<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\BookPhoto;
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
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BookController extends AbstractController
{
    #[Route('/book/fetch-isbn/{isbn}', name: 'app_book_fetch_isbn', methods: ['GET'])]
    public function fetchIsbn(
        string $isbn,
        HttpClientInterface $httpClient,
        BookRepository $bookRepo,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Verifica se esiste già nel DB
        $existing = $bookRepo->findByIsbn($isbn);
        if ($existing) {
            return new JsonResponse([
                'exists' => true,
                'bookId' => $existing->getId(),
                'addedBy' => $existing->getAddedBy()->getDisplayName(),
            ]);
        }

        $bookData = $this->fetchBookDataFromApis($isbn, $httpClient);
        
        if (!$bookData) {
            return new JsonResponse([
                'error' => 'ISBN non trovato. Verifica che sia corretto.',
            ], 200);
        }

        return new JsonResponse(array_merge(['exists' => false], $bookData));
    }

    private function fetchBookDataFromApis(string $isbn, HttpClientInterface $httpClient): ?array
    {
        // Prova prima Google Books API
        $bookData = null;
        
        try {
            $response = $httpClient->request('GET', 'https://www.googleapis.com/books/v1/volumes', [
                'query' => ['q' => 'isbn:' . $isbn],
                'timeout' => 10,
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode === 200) {
                $data = $response->toArray();
                
                if (!empty($data['items'])) {
                    $volumeInfo = $data['items'][0]['volumeInfo'] ?? [];
                    $bookData = [
                        'title' => $volumeInfo['title'] ?? '',
                        'authors' => $volumeInfo['authors'][0] ?? '',
                        'description' => $volumeInfo['description'] ?? '',
                        'pageCount' => $volumeInfo['pageCount'] ?? null,
                        'publisher' => $volumeInfo['publisher'] ?? '',
                        'publishedDate' => isset($volumeInfo['publishedDate']) ? (int) substr($volumeInfo['publishedDate'], 0, 4) : null,
                        'coverUrl' => $volumeInfo['imageLinks']['thumbnail'] ?? null,
                        'categories' => $volumeInfo['categories'] ?? [],
                    ];
                }
            }
        } catch (\Exception $e) {
            // Google Books fallito, proveremo Open Library
        }

        // Se Google Books non ha funzionato, prova Open Library API
        if (!$bookData) {
            try {
                $response = $httpClient->request('GET', "https://openlibrary.org/isbn/{$isbn}.json", [
                    'timeout' => 10,
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();
                    
                    // Ottieni anche i dettagli del work per la descrizione
                    $description = '';
                    if (isset($data['works'][0]['key'])) {
                        try {
                            $workResponse = $httpClient->request('GET', "https://openlibrary.org{$data['works'][0]['key']}.json", [
                                'timeout' => 5,
                            ]);
                            $workData = $workResponse->toArray();
                            $description = is_array($workData['description'] ?? null) 
                                ? ($workData['description']['value'] ?? '') 
                                : ($workData['description'] ?? '');
                        } catch (\Exception $e) {
                            // Ignora errori nella richiesta del work
                        }
                    }
                    
                    // Ottieni i nomi degli autori
                    $authorName = '';
                    if (!empty($data['authors'])) {
                        try {
                            $authorKey = $data['authors'][0]['key'];
                            $authorResponse = $httpClient->request('GET', "https://openlibrary.org{$authorKey}.json", [
                                'timeout' => 5,
                            ]);
                            $authorData = $authorResponse->toArray();
                            $authorName = $authorData['name'] ?? '';
                        } catch (\Exception $e) {
                            // Ignora errori nella richiesta dell'autore
                        }
                    }
                    
                    $bookData = [
                        'title' => $data['title'] ?? '',
                        'authors' => $authorName,
                        'description' => $description,
                        'pageCount' => $data['number_of_pages'] ?? null,
                        'publisher' => $data['publishers'][0] ?? '',
                        'publishedDate' => isset($data['publish_date']) ? (int) substr($data['publish_date'], -4) : null,
                        'coverUrl' => isset($data['covers'][0]) ? "https://covers.openlibrary.org/b/id/{$data['covers'][0]}-M.jpg" : null,
                        'categories' => $data['subjects'] ?? [],
                    ];
                }
            } catch (\Exception $e) {
                // Anche Open Library è fallito
            }
        }

        return $bookData;
    }

    #[Route('/book/add', name: 'app_book_add')]
    public function add(
        Request $request,
        BookRepository $bookRepo,
        EntityManagerInterface $em,
        BookPhotoUploader $uploader,
        HttpClientInterface $httpClient,
    ): Response {
        $book = new Book();
        $form = $this->createForm(BookFormType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Controlla se esiste già per ISBN (obbligatorio)
            $existing = $bookRepo->findByIsbn($book->getIsbn());
            if ($existing) {
                $this->addFlash('error', 'Questo libro è già stato aggiunto da ' . $existing->getAddedBy()->getDisplayName() . '!');
                return $this->redirectToRoute('app_book_show', ['id' => $existing->getId()]);
            }

            // Ri-ottieni i dati dall'API per garantire l'integrità
            $apiData = $this->fetchBookDataFromApis($book->getIsbn(), $httpClient);
            if ($apiData) {
                $book->setTitle($apiData['title']);
                $book->setAuthor($apiData['authors']);
                $book->setDescription($apiData['description']);
                $book->setPageCount($apiData['pageCount']);
                $book->setPublisher($apiData['publisher']);
                $book->setPublicationYear($apiData['publishedDate']);
            }

            // Upload copertina ufficiale
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                $coverPath = $uploader->upload($coverFile);
                $book->setCoverUrl($coverPath);
            }

            $book->setAddedBy($this->getUser());
            $em->persist($book);
            $em->flush();

            // Upload foto utente
            $userPhotos = $form->get('userPhotos')->getData();
            if ($userPhotos) {
                $position = 0;
                foreach ($userPhotos as $photoFile) {
                    $photoPath = $uploader->upload($photoFile);
                    $bookPhoto = new BookPhoto();
                    $bookPhoto->setImageUrl($photoPath);
                    $bookPhoto->setBook($book);
                    $bookPhoto->setUploadedBy($this->getUser());
                    $bookPhoto->setPosition($position++);
                    $em->persist($bookPhoto);
                }
                $em->flush();
            }

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
            'likesCount' => $book->getLikesCount(),
        ]);
    }
}
