<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserBookRepository;
use App\Repository\UserRepository;
use App\Service\FeedHistoryService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller per il feed principale dell'applicazione.
 * Gestisce sia il caricamento iniziale della pagina sia il caricamento incrementale
 * dei libri durante lo scroll infinito.
 *
 * Il feed supporta due sezioni:
 *  - "per-te": libri selezionati in base ai generi preferiti dell'utente, ordinati con seed casuale
 *  - "seguiti": libri consigliati dagli utenti che l'utente segue
 *
 * La deduplicazione (evitare di mostrare due volte lo stesso libro) è gestita da
 * FeedHistoryService tramite Redis, senza ricorrere a OFFSET SQL.
 */
class FeedController extends AbstractController
{
    /**
     * Carica la prima pagina del feed e renderizza la vista completa.
     * Reindirizza all'onboarding se l'utente non ha ancora scelto i generi preferiti.
     */
    #[Route('/', name: 'app_feed')]
    public function index(
        Request $request,
        BookRepository $bookRepo,
        UserBookRepository $userBookRepo,
        UserRepository $userRepo,
        ReviewRepository $reviewRepo,
        SessionInterface $session,
        FeedHistoryService $feedHistory,
    ): Response {
        $user = $this->getUser();

        // Primo accesso: l'utente deve scegliere i generi preferiti prima di vedere il feed
        if (!$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding');
        }

        $section = $request->query->getString('section', 'per-te');
        $page    = max(1, $request->query->getInt('page', 1));

        if ($section === 'seguiti') {
            // Recupera i libri dei soli utenti seguiti, escludendo quelli già visti
            $seenBookIds = $feedHistory->getSeenBookIds($user);
            $books       = $bookRepo->getBooksFromFollowedUsers($user, $page, 30, $seenBookIds);

            // Se la storia è esaurita, resetta e riempie con i titoli già visti
            if (count($books) < 30 && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $remaining  = 30 - count($books);
                $alreadyIds = array_map(fn($b) => $b->getId(), $books);
                $extra      = $bookRepo->getBooksFromFollowedUsers($user, 1, $remaining, $alreadyIds);
                $books      = array_merge($books, $extra);
            }
        } else {
            // Genera un seed casuale per randomizzare l'ordine del feed "per te"
            // e lo salva in sessione in modo che i batch successivi abbiano lo stesso ordine
            $seed = random_int(1, 999999);
            $session->set('feed_seed', $seed);

            $seenBookIds = $feedHistory->getSeenBookIds($user);
            $books       = $bookRepo->getFeedForUser($user, $page, 30, $seed, $seenBookIds);

            // Se i libri non visti sono terminati, azzera la storia e ricomincia
            if (count($books) < 30 && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $remaining  = 30 - count($books);
                $alreadyIds = array_map(fn($b) => $b->getId(), $books);
                $extra      = $bookRepo->getFeedForUser($user, 1, $remaining, $seed, $alreadyIds);
                $books      = array_merge($books, $extra);
            }
        }

        // Memorizza in Redis quali libri sono stati mostrati per evitare duplicati
        $bookIds = array_map(fn($book) => $book->getId(), $books);
        $feedHistory->markBooksAsSeen($user, $bookIds);

        // Carica in batch le interazioni utente, le statistiche, i following e le recensioni
        // con 3-4 query native invece di N×3 query in loop
        $userBooks    = $userBookRepo->findByUserForBooks($user, $bookIds);
        $bookStats    = $bookRepo->getBookStats($bookIds);
        $followingIds = $userRepo->getFollowingIds($user);
        $userReviews  = $reviewRepo->findByUserForBooks($user, $bookIds);

        return $this->render('feed/index.html.twig', [
            'books'        => $books,
            'userBooks'    => $userBooks,
            'bookStats'    => $bookStats,
            'followingIds' => $followingIds,
            'userReviews'  => $userReviews,
            'page'         => $page,
            'section'      => $section,
        ]);
    }

    /**
     * Carica il batch successivo di libri durante lo scroll infinito.
     * Restituisce un JSON con il frammento HTML dei nuovi elementi e un flag
     * che indica se ci sono altri libri disponibili.
     */
    #[Route('/feed/load-more', name: 'app_feed_load_more')]
    public function loadMore(
        Request $request,
        BookRepository $bookRepo,
        UserBookRepository $userBookRepo,
        UserRepository $userRepo,
        ReviewRepository $reviewRepo,
        SessionInterface $session,
        FeedHistoryService $feedHistory,
    ): Response {
        $user    = $this->getUser();
        $section = $request->query->getString('section', 'per-te');

        // La deduplicazione è gestita dai seenBookIds: non si usa OFFSET per evitare salti
        $seenBookIds = $feedHistory->getSeenBookIds($user);

        if ($section === 'seguiti') {
            $seenBookIds = $feedHistory->getSeenBookIds($user);
            $books       = $bookRepo->getBooksFromFollowedUsers($user, 1, 30, $seenBookIds);

            // Se la storia è esaurita, resetta e riempie con i libri già visti
            if (count($books) < 30 && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $remaining  = 30 - count($books);
                $alreadyIds = array_map(fn($b) => $b->getId(), $books);
                $extra      = $bookRepo->getBooksFromFollowedUsers($user, 1, $remaining, $alreadyIds);
                $books      = array_merge($books, $extra);
            }
        } else {
            // Riutilizza il seed della sessione per mantenere un ordine coerente tra i batch
            $seed        = $session->get('feed_seed', random_int(1, 999999));
            $seenBookIds = $feedHistory->getSeenBookIds($user);
            $books       = $bookRepo->getFeedForUser($user, 1, 30, $seed, $seenBookIds);

            // Se i libri non visti sono terminati, azzera la storia e ricomincia
            if (count($books) < 30 && !empty($seenBookIds)) {
                $feedHistory->resetHistory($user);
                $remaining  = 30 - count($books);
                $alreadyIds = array_map(fn($b) => $b->getId(), $books);
                $extra      = $bookRepo->getFeedForUser($user, 1, $remaining, $seed, $alreadyIds);
                $books      = array_merge($books, $extra);
            }
        }

        // Segna i nuovi libri come visti
        $bookIds = array_map(fn($book) => $book->getId(), $books);
        $feedHistory->markBooksAsSeen($user, $bookIds);

        // Batch-load delle informazioni necessarie per il rendering delle card
        $userBooks    = $userBookRepo->findByUserForBooks($user, $bookIds);
        $bookStats    = $bookRepo->getBookStats($bookIds);
        $followingIds = $userRepo->getFollowingIds($user);
        $userReviews  = $reviewRepo->findByUserForBooks($user, $bookIds);

        // Renderizza solo il frammento HTML degli elementi del feed (non la pagina intera)
        $html = $this->renderView('feed/_feed_items.html.twig', [
            'books'        => $books,
            'userBooks'    => $userBooks,
            'bookStats'    => $bookStats,
            'followingIds' => $followingIds,
            'userReviews'  => $userReviews,
        ]);

        return new JsonResponse([
            'html'    => $html,
            'hasMore' => !empty($books),
        ]);
    }
}
