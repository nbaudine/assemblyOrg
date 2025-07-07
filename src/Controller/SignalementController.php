<?php
namespace App\Controller;

use App\Entity\Signalement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/signalement')]
#[IsGranted('ROLE_USER')]
class SignalementController extends AbstractController
{
    #[Route('', name: 'signalement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');   // sécurité de base

        $signalement = new Signalement();

        // Formulaire “inline” pour garder un seul fichier
        $form = $this->createFormBuilder($signalement)
            ->add('type', ChoiceType::class, [
                'label'    => 'Type de problème',
                'expanded' => true,      // boutons radio → mobile-friendly
                'choices'  => [
                    'Fenêtre ouverte'      => 'fenetre',
                    'Stationnement gênant' => 'stationnement',
                    'Intrusion suspecte'   => 'intrusion',
                    'Porte verrouillée'    => 'porte',
                    'Alarme déclenchée'    => 'alarme',
                    'Autre'                => 'autre',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => true
            ])
            ->add('latitude', HiddenType::class)
            ->add('longitude', HiddenType::class)
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $signalement
                ->setUser($this->getUser())
                ->setCreatedAt(new \DateTime())
                ->setDateSignalement(new \DateTime())
                ->setStatut('En cours');


            $em->persist($signalement);
            $em->flush();

            $this->addFlash('success', 'Merci ! Votre signalement a bien été envoyé.');
            return $this->redirectToRoute('signalement_new');
        }

        return $this->render('signalement/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}
