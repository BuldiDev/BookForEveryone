<?php

namespace App\Controller;

use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller riservato agli amministratori della piattaforma.
 * L'accesso è protetto dal ruolo ROLE_ADMIN verificato a livello di classe.
 */
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    /**
     * Mostra la dashboard con le statistiche aggregate della piattaforma:
     * libri più apprezzati, più letti, più recensiti e dati globali (utenti, prestiti, ecc.).
     */
    #[Route('/admin/stats', name: 'app_admin_stats')]
    public function stats(BookRepository $bookRepo): Response
    {
        return $this->render('admin/stats.html.twig', [
            'bookStats'   => $bookRepo->getAdminBookStats(),   // classifiche top-10 per categoria
            'globalStats' => $bookRepo->getGlobalStats(),       // contatori globali (utenti, libri, ecc.)
        ]);
    }
}
