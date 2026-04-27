<?php

namespace App\Command;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Comando Symfony CLI per popolare il database con libri reali da Open Library.
 *
 * Esecuzione: php bin/console app:populate-books [count]
 *
 * Scarica opere da vari subject letterari tramite l'API pubblica di Open Library,
 * effettua una seconda richiesta per ogni opera per recuperare descrizione e copertina,
 * e persiste i record Book nel database. Libri già presenti (per ISBN) vengono saltati.
 * Utilizzato esclusivamente per la generazione di dati di test e sviluppo.
 */
#[AsCommand(
    name: 'app:populate-books',
    description: 'Popola il database con libri casuali da Open Library API',
)]
class PopulateBooksCommand extends Command
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private BookRepository $bookRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('count', InputArgument::OPTIONAL, 'Numero di libri da creare', 2000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $targetCount = (int) $input->getArgument('count');

        $io->title("Popolamento database con {$targetCount} libri da Open Library");

        // Carica tutti gli utenti per assegnare casualmente addedBy a ogni libro
        $users = $this->userRepo->findAll();
        if (empty($users)) {
            $io->error('Nessun utente trovato! Crea prima degli utenti.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Trovati %d utenti per assegnare i libri', count($users)));

        // Generi letterari da interrogare su Open Library per una varietà di titoli
        $subjects = [
            'science_fiction', 'fantasy', 'romance', 'thriller', 'mystery',
            'horror', 'biography', 'history', 'science', 'philosophy',
            'psychology', 'business', 'art', 'cooking', 'travel',
            'poetry', 'drama', 'comics', 'children', 'young_adult',
            'classic', 'adventure', 'crime', 'war', 'politics'
        ];

        $created = 0;
        $skipped = 0;
        $errors = 0;

        $io->progressStart($targetCount);

        foreach ($subjects as $subject) {
            if ($created >= $targetCount) {
                break;
            }

            try {
                // Richiesta a Open Library per subject
                $response = $this->httpClient->request('GET', "https://openlibrary.org/subjects/{$subject}.json", [
                    'query' => ['limit' => 100],
                    'timeout' => 15,
                ]);

                $data = $response->toArray();
                $works = $data['works'] ?? [];

                foreach ($works as $work) {
                    if ($created >= $targetCount) {
                        break 2;
                    }

                    // Estrazione campi base dalla risposta subjects
                    $title = $work['title'] ?? null;
                    $authors = $work['authors'][0]['name'] ?? 'Unknown';
                    $firstPublishYear = $work['first_publish_year'] ?? null;

                    if (!$title) {
                        continue;
                    }

                    // Seconda richiesta al dettaglio del work per descrizione e copertina
                    $isbn = null;
                    $description = null;
                    $pageCount = null;

                    if (isset($work['key'])) {
                        try {
                            $workResponse = $this->httpClient->request('GET', "https://openlibrary.org{$work['key']}.json", [
                                'timeout' => 10,
                            ]);
                            $workData = $workResponse->toArray();

                            // La descrizione può essere stringa o oggetto {value:...} a seconda della versione API
                            if (isset($workData['description'])) {
                                $description = is_array($workData['description'])
                                    ? ($workData['description']['value'] ?? '')
                                    : $workData['description'];
                            }

                            if (isset($workData['covers'][0])) {
                                $coverId = $workData['covers'][0];
                            }
                        } catch (\Exception $e) {
                            // Errori di rete nel recupero dettagli non bloccano l'elaborazione
                        }
                    }

                    // Se l'API non restituisce un ISBN reale, si genera uno fittizio univoco
                    if (!$isbn) {
                        $isbn = '978' . str_pad((string)rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
                    }

                    // Salta libri già presenti nel database per evitare duplicati
                    if ($this->bookRepo->findByIsbn($isbn)) {
                        $skipped++;
                        continue;
                    }

                    $book = new Book();
                    $book->setIsbn($isbn);
                    $book->setTitle(mb_substr($title, 0, 255));
                    $book->setBookAuthor(mb_substr($authors, 0, 255));
                    $book->setDescription($description ? mb_substr($description, 0, 2000) : null);
                    $book->setPublicationYear($firstPublishYear);
                    $book->setPageCount($pageCount);

                    // Scarica e salva la copertina del libro in public/uploads/books/
                    if (isset($work['cover_id'])) {
                        try {
                            $coverUrl = "https://covers.openlibrary.org/b/id/{$work['cover_id']}-L.jpg";
                            $coverResponse = $this->httpClient->request('GET', $coverUrl, ['timeout' => 10]);

                            if ($coverResponse->getStatusCode() === 200) {
                                $imageContent = $coverResponse->getContent();

                                $uploadsDir = __DIR__ . '/../../public/uploads/books';
                                if (!is_dir($uploadsDir)) {
                                    mkdir($uploadsDir, 0755, true);
                                }

                                $filename = 'book_' . uniqid() . '_' . $work['cover_id'] . '.jpg';
                                $filepath = $uploadsDir . '/' . $filename;

                                file_put_contents($filepath, $imageContent);
                                $book->setCoverUrl('/uploads/books/' . $filename);
                            }
                        } catch (\Exception $e) {
                            // Il download della copertina è facoltativo: si prosegue senza
                        }
                    }

                    // Attribuisce il libro a un utente casuale come autore del caricamento
                    $randomUser = $users[array_rand($users)];
                    $book->setAddedBy($randomUser);

                    $this->em->persist($book);
                    $created++;

                    // Flush periodico per evitare accumulo eccessivo in memoria
                    if ($created % 50 === 0) {
                        $this->em->flush();
                        $io->progressAdvance(50);

                        // Breve pausa per rispettare i rate limit dell'API Open Library
                        usleep(500000);
                    }
                }
            } catch (\Exception $e) {
                $io->warning("Errore nel subject {$subject}: " . $e->getMessage());
                $errors++;
                continue;
            }
        }

        // Flush finale
        $this->em->flush();
        $io->progressFinish();

        $io->success([
            "Popolamento completato!",
            "Libri creati: {$created}",
            "Libri saltati (duplicati): {$skipped}",
            "Errori: {$errors}",
        ]);

        return Command::SUCCESS;
    }
}
