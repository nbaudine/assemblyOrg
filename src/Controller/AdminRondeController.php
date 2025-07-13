<?php

namespace App\Controller;

use App\Entity\Ronde;
use App\Entity\User;
use App\Repository\IndisponibiliteRepository;
use App\Repository\RondeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/rondes')]
#[IsGranted('ROLE_ADMIN')]
class AdminRondeController extends AbstractController
{
    /* --------------------------------------------------------------------- */
    /*  LISTE DES RONDES (vue principale)                                    */
    /* --------------------------------------------------------------------- */
    #[Route('/index', name: 'admin_rondes_index')]
    public function index(RondeRepository $rondeRepo, EntityManagerInterface $em): Response
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        $rondes = $rondeRepo->createQueryBuilder('r')
            ->where('r.end >= :now')
            ->setParameter('now', $now)
            ->orderBy('r.start', 'ASC')
            ->getQuery()
            ->getResult();

        /*  Mappe les utilisateurs dans une indispo pour surligner en orange  */
        $unavailableMap = [];
        if ($rondes) {
            $ids = array_map(fn(Ronde $r) => $r->getId(), $rondes);

            $rows = $em->createQuery(/** @lang DQL */'
                SELECT r.id  AS ronde_id,
                       u.id  AS user_id
                FROM   App\Entity\Ronde            r
                JOIN   r.sesUsers                  u
                JOIN   App\Entity\Indisponibilite  ind WITH ind.user = u
                WHERE  r.id IN (:ids)
                  AND  r.start >= ind.start
                  AND  r.end   <= ind.end
            ')
                ->setParameter('ids', $ids)
                ->getArrayResult();

            foreach ($rows as $row) {
                $unavailableMap[$row['ronde_id']][] = (int) $row['user_id'];
            }
        }

        return $this->render('admin/ronde/index.html.twig', [
            'rondes'         => $rondes,
            'unavailableMap' => $unavailableMap,
        ]);
    }

    /* --------------------------------------------------------------------- */
    /*  AJAX : liste des utilisateurs disponibles                            */
    /* --------------------------------------------------------------------- */
    #[Route('/form', name: 'admin_rondes_form', methods: ['GET'])]
    public function getRondeForm(
        Request                   $request,
        UserRepository            $userRepo,
        RondeRepository           $rondeRepo,
        IndisponibiliteRepository $indispoRepo,
    ): Response {
        /* ----- paramètres ----- */
        $start = new \DateTimeImmutable($request->query->get('start'));
        $end   = new \DateTimeImmutable($request->query->get('end'));
        $rondeId = $request->query->get('id');

        $dayStart = $start->setTime(0, 0);
        $dayEnd   = $dayStart->modify('+1 day');

        $ronde        = $rondeId ? $rondeRepo->find($rondeId) : null;
        $currentUsers = $ronde ? $ronde->getSesUsers()->map(fn($u) => $u->getId())->toArray() : [];

        /* ----- indispos + déjà affectés ce jour-là ----- */
        $indispoIds = $indispoRepo->findUsersIndisponiblesBetween($start, $end);

        $busyIds = $rondeRepo->createQueryBuilder('r')
            ->select('DISTINCT u.id')
            ->join('r.sesUsers', 'u')
            ->where('r.start >= :dayStart')
            ->andWhere('r.start <  :dayEnd')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd',   $dayEnd)
            ->getQuery()
            ->getSingleColumnResult();

        /*  On exclut tout… sauf les users déjà dans la ronde courante  */
        $exclude = array_diff(array_merge($indispoIds, $busyIds), $currentUsers);

        /* ----- chargement + tri par nb de rondes ----- */
        $qb = $userRepo->createQueryBuilder('u')
            ->leftJoin('u.rondes', 'rPart')
            ->addSelect('COUNT(rPart.id) AS HIDDEN nb')
            ->groupBy('u.id')
            ->orderBy('nb', 'ASC')
            ->addOrderBy('u.nom', 'ASC');

        if ($exclude) {
            $qb->where($qb->expr()->notIn('u.id', ':ex'))
                ->setParameter('ex', $exclude);
        }

        return $this->render('admin/partials/_user_list.html.twig', [
            'users' => $qb->getQuery()->getResult(),
        ]);
    }

    /* --------------------------------------------------------------------- */
    /*  HYDRATE : appliqué par create / update                               */
    /* --------------------------------------------------------------------- */
    private function hydrate(Ronde $ronde, Request $req, UserRepository $userRepo): void
    {
        $start = new \DateTime($req->request->get('start'));
        $end   = new \DateTime($req->request->get('end'));

        $ronde->setStart($start)->setEnd($end);

        // récupère toujours un tableau — même s’il n’y a qu’un seul ID
        $ids = $req->request->all('users') ?: $req->request->get('users', []);
        $ids = is_array($ids) ? $ids : [$ids];

        $ronde->getSesUsers()->clear();
        foreach ($ids as $id) {
            if (!$user = $userRepo->find($id)) {
                continue;
            }

            /*  Un seul créneau par jour  */
            $sameDay = $user->getRondes()->exists(
                fn($_, Ronde $r) => $r !== $ronde && $r->getStart()->format('Y-m-d') === $start->format('Y-m-d')
            );
            if (!$sameDay) {
                $ronde->addSesUser($user);
            }
        }
    }


