<?php
namespace App\Controller;

use App\Entity\Signalement;
use App\Repository\SignalementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/signalement')]
#[IsGranted('ROLE_USER')]
class SignalementController extends AbstractController
{
    #[Route('', name: 'signalement_index', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em,SignalementRepository $repo): Response
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
            return $this->redirectToRoute('signalement_index');
        }

        // On transforme les entités en tableau “prêt à consommer” côté JS
        $signalements = array_map(static function ($s) {
            return [
                'id' => $s->getId(),
                'lat'         => $s->getLatitude(),
                'lon'         => $s->getLongitude(),
                'type'        => $s->getType(),
                'description' => $s->getDescription(),
                'createdAt' => $s->getCreatedAt()?->format('d/m/Y H:i'),
            ];
        }, $repo->findAll());

        return $this->render('signalement/index.html.twig', [
            'form' => $form->createView(),
            'signalements' => $signalements,
        ]);
    }

    #[IsGranted('ROLE_ADMIN')]
    #[Route('/supprimer/{id}', name: 'signalement_supprimer', methods: ['POST'])]
    public function supprimer(Signalement $signalement, Request $request, EntityManagerInterface $em, AuthorizationCheckerInterface $auth): Response
    {
        if (!$auth->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

            $em->remove($signalement);
            $em->flush();
            $this->addFlash('success', 'Signalement supprimé.');


        return $this->redirectToRoute('signalement_index');
    }



    #[Route('/signalements/carte', name: 'signalement_map')]
    #[IsGranted('ROLE_ADMIN')]
    public function carte(SignalementRepository $repo): Response
    {
        // On transforme les entités en tableau “prêt à consommer” côté JS
        $signalements = array_map(static function ($s) {
            return [
                'lat'         => $s->getLatitude(),
                'lon'         => $s->getLongitude(),
                'type'        => $s->getType(),
                'description' => $s->getDescription(),
            ];
        }, $repo->findAll());


        return $this->render('signalement/map.html.twig', [
            'signalements' => $signalements,
        ]);
    }
}
