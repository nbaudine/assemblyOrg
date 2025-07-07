<?php

namespace App\Controller;

use App\Entity\Ronde;
use App\Entity\User;
use App\Repository\IndisponibiliteRepository;
use App\Repository\RondeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/rondes')]
#[IsGranted('ROLE_ADMIN')]
class AdminRondeController extends AbstractController
{
    // src/Controller/Admin/RondeAdminController.php
    #[Route('/admin/rondes', name: 'admin_rondes_index')]
    public function index(
        RondeRepository        $rondeRepo,
        EntityManagerInterface $em,
    ): Response {
        /* ─────────── 1) Récupère les rondes non terminées ─────────── */
        $nowParis = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));

        /** @var Ronde[] $rondes */
        $rondes = $rondeRepo->createQueryBuilder('r')
            ->where('r.end >= :now')          // ← garde futur + en cours
            ->setParameter('now', $nowParis)
            ->orderBy('r.start', 'ASC')
            ->getQuery()
            ->getResult();

        /* ─────────── 2) Mappe les utilisateurs indisponibles ─────────── */
        $unavailableMap = [];
        if ($rondes) {
            $ids = array_map(static fn(Ronde $r) => $r->getId(), $rondes);

            $rows = $em->createQuery(/** @lang DQL */'
            SELECT r.id  AS ronde_id,
                   u.id  AS user_id
            FROM   App\Entity\Ronde            r
            JOIN   r.sesUsers                  u
            JOIN   App\Entity\Indisponibilite  ind WITH ind.user = u
            WHERE  r.id  IN (:ids)
              AND  r.start >= ind.start
              AND  r.end   <= ind.end
        ')
                ->setParameter('ids', $ids)
                ->getArrayResult();

            foreach ($rows as $row) {
                $unavailableMap[$row['ronde_id']][] = (int) $row['user_id'];
            }
        }

        /* ─────────── 3) Affiche la vue ─────────── */
        return $this->render('admin/ronde/index.html.twig', [
            'rondes'         => $rondes,
            'unavailableMap' => $unavailableMap,
        ]);
    }



    #[Route('/new', name: 'admin_rondes_new', methods: ['GET', 'POST'])]
    public function new(Request $req, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $ronde = new Ronde();

        if ($req->isMethod('POST')) {
            $this->hydrate($ronde, $req, $userRepo);
            $em->persist($ronde);
            $em->flush();

            $this->addFlash('success', 'Ronde créée.');
            return $this->redirectToRoute('admin_rondes_index');
        }



        return $this->render('admin/ronde/form.html.twig', [
            'ronde' => $ronde,
            'edit'  => false,
            'users' => $userRepo->findAll(),
        ]);
    }

    // AdminRondeController.php

