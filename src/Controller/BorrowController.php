<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller per la funzionalità "Prendi in prestito".
 * Gestisce la ricerca geolocalizzata di libri disponibili al prestito nelle vicinanze.
 */
class BorrowController extends AbstractController
{
    /**
     * Renderizza la pagina principale del prestito con la mappa Leaflet.
     * Passa la posizione GPS dell'utente corrente al template per centrare la mappa.
     */
    #[Route('/borrow', name: 'app_borrow')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('borrow/index.html.twig', [
            'userLat'     => $user->getLatitude(),
            'userLng'     => $user->getLongitude(),
            'hasLocation' => $user->hasLocation(),
        ]);
    }

    /**
     * Passo 1: ricerca libri per titolo o autore (senza filtro geografico).
     * Usato dall'autocompletamento nella barra di ricerca della pagina prestito.
     * Richiede almeno 2 caratteri per evitare query troppo generiche.
     */
    #[Route('/borrow/search-books', name: 'app_borrow_search_books')]
    public function searchBooks(Request $request, BookRepository $bookRepo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (strlen($q) < 2) {
            return new JsonResponse([]);
        }

        $books = $bookRepo->search($q, 30);

        // Serializza solo i campi necessari al frontend
        $data = array_map(fn($b) => [
            'id'     => $b->getId(),
            'title'  => $b->getTitle(),
            'author' => $b->getBookAuthor(),
            'cover'  => $b->getCoverUrl(),
        ], $books);

        return new JsonResponse($data);
    }

    /**
     * Passo 2: dato l'ID di un libro, restituisce la lista degli utenti vicini che lo possiedono.
     * Il raggio di ricerca è configurabile (default 25 km, massimo 100 km).
     * L'utente corrente viene escluso dai risultati.
     */
    #[Route('/borrow/book/{id}/owners', name: 'app_borrow_book_owners')]
    public function bookOwners(int $id, Request $request, BookRepository $bookRepo, UserRepository $userRepo): JsonResponse
    {
        $user = $this->getUser();

        // Usa la posizione del parametro se fornita, altrimenti quella salvata nel profilo
        $lat    = (float) $request->query->get('lat', $user->getLatitude() ?? 0);
        $lng    = (float) $request->query->get('lng', $user->getLongitude() ?? 0);
        $radius = min(100, max(1, (float) $request->query->get('radius', 25)));

        if (!$lat && !$lng) {
            return new JsonResponse([]);
        }

        $book = $bookRepo->find($id);
        if (!$book) {
            return new JsonResponse([], 404);
        }

        // Cerca utenti entro il raggio con quel titolo tra i libri posseduti
        $results = $userRepo->findNearbyUsersWithBook($lat, $lng, $radius, $book->getTitle(), 200);

        // Filtra per ottenere solo i proprietari del libro con l'ID esatto
        $owners = [];
        foreach ($results as $item) {
            if ($item['book']->getId() === $id) {
                $owners = $item['owners'];
                break;
            }
        }

        // Rimuove l'utente corrente dalla lista dei prestatori
        $currentUserId = $user->getId();
        $owners = array_values(array_filter(
            $owners,
            fn($o) => $o['user']->getId() !== $currentUserId
        ));

        // Serializza i dati per il frontend (mappa + lista laterale)
        $data = array_map(fn($o) => [
            'userId'      => $o['user']->getId(),
            'username'    => $o['user']->getUsername(),
            'displayName' => $o['user']->getDisplayName(),
            'avatarUrl'   => $o['user']->getAvatarUrl(),
            'userLat'     => $o['user']->getLatitude(),
            'userLng'     => $o['user']->getLongitude(),
            'distance'    => $o['distance'],
        ], $owners);

        return new JsonResponse([
            'book' => [
                'id'     => $book->getId(),
                'title'  => $book->getTitle(),
                'author' => $book->getBookAuthor(),
                'cover'  => $book->getCoverUrl(),
            ],
            'owners' => $data,
        ]);
    }
}
