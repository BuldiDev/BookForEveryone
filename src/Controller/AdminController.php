<?php

namespace App\Controller;

use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('/admin/stats', name: 'app_admin_stats')]
    public function stats(BookRepository $bookRepo): Response
    {
        return $this->render('admin/stats.html.twig', [
            'bookStats'   => $bookRepo->getAdminBookStats(),
            'globalStats' => $bookRepo->getGlobalStats(),
        ]);
    }
}
