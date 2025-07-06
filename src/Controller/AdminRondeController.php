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
    #[Route('/', name: 'admin_rondes_index', methods: ['GET'])]
    public function index(
        RondeRepository $repo,
        UserRepository $userRepo          // <-- injecte le repo User
    ): Response
    {
        return $this->render('admin/ronde/index.html.twig', [
            'rondes' => $repo->findAll(),
            'users'  => $userRepo->findAll(),   // <-- passe les utilisateurs à la vue
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

    #[Route('/admin/rondes/form', name: 'admin_rondes_form', methods: ['GET'])]
    public function getRondeForm(Request $request, UserRepository $userRepo, IndisponibiliteRepository $indispoRepo): Response
    {
        $start = new \DateTime($request->query->get('start'));
        $end   = new \DateTime($request->query->get('end'));

        $indispoUserIds = $indispoRepo->findUsersIndisponiblesBetween($start, $end);
        $usersDispo = $userRepo->createQueryBuilder('u')
            ->where('u.id NOT IN (:ids)')
            ->setParameter('ids', $indispoUserIds ?: [0]) // sécurité : éviter IN ()
            ->getQuery()
            ->getResult();

        return $this->render('admin/partials/_user_list.html.twig', [
            'users' => $usersDispo,
        ]);
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
    private function hydrate(Ronde $ronde, Request $req, UserRepository $userRepo): void
    {
        $start = new \DateTime($req->request->get('start'));
        $end   = new \DateTime($req->request->get('end'));
        $ronde->setStart($start)->setEnd($end);

        // gestion ManyToMany
        $ids = $req->request->all('users') ?? [];
        $ronde->getSesUsers()->clear();
        foreach ($ids as $id) {
            if ($user = $userRepo->find($id)) {
                $ronde->addSesUser($user);
            }
        }
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
