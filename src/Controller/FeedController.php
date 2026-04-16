<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\UserBookRepository;
use App\Repository\UserRepository;
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
        UserRepository $userRepo,
        SessionInterface $session,
        FeedHistoryService $feedHistory,
    ): Response {
        $user = $this->getUser();

        // Redirect all'onboarding se non ha scelto i generi
        if (!$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding');
        }

        $section = $request->query->getString('section', 'per-te');
        $page = max(1, $request->query->getInt('page', 1));

        if ($section === 'seguiti') {
            $seenBookIds = $feedHistory->getSeenBookIds($user);
            $books = $bookRepo->getBooksFromFollowedUsers($user, $page, 30, $seenBookIds);

            if (empty($books) && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $books = $bookRepo->getBooksFromFollowedUsers($user, $page, 30);
            }
        } else {
            // Genera o recupera il seed dalla sessione (si rigenera ogni volta che visiti il feed)
            $seed = random_int(1, 999999);
            $session->set('feed_seed', $seed);

            $seenBookIds = $feedHistory->getSeenBookIds($user);
            $books = $bookRepo->getFeedForUser($user, $page, 30, $seed, $seenBookIds);

            if (empty($books) && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $books = $bookRepo->getFeedForUser($user, $page, 30, $seed);
            }
        }

        // Traccia i libri mostrati
        $bookIds = array_map(fn($book) => $book->getId(), $books);
        $feedHistory->markBooksAsSeen($user, $bookIds);

        // Batch-load interazioni utente e stats (3 query native invece di 180+)
        $userBooks = $userBookRepo->findByUserForBooks($user, $bookIds);
        $bookStats = $bookRepo->getBookStats($bookIds);
        $followingIds = $userRepo->getFollowingIds($user);

        return $this->render('feed/index.html.twig', [
            'books' => $books,
            'userBooks' => $userBooks,
            'bookStats' => $bookStats,
            'followingIds' => $followingIds,
            'page' => $page,
            'section' => $section,
        ]);
    }

    #[Route('/feed/load-more', name: 'app_feed_load_more')]
    public function loadMore(
        Request $request,
        BookRepository $bookRepo,
        UserBookRepository $userBookRepo,
        UserRepository $userRepo,
        SessionInterface $session,
        FeedHistoryService $feedHistory,
    ): Response {
        $user = $this->getUser();
        $section = $request->query->getString('section', 'per-te');

        // Sempre page=1: la deduplicazione è gestita dai seenBookIds (no doppio-skip con OFFSET)
        $seenBookIds = $feedHistory->getSeenBookIds($user);

        if ($section === 'seguiti') {
            $books = $bookRepo->getBooksFromFollowedUsers($user, 1, 30, $seenBookIds);

            if (empty($books) && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $books = $bookRepo->getBooksFromFollowedUsers($user, 1, 30);
            }
        } else {
            // Usa lo stesso seed della sessione per mantenere l'ordine consistente
            $seed = $session->get('feed_seed', random_int(1, 999999));

            $books = $bookRepo->getFeedForUser($user, 1, 30, $seed, $seenBookIds);

            if (empty($books) && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $books = $bookRepo->getFeedForUser($user, 1, 30, $seed);
            }
        }

        // Traccia i nuovi libri mostrati
        $bookIds = array_map(fn($book) => $book->getId(), $books);
        $feedHistory->markBooksAsSeen($user, $bookIds);

        // Batch-load interazioni utente e stats (3 query native invece di 180+)
        $userBooks = $userBookRepo->findByUserForBooks($user, $bookIds);
        $bookStats = $bookRepo->getBookStats($bookIds);
        $followingIds = $userRepo->getFollowingIds($user);

        // Renderizza solo gli item del feed
        $html = $this->renderView('feed/_feed_items.html.twig', [
            'books' => $books,
            'userBooks' => $userBooks,
            'bookStats' => $bookStats,
            'followingIds' => $followingIds,
        ]);

        return new JsonResponse([
            'html' => $html,
            'hasMore' => count($books) === 30,
        ]);
    }
}