    #[Route('/fill', name: 'admin_rondes_fill')]
    public function fillRondes(
        RondeRepository            $rondeRepo,
        UserRepository             $userRepo,
        IndisponibiliteRepository  $indispoRepo,
        EntityManagerInterface     $em
    ): Response {

        $rondes = $rondeRepo->findBy([], ['start' => 'ASC']);
        $users = $userRepo->findAll();

        //RETRAIT ROLE NON RONDES
        $users = array_filter($users, function ($user) {
            return !in_array('ROLE_NO_ROUND', $user->getRoles(), true);
        });

        /* ----------- charge le nombre de participations par user ------------ */
        $userLoad = [];                // [id => nbParticipations]
        foreach ($users as $u) {
            $userLoad[$u->getId()] = $u->getRondes()->count();
        }

        /* ----------- nouvelle structure : users déjà pris par date ---------- */
        $assignedByDay = [];           // ['2025-07-11' => [3,7,12], …]

        foreach ($rondes as $ronde) {
            $start = $ronde->getStart();
            $end   = $ronde->getEnd();
            $jour  = $start->format('Y-m-d');

            if (!isset($assignedByDay[$jour])) {
                // initialise une liste avec les users qui ont (déjà) une ronde ce jour en base
                $assignedByDay[$jour] = $rondeRepo->createQueryBuilder('r')
                    ->select('DISTINCT u.id')
                    ->join('r.sesUsers', 'u')
                    ->where('r.start >= :dayStart')
                    ->andWhere('r.start <  :dayEnd')
                    ->setParameter('dayStart', (clone $start)->setTime(0, 0))
                    ->setParameter('dayEnd',   (clone $start)->setTime(0, 0)->modify('+1 day'))
                    ->getQuery()
                    ->getSingleColumnResult();
            }

            /* ------------ on remplit jusqu’à 2 utilisateurs ------------------ */
            while ($ronde->getSesUsers()->count() < 2) {

                $available = array_filter($users, function (User $u) use (
                    $ronde, $indispoRepo, $start, $end, $jour, $assignedByDay
                ) {
                    /* déjà affecté à la ronde courante ? */
                    if ($ronde->getSesUsers()->contains($u)) return false;

                    /* indisponible ? */
                    if ($indispoRepo->isUserUnavailableDuring($u, $start, $end)) return false;

                    /* déjà placé le même jour (en base OU dans $assignedByDay) ? */
                    if (in_array($u->getId(), $assignedByDay[$jour], true)) return false;

                    return true;
                });

                if (!$available) break;    // plus personne de libre

                /* tri par charge la plus faible */
                usort($available, fn(User $a, User $b) =>
                    $userLoad[$a->getId()] <=> $userLoad[$b->getId()]
                );

                $chosen = $available[0];
                $ronde->addSesUser($chosen);
                $userLoad[$chosen->getId()]++;

                // on mémorise tout de suite pour bloquer ce user dans les rondes suivantes du même jour
                $assignedByDay[$jour][] = $chosen->getId();
            }

            $em->persist($ronde);
        }

        $em->flush();
        $this->addFlash('success', 'Les rondes ont été réparties sans doublons journaliers ✨');
        return $this->redirectToRoute('admin_rondes_index');
    }


    #[Route('/{id}/delete', name: 'admin_rondes_delete', methods: ['POST'])]
    public function delete(Ronde $ronde, Request $req, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-ronde-'.$ronde->getId(), $req->request->get('_token'))) {
            $em->remove($ronde);
            $em->flush();
            $this->addFlash('success', 'Ronde supprimée.');
        }

        return $this->redirectToRoute('admin_rondes_index');
    }



    /* --------------------------------------------------------------------- */
    /*  AJAX create / update                                                 */
    /* --------------------------------------------------------------------- */
    #[Route('/ajax/create', name: 'admin_rondes_ajax_create', methods: ['POST'])]
    public function ajaxCreate(Request $req, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        try {
            $ronde = new Ronde();
            $this->hydrate($ronde, $req, $userRepo);
            $em->persist($ronde);
            $em->flush();

            return $this->json(['status' => 'ok'], 201);
        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/ajax/rondes/admin/{id}/update', name: 'admin_rondes_ajax_update', methods: ['POST'])]
    public function update(Request $request, Ronde $ronde, EntityManagerInterface $em): JsonResponse
    {
        try {
            $start = new \DateTime($request->request->get('start'));
            $end = new \DateTime($request->request->get('end'));
            $userIds = $request->request->all('users'); // Pour récupérer users[]

            if (!$start || !$end) {
                throw new \Exception('Date de début ou de fin manquante');
            }

            $ronde->setStart($start);
            $ronde->setEnd($end);

            // Vider les anciens utilisateurs si besoin
            foreach ($ronde->getSesUsers() as $u) {
                $ronde->removeSesUser($u);
            }

            foreach ($userIds as $userId) {
                $user = $em->getRepository(User::class)->find($userId);
                if ($user) {
                    $ronde->addSesUser($user);
                }
            }

            $em->flush();

            return new JsonResponse(['success' => true]);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()], 500);
        }
    }


    /* --------------------------------------------------------------------- */
    /*  DELETE & FILL restent inchangés                                      */
    /* --------------------------------------------------------------------- */
    // … delete(), fillRondes(), new(), edit() exactement comme avant …
}
