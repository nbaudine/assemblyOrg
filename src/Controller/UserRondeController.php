<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\RondeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/mes-rondes')]
#[IsGranted('ROLE_USER')]
class UserRondeController extends AbstractController
{
    #[Route('/', name: 'user_my_rounds')]
    public function index(RondeRepository $repo): Response
    {
        /** @var User $me */
        $me  = $this->getUser();
        $now = new \DateTimeImmutable();

        $mesRondesFutures = $repo->createQueryBuilder('r')
            ->andWhere(':me MEMBER OF r.sesUsers')
            ->andWhere('r.start >= :now')
            ->setParameter('me', $me)
            ->setParameter('now', $now)
            ->orderBy('r.start', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('ronde/my.html.twig', [
            'rondes' => $mesRondesFutures,
            'me'     => $me,
        ]);
    }


}
