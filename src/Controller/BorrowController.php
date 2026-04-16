<?php

namespace App\Controller;

use App\Repository\BookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BorrowController extends AbstractController
{
    #[Route('/borrow', name: 'app_borrow')]
    public function index(BookRepository $bookRepo): Response
    {
        $user = $this->getUser();
        $nearbyBooks = [];
        $userLat = $user->getLatitude();
        $userLng = $user->getLongitude();

        if ($user->hasLocation()) {
            $nearbyBooks = $bookRepo->findNearbyLendableBooks($userLat, $userLng, 25.0, 50);
        }

        return $this->render('borrow/index.html.twig', [
            'nearbyBooks' => $nearbyBooks,
            'userLat' => $userLat,
            'userLng' => $userLng,
            'hasLocation' => $user->hasLocation(),
        ]);
    }

    #[Route('/borrow/search', name: 'app_borrow_search')]
    public function search(Request $request, BookRepository $bookRepo): JsonResponse
    {
        $lat = $request->query->get('lat');
        $lng = $request->query->get('lng');
        $radius = $request->query->get('radius', 25);

        if (!$lat || !$lng) {
            return new JsonResponse(['error' => 'Posizione mancante'], 400);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $radius = min(100, max(1, (float) $radius));

        $results = $bookRepo->findNearbyLendableBooks($lat, $lng, $radius, 50);

        $data = [];
        foreach ($results as $item) {
            $book = $item['book'];
            $owner = $item['owner'];
            $data[] = [
                'bookId' => $book->getId(),
                'title' => $book->getTitle(),
                'author' => $book->getBookAuthor(),
                'coverUrl' => $book->getCoverUrl(),
                'ownerUsername' => $owner->getUsername(),
                'ownerDisplayName' => $owner->getDisplayName(),
                'ownerLat' => $owner->getLatitude(),
                'ownerLng' => $owner->getLongitude(),
                'distance' => $item['distance'],
            ];
        }

        return new JsonResponse($data);
    }
}
