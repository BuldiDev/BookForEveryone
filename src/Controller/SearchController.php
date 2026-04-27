<?php

namespace App\Controller;

use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller per la ricerca libri nel catalogo della piattaforma.
 */
class SearchController extends AbstractController
{
    /**
     * Mostra la pagina di ricerca con i risultati filtrati per titolo o autore.
     * Se il parametro "q" è assente o vuoto, la pagina viene mostrata senza risultati.
     */
    #[Route('/search', name: 'app_search')]
    public function index(Request $request, BookRepository $bookRepo): Response
    {
        $query   = $request->query->get('q', '');
        $results = [];

        if ($query !== '') {
            $results = $bookRepo->search($query);
        }

        return $this->render('search/index.html.twig', [
            'query'   => $query,
            'results' => $results,
        ]);
    }
}
