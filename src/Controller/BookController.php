<?php

namespace App\Controller;

use App\Entity\Book;
use App\Entity\BookPhoto;
use App\Entity\Review;
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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Controller che gestisce tutto ciò che riguarda i libri:
 * aggiunta, visualizzazione, azioni rapide (like/letto/possiedo) e recensioni.
 */
class BookController extends AbstractController
{
    /**
     * Endpoint AJAX per la ricerca automatica dei dati di un libro tramite ISBN.
     * Prima controlla il database locale, poi interroga Google Books e Open Library.
     * Il risultato viene memorizzato in cache Redis per 24 ore.
     */
    #[Route('/book/fetch-isbn/{isbn}', name: 'app_book_fetch_isbn', methods: ['GET'])]
    public function fetchIsbn(
        string $isbn,
        HttpClientInterface $httpClient,
        BookRepository $bookRepo,
        CacheInterface $cache,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Normalizza l'ISBN rimuovendo trattini e spazi (es. 978-88-... → 9788...)
        $isbn = preg_replace('/[^0-9X]/i', '', $isbn);

        // Verifica se il libro è già presente nel database
        $existing = $bookRepo->findByIsbn($isbn);
        if ($existing) {
            return new JsonResponse([
                'exists' => true,
                'bookId' => $existing->getId(),
                'addedBy' => $existing->getAddedBy()->getDisplayName(),
            ]);
        }

        // Recupera i dati dalle API esterne (con cache di 24 ore)
        $bookData = $cache->get('isbn_api_' . $isbn, function (ItemInterface $item) use ($isbn, $httpClient) {
            $item->expiresAfter(86400);
            return $this->fetchBookDataFromApis($isbn, $httpClient);
        });

        if (!$bookData) {
            return new JsonResponse([
                'error' => 'ISBN non trovato. Verifica che sia corretto.',
            ], 200);
        }

        return new JsonResponse(array_merge(['exists' => false], $bookData));
    }

    /**
     * Interroga in sequenza Google Books API e, in caso di fallimento, Open Library API.
     * Restituisce un array normalizzato con i metadati del libro, oppure null se non trovato.
     */
    private function fetchBookDataFromApis(string $isbn, HttpClientInterface $httpClient): ?array
    {
        $bookData = null;

        // --- Tentativo 1: Google Books API ---
        try {
            $response = $httpClient->request('GET', 'https://www.googleapis.com/books/v1/volumes', [
                'query' => ['q' => 'isbn:' . $isbn],
                'timeout' => 10,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();

                if (!empty($data['items'])) {
                    $volumeInfo = $data['items'][0]['volumeInfo'] ?? [];
                    $bookData = [
                        'title'         => $volumeInfo['title'] ?? '',
                        'authors'       => $volumeInfo['authors'][0] ?? '',
                        'description'   => $volumeInfo['description'] ?? '',
                        'pageCount'     => $volumeInfo['pageCount'] ?? null,
                        'publisher'     => $volumeInfo['publisher'] ?? '',
                        'publishedDate' => isset($volumeInfo['publishedDate']) ? (int) substr($volumeInfo['publishedDate'], 0, 4) : null,
                        'coverUrl'      => $volumeInfo['imageLinks']['thumbnail'] ?? null,
                        'categories'    => $volumeInfo['categories'] ?? [],
                    ];
                }
            }
        } catch (\Exception $e) {
            // Google Books non disponibile, si tenta con Open Library
        }

        // --- Tentativo 2: Open Library API (fallback) ---
        if (!$bookData) {
            try {
                $response = $httpClient->request('GET', "https://openlibrary.org/isbn/{$isbn}.json", [
                    'timeout' => 10,
                ]);

                if ($response->getStatusCode() === 200) {
                    $data = $response->toArray();

                    // Recupera la descrizione dal record "work" associato al volume
                    $description = '';
                    if (isset($data['works'][0]['key'])) {
                        try {
                            $workResponse = $httpClient->request('GET', "https://openlibrary.org{$data['works'][0]['key']}.json", [
                                'timeout' => 5,
                            ]);
                            $workData    = $workResponse->toArray();
                            $description = is_array($workData['description'] ?? null)
                                ? ($workData['description']['value'] ?? '')
                                : ($workData['description'] ?? '');
                        } catch (\Exception $e) {
                            // La descrizione è opzionale, si prosegue senza
                        }
                    }

                    // Recupera il nome dell'autore dalla chiave autore di Open Library
                    $authorName = '';
                    if (!empty($data['authors'])) {
                        try {
                            $authorKey      = $data['authors'][0]['key'];
                            $authorResponse = $httpClient->request('GET', "https://openlibrary.org{$authorKey}.json", [
                                'timeout' => 5,
                            ]);
                            $authorData = $authorResponse->toArray();
                            $authorName = $authorData['name'] ?? '';
                        } catch (\Exception $e) {
                            // Nome autore non disponibile
                        }
                    }

                    $bookData = [
                        'title'         => $data['title'] ?? '',
                        'authors'       => $authorName,
                        'description'   => $description,
                        'pageCount'     => $data['number_of_pages'] ?? null,
                        'publisher'     => $data['publishers'][0] ?? '',
                        'publishedDate' => isset($data['publish_date']) ? (int) substr($data['publish_date'], -4) : null,
                        'coverUrl'      => isset($data['covers'][0]) ? "https://covers.openlibrary.org/b/id/{$data['covers'][0]}-M.jpg" : null,
                        'categories'    => $data['subjects'] ?? [],
                    ];
                }
            } catch (\Exception $e) {
                // Anche Open Library non ha trovato il libro
            }
        }

        return $bookData;
    }

    /**
     * Mostra il modulo di aggiunta libro e ne gestisce l'invio.
     * Al submit verifica i duplicati per ISBN, ri-scarica i metadati dall'API
     * (per garantire l'integrità dei dati) e salva copertina e foto dell'utente.
     */
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
            // Impedisce duplicati: se l'ISBN è già nel DB si reindirizza alla scheda esistente
            $existing = $bookRepo->findByIsbn($book->getIsbn());
            if ($existing) {
                $this->addFlash('error', 'Questo libro è già stato aggiunto da ' . $existing->getAddedBy()->getDisplayName() . '!');
                return $this->redirectToRoute('app_book_show', ['id' => $existing->getId()]);
            }

            // Ri-scarica i metadati dall'API per evitare dati manomessi nel form
            $apiData = $this->fetchBookDataFromApis($book->getIsbn(), $httpClient);
            if ($apiData) {
                $book->setTitle($apiData['title']);
                $book->setBookAuthor($apiData['authors']);
                $book->setDescription($apiData['description']);
                $book->setPageCount($apiData['pageCount']);
                $book->setPublisher($apiData['publisher']);
                $book->setPublicationYear($apiData['publishedDate']);
            }

            // Carica la copertina ufficiale se fornita dall'utente
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                $coverPath = $uploader->upload($coverFile);
                $book->setCoverUrl($coverPath);
            }

