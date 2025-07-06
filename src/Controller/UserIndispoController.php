<?php

namespace App\Controller;

use App\Entity\Indisponibilite;
use App\Repository\IndisponibiliteRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mes-indispos')]
#[IsGranted('ROLE_USER')]
class UserIndispoController extends AbstractController
{
    #[Route('/', name: 'user_indispo_index', methods: ['GET'])]
    public function index(IndisponibiliteRepository $repo): Response
    {
        $me   = $this->getUser();
        $now  = new \DateTimeImmutable();

        $indispos = $repo->createQueryBuilder('i')
            ->andWhere('i.user = :me')->setParameter('me', $me)
            ->andWhere('i.end >= :now')->setParameter('now', $now)
            ->orderBy('i.start', 'ASC')
            ->getQuery()->getResult();

        return $this->render('indispo/my.html.twig', [
            'indispos' => $indispos,
        ]);
    }

    #[Route('/ajax/create', name: 'user_indispo_ajax_create', methods: ['POST'])]
    public function ajaxCreate(Request $req, EntityManagerInterface $em): Response
    {
        $i = new Indisponibilite();
        $i->setUser($this->getUser())
            ->setStart(new \DateTime($req->request->get('start')))
            ->setEnd(new \DateTime($req->request->get('end')));

        $em->persist($i); $em->flush();
        return $this->json(['ok'=>true]);
    }

    #[Route('/ajax/{id}/delete', name: 'user_indispo_ajax_delete', methods: ['POST'])]
    public function ajaxDelete(Indisponibilite $i, EntityManagerInterface $em): Response
    {
        $em->remove($i); $em->flush();
        return $this->json(['ok'=>true]);
    }
}