// … namespace & use déjà présents …

    /* ================================================================
     * 1) LISTE AJAX DES UTILISATEURS DISPONIBLES
     *    - exclus : indispos + déjà pris le même jour
     *    - triés : par nombre total de participations (croissant)
     * ================================================================ */
    #[Route('/admin/rondes/form', name: 'admin_rondes_form', methods: ['GET'])]
    public function getRondeForm(
        Request                    $request,
        UserRepository             $userRepo,
        RondeRepository            $rondeRepo,
        IndisponibiliteRepository  $indispoRepo,
        EntityManagerInterface     $em
    ): Response {
        $start = new \DateTimeImmutable($request->query->get('start'));
        $end   = new \DateTimeImmutable($request->query->get('end'));
        $dayStart = $start->setTime(0, 0, 0);
        $dayEnd   = $dayStart->modify('+1 day');

        // ID ronde (si édition)
        $rondeId = $request->query->get('id');
        $ronde = $rondeId ? $rondeRepo->find($rondeId) : null;
        $currentUsers = $ronde ? $ronde->getSesUsers()->map(fn($u) => $u->getId())->toArray() : [];

        // exclusions (indispos + déjà sur une ronde ce jour-là) SAUF ceux déjà assignés
        $indispoIds = $indispoRepo->findUsersIndisponiblesBetween($start, $end);
        $busyIds = $rondeRepo->createQueryBuilder('r')
            ->select('DISTINCT u.id')
            ->join('r.sesUsers', 'u')
            ->where('r.start >= :dayStart')
            ->andWhere('r.start < :dayEnd')
            ->setParameter('dayStart', $dayStart)
            ->setParameter('dayEnd', $dayEnd)
            ->getQuery()
            ->getSingleColumnResult();

        $exclude = array_diff(array_unique(array_merge($indispoIds, $busyIds)), $currentUsers);

        // récupère TOUS les utilisateurs non exclus + ceux de la ronde courante
        $qb = $userRepo->createQueryBuilder('u')
            ->leftJoin('u.rondes', 'rPart')
            ->addSelect('COUNT(rPart.id) AS HIDDEN nb')
            ->groupBy('u.id')
            ->orderBy('nb', 'ASC')
            ->addOrderBy('u.nom', 'ASC');

        if (!empty($exclude)) {
            $qb->where($qb->expr()->notIn('u.id', ':ex'))
                ->setParameter('ex', $exclude);
        }

        $users = $qb->getQuery()->getResult();

        return $this->render('admin/partials/_user_list.html.twig', [
            'users' => $users,
        ]);
    }


    /* ================================================================
     * 2) HYDRATE UNE RONDE À PARTIR DE LA REQUÊTE
     *    - applique la règle « 1 créneau / jour / user »
     * ================================================================ */
    private function hydrate(Ronde $ronde, Request $req, UserRepository $userRepo): void
    {
        $start = new \DateTimeImmutable($req->request->get('start'));
        $end   = new \DateTimeImmutable($req->request->get('end'));
        $ronde->setStart($start)->setEnd($end);

        // ------------------ gestion ManyToMany ------------
        $ronde->getSesUsers()->clear();
        $idsDemandes = $req->request->all('users') ?? [];
        foreach ($idsDemandes as $id) {
            if (!$user = $userRepo->find($id)) {
                continue;                                     // id invalide
            }

            // Vérifie s'il possède déjà une ronde le même jour
            $hasSameDay = $user->getRondes()->exists(
                fn($key, Ronde $r) =>
                    $r !== $ronde &&
                    $r->getStart()->format('Y-m-d') === $start->format('Y-m-d')
            );

            if (!$hasSameDay) {
                $ronde->addSesUser($user);
            }
        }
    }



    #[Route('/{id}/edit', name: 'admin_rondes_edit', methods: ['GET', 'POST'])]
    public function edit(Ronde $ronde, Request $req, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($req->isMethod('POST')) {
            $this->hydrate($ronde, $req, $userRepo);
            $em->flush();

            $this->addFlash('success', 'Ronde mise à jour.');
            return $this->redirectToRoute('admin_rondes_index');
        }

        return $this->render('admin/ronde/form.html.twig', [
            'ronde' => $ronde,
            'edit'  => true,
            'users' => $userRepo->findAll(),
        ]);
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

    /** Remplit l’entité Ronde depuis la requête */

    #[Route('/admin/rondes/fill', name: 'admin_rondes_fill')]
    public function fillRondes(
        RondeRepository            $rondeRepository,
        UserRepository             $userRepository,
        IndisponibiliteRepository  $indispoRepository,
        EntityManagerInterface     $em
    ): Response {
        $rondes = $rondeRepository->findBy([], ['start' => 'ASC']);
        $users  = $userRepository->findAll();

        // Préparation des charges
        $userLoad = []; // [id => nbParticipations]
        foreach ($users as $u) {
            $userLoad[$u->getId()] = $u->getRondes()->count();
        }

        foreach ($rondes as $ronde) {
            while ($ronde->getSesUsers()->count() < 2) {
                $start = $ronde->getStart();
                $end   = $ronde->getEnd();
                $jour  = $start->format('Y-m-d');

                // Liste des candidats valides
                $available = array_filter($users, function (User $u) use ($ronde, $indispoRepository, $start, $end, $jour) {
                    // déjà affecté à cette ronde
                    if ($ronde->getSesUsers()->contains($u)) return false;

                    // indispo ?
                    if ($indispoRepository->isUserUnavailableDuring($u, $start, $end)) return false;

                    // a déjà une autre ronde ce jour-là ?
                    foreach ($u->getRondes() as $r) {
                        if ($r !== $ronde && $r->getStart()->format('Y-m-d') === $jour) {
                            return false;
                        }
                    }

                    return true;
                });

                if (!$available) {
                    break; // pas de candidats valides
                }

                // Trie les candidats par charge croissante
                usort($available, fn(User $a, User $b) =>
                    $userLoad[$a->getId()] <=> $userLoad[$b->getId()]
                );

                // Choisit le moins chargé
                $chosen = $available[0];
                $ronde->addSesUser($chosen);
                $userLoad[$chosen->getId()]++;
            }

            $em->persist($ronde);
        }

        $em->flush();
        $this->addFlash('success', 'Les rondes ont été réparties équitablement ✨');

        return $this->redirectToRoute('admin_rondes_index');
    }



    // src/Controller/AdminRondeController.php (suite)

    #[Route('/ajax/create', name: 'admin_rondes_ajax_create', methods: ['POST'])]
    public function ajaxCreate(Request $req, EntityManagerInterface $em, UserRepository $ur): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $ronde = new Ronde();
        $this->hydrate($ronde, $req, $ur);
        $em->persist($ronde); $em->flush();
        return $this->json(['status'=>'success']);
    }

    #[Route('/ajax/{id}/update', name: 'admin_rondes_ajax_update', methods: ['POST'])]
    public function ajaxUpdate(Ronde $ronde, Request $req, EntityManagerInterface $em, UserRepository $ur): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $this->hydrate($ronde, $req, $ur);
        $em->flush();
        return $this->json(['status'=>'success']);
    }

}
