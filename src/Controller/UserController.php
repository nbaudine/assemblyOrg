<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/profil')]
#[IsGranted('ROLE_USER')]
class UserController extends AbstractController
{
    #[Route('/', name: 'app_profile')]
    public function edit(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $nom = $request->request->get('nom');
            $prenom = $request->request->get('prenom');
            $telephone = $request->request->get('telephone');
            $newPassword = $request->request->get('plainPassword');

            $user->setEmail($email);
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setTelephone($telephone);

            if ($newPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
            }

            $em->flush();
            $this->addFlash('success', 'Profil mis à jour avec succès.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('user/index.html.twig', [
            'user' => $user,
        ]);
    }

}
