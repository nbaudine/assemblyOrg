<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlanningController extends AbstractController
{
    #[Route('/rondes', name: 'rondes_index')]
    public function index(): Response
    {
        return $this->render('planning/index.html.twig', [
        ]);
    }

    #[Route('/admin/rondes', name: 'admin_rondes_index')]
    public function admin_rondes_index(): Response
    {
        return $this->render('planning/index.html.twig', [
        ]);
    }
}
