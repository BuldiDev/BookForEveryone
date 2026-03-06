<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\UserBookRepository;
use App\Service\FeedHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

class FeedController extends AbstractController
{
    #[Route('/', name: 'app_feed')]
    public function index(
        Request $request,
        BookRepository $bookRepo,
        UserBookRepository $userBookRepo,
        SessionInterface $session,
        FeedHistoryService $feedHistory,
    ): Response {
        $user = $this->getUser();

        // Redirect all'onboarding se non ha scelto i generi
        if (!$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding');
        }

        // Genera o recupera il seed dalla sessione (si rigenera ogni volta che visiti il feed)
        $seed = random_int(1, 999999);
        $session->set('feed_seed', $seed);

        $page = max(1, $request->query->getInt('page', 1));
        
        // Escludi libri già visti
        $seenBookIds = $feedHistory->getSeenBookIds($user);
        
        // Aumenta il numero di libri per dare più varietà
        $books = $bookRepo->getFeedForUser($user, $page, 30, $seed, $seenBookIds);
        
        // Traccia i libri mostrati
        $bookIds = array_map(fn($book) => $book->getId(), $books);
        $feedHistory->markBooksAsSeen($user, $bookIds);

        // Prepara le interazioni dell'utente per ogni libro
        $userBooks = [];
        foreach ($books as $book) {
            $userBooks[$book->getId()] = $userBookRepo->findByUserAndBook($user, $book);
        }

        return $this->render('feed/index.html.twig', [
            'books' => $books,
            'userBooks' => $userBooks,
            'page' => $page,
        ]);
    }

    #[Route('/feed/load-more', name: 'app_feed_load_more')]
    public function loadMore(
        Request $request,
        BookRepository $bookRepo,
        UserBookRepository $userBookRepo,
        SessionInterface $session,
        FeedHistoryService $feedHistory,
    ): Response {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        
        // Usa lo stesso seed della sessione per mantenere l'ordine consistente
        $seed = $session->get('feed_seed', random_int(1, 999999));
        
        // Escludi libri già visti
        $seenBookIds = $feedHistory->getSeenBookIds($user);
        
        $books = $bookRepo->getFeedForUser($user, $page, 30, $seed, $seenBookIds);
        
        // Traccia i nuovi libri mostrati
        $bookIds = array_map(fn($book) => $book->getId(), $books);
        $feedHistory->markBooksAsSeen($user, $bookIds);

        // Prepara le interazioni dell'utente per ogni libro
        $userBooks = [];
        foreach ($books as $book) {
            $userBooks[$book->getId()] = $userBookRepo->findByUserAndBook($user, $book);
        }

        // Renderizza solo gli item del feed
        $html = $this->renderView('feed/_feed_items.html.twig', [
            'books' => $books,
            'userBooks' => $userBooks,
        ]);

        return new JsonResponse([
            'html' => $html,
            'hasMore' => count($books) === 30,
        ]);
    }
}