            // Assegna l'utente che ha aggiunto il libro e premia con 10 Culture Points
            $book->setAddedBy($this->getUser());
            $this->getUser()->addCulturePoints(10);
            $em->persist($book);
            $em->flush();

            // Salva le eventuali foto personali del libro caricate dall'utente
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

    /**
     * Mostra la scheda dettaglio di un libro con recensioni e stato dell'utente corrente.
     */
    #[Route('/book/{id}', name: 'app_book_show', requirements: ['id' => '\d+'])]
    public function show(
        Book $book,
        ReviewRepository $reviewRepo,
        UserBookRepository $userBookRepo,
    ): Response {
        // Recupera l'interazione dell'utente con questo libro (like, letto, possiedo)
        $userBook = null;
        if ($this->getUser()) {
            $userBook = $userBookRepo->findByUserAndBook($this->getUser(), $book);
        }

        return $this->render('book/show.html.twig', [
            'book'     => $book,
            'reviews'  => $reviewRepo->getReviewsForBook($book),
            'userBook' => $userBook,
        ]);
    }

    /**
     * Gestisce le azioni rapide sul libro: like, segna come letto, indica che lo possiedi.
     * Risponde in JSON per essere chiamato via fetch() dal frontend.
     */
    #[Route('/book/{id}/action/{action}', name: 'app_book_action', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function action(
        Book $book,
        string $action,
        UserBookRepository $userBookRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $user     = $this->getUser();
        // Recupera o crea il record di interazione utente-libro
        $userBook = $userBookRepo->findOrCreate($user, $book);

        // Esegui l'azione richiesta in modo toggle (on/off)
        match ($action) {
            'like' => $userBook->toggleLike(),
            'read' => $userBook->toggleHasRead(),
            'own'  => $userBook->toggleOwnsPhysically(),
            default => null,
        };

        $em->persist($userBook);
        $em->flush();

        // Restituisce lo stato aggiornato al frontend
        return new JsonResponse([
            'liked'          => $userBook->isLiked(),
            'hasRead'        => $userBook->hasRead(),
            'ownsPhysically' => $userBook->ownsPhysically(),
            'likesCount'     => $book->getLikesCount(),
        ]);
    }

    /**
     * Restituisce in JSON le recensioni di un libro (usato dal pannello espandibile nel feed).
     */
    #[Route('/book/{id}/reviews', name: 'app_book_reviews', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function reviews(Book $book, ReviewRepository $reviewRepo): JsonResponse
    {
        $reviews = $reviewRepo->getReviewsForBook($book, 30);

        return new JsonResponse([
            'avgRating'    => $book->getAverageRating(),
            'reviewsCount' => $book->getReviewsCount(),
            'reviews'      => array_map(static fn($r) => [
                'username'    => $r->getUser()->getUsername(),
                'displayName' => $r->getUser()->getDisplayName(),
                'rating'      => $r->getRating(),
                'content'     => $r->getContent(),
            ], $reviews),
        ]);
    }

    /**
     * Riceve e salva (o aggiorna) la recensione di un utente su un libro.
     * Se l'utente ha già recensito lo stesso libro, sovrascrive il valore precedente.
     */
    #[Route('/book/{id}/review', name: 'app_book_review', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function review(
        Book $book,
        Request $request,
        ReviewRepository $reviewRepo,
        EntityManagerInterface $em,
    ): JsonResponse {
        $this->denyAccessUnlessGranted('ROLE_USER');

        // Valida il voto: deve essere un intero tra 1 e 5
        $rating = (int) $request->request->get('rating', 0);
        if ($rating < 1 || $rating > 5) {
            return new JsonResponse(['error' => 'Rating non valido'], 400);
        }

        $user    = $this->getUser();
        $content = trim((string) $request->request->get('content', ''));

        // Cerca una recensione esistente per aggiornare invece di duplicare
        $review = $reviewRepo->findByUserAndBook($user, $book);
        if (!$review) {
            $review = new Review();
            $review->setUser($user);
            $review->setBook($book);
        }

        $review->setRating($rating);
        $review->setContent($content !== '' ? $content : null);

        $em->persist($review);
        $em->flush();

        return new JsonResponse([
            'rating'       => $review->getRating(),
            'avgRating'    => $book->getAverageRating(),
            'reviewsCount' => $book->getReviewsCount(),
        ]);
    }
}
