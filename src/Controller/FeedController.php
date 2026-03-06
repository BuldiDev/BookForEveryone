<?php

namespace App\Controller;

use App\Repository\BookRepository;
use App\Repository\UserBookRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class FeedController extends AbstractController
{
    #[Route('/', name: 'app_feed')]
    public function index(
        Request $request,
        BookRepository $bookRepo,
        UserBookRepository $userBookRepo,
    ): Response {
        $user = $this->getUser();

        // Redirect all'onboarding se non ha scelto i generi
        if (!$user->isOnboardingCompleted()) {
            return $this->redirectToRoute('app_onboarding');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $books = $bookRepo->getFeedForUser($user, $page);

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
}
