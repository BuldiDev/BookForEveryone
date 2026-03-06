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

        // Prendi tutti gli utenti per assegnare casualmente i libri
        $users = $this->userRepo->findAll();
        if (empty($users)) {
            $io->error('Nessun utente trovato! Crea prima degli utenti.');
            return Command::FAILURE;
        }

        $io->info(sprintf('Trovati %d utenti per assegnare i libri', count($users)));

        // Subjects da cui prendere libri
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

                    // Estrai dati del libro
                    $title = $work['title'] ?? null;
                    $authors = $work['authors'][0]['name'] ?? 'Unknown';
                    $firstPublishYear = $work['first_publish_year'] ?? null;

                    if (!$title) {
                        continue;
                    }

                    // Prendi dettagli del work per descrizione e ISBN
                    $isbn = null;
                    $description = null;
                    $pageCount = null;

                    if (isset($work['key'])) {
                        try {
                            $workResponse = $this->httpClient->request('GET', "https://openlibrary.org{$work['key']}.json", [
                                'timeout' => 10,
                            ]);
                            $workData = $workResponse->toArray();

                            // Descrizione
                            if (isset($workData['description'])) {
                                $description = is_array($workData['description'])
                                    ? ($workData['description']['value'] ?? '')
                                    : $workData['description'];
                            }

                            // Cerca un'edizione con ISBN
                            if (isset($workData['covers'][0])) {
                                $coverId = $workData['covers'][0];
                            }
                        } catch (\Exception $e) {
                            // Ignora errori nel recupero dettagli
                        }
                    }

                    // Genera ISBN fittizio se non trovato (per evitare duplicati)
                    if (!$isbn) {
                        $isbn = '978' . str_pad((string)rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);
                    }

                    // Verifica che non esista già
                    if ($this->bookRepo->findByIsbn($isbn)) {
                        $skipped++;
                        continue;
                    }

                    // Crea il libro
                    $book = new Book();
                    $book->setIsbn($isbn);
                    $book->setTitle(mb_substr($title, 0, 255));
                    $book->setBookAuthor(mb_substr($authors, 0, 255));
                    $book->setDescription($description ? mb_substr($description, 0, 2000) : null);
                    $book->setPublicationYear($firstPublishYear);
                    $book->setPageCount($pageCount);

                    // Download cover image from Open Library
                    if (isset($work['cover_id'])) {
                        try {
                            $coverUrl = "https://covers.openlibrary.org/b/id/{$work['cover_id']}-L.jpg";
                            $coverResponse = $this->httpClient->request('GET', $coverUrl, ['timeout' => 10]);
                            
                            if ($coverResponse->getStatusCode() === 200) {
                                $imageContent = $coverResponse->getContent();
                                
                                // Create uploads directory if not exists
                                $uploadsDir = __DIR__ . '/../../public/uploads/books';
                                if (!is_dir($uploadsDir)) {
                                    mkdir($uploadsDir, 0755, true);
                                }
                                
                                // Generate unique filename
                                $filename = 'book_' . uniqid() . '_' . $work['cover_id'] . '.jpg';
                                $filepath = $uploadsDir . '/' . $filename;
                                
                                // Save image
                                file_put_contents($filepath, $imageContent);
                                
                                // Set relative path for database
                                $book->setCoverUrl('/uploads/books/' . $filename);
                            }
                        } catch (\Exception $e) {
                            // Se download fallisce, ignora e continua senza copertina
                        }
                    }

                    // Assegna a un utente casuale
                    $randomUser = $users[array_rand($users)];
                    $book->setAddedBy($randomUser);

                    $this->em->persist($book);
                    $created++;

                    // Flush ogni 50 libri per performance
                    if ($created % 50 === 0) {
                        $this->em->flush();
                        $io->progressAdvance(50);
                        
                        // Pausa per evitare rate limiting
                        usleep(500000); // 0.5 secondi
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
