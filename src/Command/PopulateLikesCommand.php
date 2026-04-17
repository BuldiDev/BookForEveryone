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

        // Prendi tutti gli utenti e tutti i libri
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
        $io->progressStart(count($users));

        foreach ($users as $user) {
            // Determina quanti like questo utente metterà (variazione casuale attorno alla media)
            $baseCount = (int) (count($books) * $percentage / 100);
            $variation = (int) ($baseCount * 0.3); // Variazione del 30%
            $likesCount = max(0, $baseCount + random_int(-$variation, $variation));

            // Seleziona libri casuali
            $booksCopy = $books;
            shuffle($booksCopy);
            $booksToLike = array_slice($booksCopy, 0, $likesCount);

            foreach ($booksToLike as $book) {
                // Trova o crea UserBook
                $userBook = $this->userBookRepo->findOneBy(['user' => $user, 'book' => $book]);
                
                if (!$userBook) {
                    $userBook = $this->userBookRepo->findOrCreate($user, $book);
                }

                // Metti like solo se non ce l'ha già
                if (!$userBook->isLiked()) {
                    $userBook->setLiked(true);
                    $this->em->persist($userBook);
                    $totalLikes++;
                }
            }

            // Flush ogni 100 utenti per performance
            if ($totalLikes % 1000 === 0) {
                $this->em->flush();
                $this->em->clear();
                
                // Ricarica gli oggetti per evitare problemi di memoria
                $users = $this->userRepo->findAll();
                $books = $this->bookRepo->findAll();
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
