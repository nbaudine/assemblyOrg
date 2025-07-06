<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
class AdminUserController extends AbstractController
{
    #[Route('/', name: 'admin_users_index', methods: ['GET'])]
    public function index(UserRepository $repo): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $repo->findAll(),
        ]);
    }

    #[Route('/new', name: 'admin_users_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        $user = new User();

        if ($request->isMethod('POST')) {
            $this->hydrateFromRequest($user, $request, $hasher, true);
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'user'   => $user,
            'edit'   => false,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_users_edit', methods: ['GET', 'POST'])]
    public function edit(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($request->isMethod('POST')) {
            $this->hydrateFromRequest($user, $request, $hasher, false);
            $em->flush();

            $this->addFlash('success', 'Utilisateur mis à jour.');
            return $this->redirectToRoute('admin_users_index');
        }

        return $this->render('admin/user/form.html.twig', [
            'user' => $user,
            'edit' => true,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_users_delete', methods: ['POST'])]
    public function delete(User $user, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }

        return $this->redirectToRoute('admin_users_index');
    }

    /** Remplit l’entité User depuis la requête */
    private function hydrateFromRequest(
        User $user,
        Request $request,
        UserPasswordHasherInterface $hasher,
        bool $isNew
    ): void {
        $user->setEmail($request->request->get('email'));
        $user->setNom($request->request->get('nom'));
        $user->setPrenom($request->request->get('prenom'));
        $user->setTelephone($request->request->get('telephone'));

        // Rôles (checkbox multiples)
        $roles = $request->request->all('roles'); // peut retourner null
        $user->setRoles($roles ?? []);

        // Mot de passe (obligatoire en création, optionnel en édition)
        $plain = $request->request->get('plainPassword');
        if ($plain || $isNew) {
            $user->setPassword($hasher->hashPassword($user, '144000' ?: 'changeme'));
        }
    }
}
