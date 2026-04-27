<?php

namespace App\Command;

use App\Repository\BookRepository;
use App\Repository\UserRepository;
use App\Repository\UserBookRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Comando Symfony CLI per assegnare like casuali ai libri.
 *
 * Esecuzione: php bin/console app:populate-likes [percentage]
 *
 * Per ogni utente seleziona casualmente una percentuale dei libri disponibili
 * e imposta liked=true sul record UserBook corrispondente (creandolo se assente).
 * La percentuale è un valore medio: ogni utente può variare del ±30% rispetto al target.
 * Usato per arricchire i dati di test con contatori di like realistici.
 */
#[AsCommand(
    name: 'app:populate-likes',
    description: 'Aggiunge like casuali ai libri da parte degli utenti',
)]
class PopulateLikesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private BookRepository $bookRepo,
        private UserBookRepository $userBookRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('percentage', InputArgument::OPTIONAL, 'Percentuale media di like per utente (0-100)', 15);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $percentage = min(100, max(0, (int) $input->getArgument('percentage')));

        $io->title("Popolamento like casuali (media {$percentage}% libri per utente)");

        $users = $this->userRepo->findAll();
        $books = $this->bookRepo->findAll();

        if (empty($users)) {
            $io->error('Nessun utente trovato!');
            return Command::FAILURE;
        }

        if (empty($books)) {
            $io->error('Nessun libro trovato!');
            return Command::FAILURE;
        }

        $io->info(sprintf('Trovati %d utenti e %d libri', count($users), count($books)));

        $totalLikes = 0;
        $batchCount = 0;
        $io->progressStart(count($users));

        foreach ($users as $user) {
            // Il numero di like per utente varia del ±30% attorno alla media target
            $baseCount = (int) (count($books) * $percentage / 100);
            $variation = (int) ($baseCount * 0.3);
            $likesCount = max(0, $baseCount + random_int(-$variation, $variation));

            // Mescola i libri e seleziona i primi N per simulare scelte casuali
            $booksCopy = $books;
            shuffle($booksCopy);
            $booksToLike = array_slice($booksCopy, 0, $likesCount);

            foreach ($booksToLike as $book) {
                // findOrCreate garantisce che il record UserBook esista prima di impostare liked
                $userBook = $this->userBookRepo->findOneBy(['user' => $user, 'book' => $book]);

                if (!$userBook) {
                    $userBook = $this->userBookRepo->findOrCreate($user, $book);
                }

                // Evita di sovrascrivere like già esistenti
                if (!$userBook->isLiked()) {
                    $userBook->setLiked(true);
                    $this->em->persist($userBook);
                    $totalLikes++;
                }
            }

            // Flush a batch di 100 utenti; senza clear per mantenere le entità in memoria
            $batchCount++;
            if ($batchCount % 100 === 0) {
                $this->em->flush();
            }

            $io->progressAdvance();
        }

        // Flush finale
        $this->em->flush();
        $io->progressFinish();

        $io->success("✅ Creati {$totalLikes} like casuali!");

        return Command::SUCCESS;
    }
}
