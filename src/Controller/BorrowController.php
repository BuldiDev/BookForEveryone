<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BorrowController extends AbstractController
{
    #[Route('/borrow', name: 'app_borrow')]
    public function index(UserRepository $userRepo, Request $request): Response
    {
        $user = $this->getUser();
        $nearbyBooks = [];
        $userLat = $user->getLatitude();
        $userLng = $user->getLongitude();
        $search = trim($request->query->get('q', ''));

        // Mostra risultati SOLO se c'è una query di ricerca
        if ($search && $user->hasLocation()) {
            $allBooks = $userRepo->findNearbyUsersWithBook(
                $userLat, 
                $userLng, 
                25.0, 
                $search, 
                200
            );
            
            // Filtra se stesso dagli owner
            foreach ($allBooks as &$bookData) {
                $bookData['owners'] = array_filter(
                    $bookData['owners'], 
                    fn($owner) => $owner['user']->getId() !== $user->getId()
                );
            }
            
            // Rimuovi libri senza proprietari disponibili
            $nearbyBooks = array_filter($allBooks, fn($item) => count($item['owners']) > 0);
        }

        return $this->render('borrow/index.html.twig', [
            'nearbyBooks' => $nearbyBooks,
            'query' => $search,
            'userLat' => $userLat,
            'userLng' => $userLng,
            'hasLocation' => $user->hasLocation(),
        ]);
    }

    #[Route('/borrow/search', name: 'app_borrow_search')]
    public function search(Request $request, UserRepository $userRepo): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = $request->query->get('radius', 25);
        $searchQuery = trim($request->query->get('q', ''));

        if (!$lat || !$lng || !$searchQuery) {
            return new JsonResponse([]);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = min(100, max(1, (float) $radius));

        $results = $userRepo->findNearbyUsersWithBook(
            $lat, 
            $lng, 
            $radius, 
            $searchQuery, 
            200
        );
        
        // Filtra l'utente corrente dagli owner
        $currentUserId = $this->getUser()->getId();
        foreach ($results as &$bookData) {
            $bookData['owners'] = array_filter(
                $bookData['owners'], 
                fn($owner) => $owner['user']->getId() !== $currentUserId
            );
        }
        
        // Rimuovi libri senza owner disponibili
        $results = array_filter($results, fn($item) => count($item['owners']) > 0);

        $data = [];
        foreach ($results as $item) {
            $book = $item['book'];
            $data[] = [
                'bookId' => $book->getId(),
                'bookTitle' => $book->getTitle(),
                'bookAuthor' => $book->getBookAuthor(),
                'bookCover' => $book->getCoverUrl(),
                'owners' => array_map(function($owner) {
                    $user = $owner['user'];
                    return [
                        'userId' => $user->getId(),
                        'username' => $user->getUsername(),
                        'displayName' => $user->getDisplayName(),
                        'avatarUrl' => $user->getAvatarUrl(),
                        'userLat' => $user->getLatitude(),
                        'userLng' => $user->getLongitude(),
                        'distance' => $owner['distance'],
                    ];
                }, $item['owners'])
            ];
        }

        return new JsonResponse($data);
    }
}
