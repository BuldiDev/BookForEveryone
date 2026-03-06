<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\UserBook;
use App\Form\BookFormType;
use App\Repository\BookRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserBookRepository;
use App\Service\BookPhotoUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class BookController extends AbstractController
{
    #[Route('/book/add', name: 'app_book_add')]
    public function add(
        Request $request,
        BookRepository $bookRepo,
        EntityManagerInterface $em,
        BookPhotoUploader $uploader,
    ): Response {
        $book = new Book();
        $form = $this->createForm(BookFormType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Controlla se esiste già per ISBN
            if ($book->getIsbn()) {
                $existing = $bookRepo->findByIsbn($book->getIsbn());
                if ($existing) {
                    $this->addFlash('success', 'Libro già presente! Aggiunto alla tua libreria.');
                    return $this->redirectToRoute('app_book_show', ['id' => $existing->getId()]);
                }
            }

            // Upload copertina
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                $coverPath = $uploader->upload($coverFile);
                $book->setCoverUrl($coverPath);
            }

            $book->setAddedBy($this->getUser());
            $em->persist($book);
            $em->flush();

            $this->addFlash('success', 'Libro aggiunto!');

            return $this->redirectToRoute('app_book_show', ['id' => $book->getId()]);
        }

        return $this->render('book/add.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/book/{id}', name: 'app_book_show', requirements: ['id' => '\d+'])]
    public function show(
        Book $book,
        ReviewRepository $reviewRepo,
        UserBookRepository $userBookRepo,
    ): Response {
        $userBook = null;
        if ($this->getUser()) {
            $userBook = $userBookRepo->findByUserAndBook($this->getUser(), $book);
        }

        return $this->render('book/show.html.twig', [
            'book' => $book,
            'reviews' => $reviewRepo->getReviewsForBook($book),
            'userBook' => $userBook,
        ]);
    }

    #[Route('/book/{id}/action/{action}', name: 'app_book_action', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function action(
        Book $book,
        string $action,
        UserBookRepository $userBookRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user = $this->getUser();
        $userBook = $userBookRepo->findOrCreate($user, $book);

        match ($action) {
            'like' => $userBook->toggleLike(),
            'read' => $userBook->toggleHasRead(),
            'own' => $userBook->toggleOwnsPhysically(),
            default => null,
        };

        $em->persist($userBook);
        $em->flush();

        return new JsonResponse([
            'liked' => $userBook->isLiked(),
            'hasRead' => $userBook->hasRead(),
            'ownsPhysically' => $userBook->ownsPhysically(),
        ]);
    }
}
