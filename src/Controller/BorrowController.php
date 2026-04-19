<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BorrowController extends AbstractController
{
    #[Route('/borrow', name: 'app_borrow')]
    public function index(): Response
    {
        $user = $this->getUser();

        return $this->render('borrow/index.html.twig', [
            'userLat' => $user->getLatitude(),
            'userLng' => $user->getLongitude(),
            'hasLocation' => $user->hasLocation(),
        ]);
    }

    /** Step 1: ricerca libri per titolo/autore (senza geolocalizzazione) */
    #[Route('/borrow/search-books', name: 'app_borrow_search_books')]
    public function searchBooks(Request $request, BookRepository $bookRepo): JsonResponse
    {
        $q = trim($request->query->get('q', ''));
        if (strlen($q) < 2) {
            return new JsonResponse([]);
        }

        $books = $bookRepo->search($q, 30);

        $data = array_map(fn($b) => [
            'id' => $b->getId(),
            'title' => $b->getTitle(),
            'author' => $b->getBookAuthor(),
            'cover' => $b->getCoverUrl(),
        ], $books);

        return new JsonResponse($data);
    }

    /** Step 2: dato un libro, trova i proprietari vicini */
    #[Route('/borrow/book/{id}/owners', name: 'app_borrow_book_owners')]
    public function bookOwners(int $id, Request $request, BookRepository $bookRepo, UserRepository $userRepo): JsonResponse
    {
        $user = $this->getUser();
        $lat = (float) $request->query->get('lat', $user->getLatitude() ?? 0);
        $lng = (float) $request->query->get('lng', $user->getLongitude() ?? 0);
        $radius = min(100, max(1, (float) $request->query->get('radius', 25)));

        if (!$lat && !$lng) {
            return new JsonResponse([]);
        }

        $book = $bookRepo->find($id);
        if (!$book) {
            return new JsonResponse([], 404);
        }

        $results = $userRepo->findNearbyUsersWithBook($lat, $lng, $radius, $book->getTitle(), 200);

        // Trova il risultato corrispondente al libro esatto
        $owners = [];
        foreach ($results as $item) {
            if ($item['book']->getId() === $id) {
                $owners = $item['owners'];
                break;
            }
        }

        // Filtra se stesso
        $currentUserId = $user->getId();
        $owners = array_values(array_filter(
            $owners,
            fn($o) => $o['user']->getId() !== $currentUserId
        ));

        $data = array_map(fn($o) => [
            'userId' => $o['user']->getId(),
            'username' => $o['user']->getUsername(),
            'displayName' => $o['user']->getDisplayName(),
            'avatarUrl' => $o['user']->getAvatarUrl(),
            'userLat' => $o['user']->getLatitude(),
            'userLng' => $o['user']->getLongitude(),
            'distance' => $o['distance'],
        ], $owners);

        return new JsonResponse([
            'book' => [
                'id' => $book->getId(),
                'title' => $book->getTitle(),
                'author' => $book->getBookAuthor(),
                'cover' => $book->getCoverUrl(),
            ],
            'owners' => $data,
        ]);
    }
}
