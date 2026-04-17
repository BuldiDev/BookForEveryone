<?php

namespace App\Command;

use App\Entity\UserBook;
use App\Repository\BookRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:distribute-books',
    description: 'Distribuisce i libri esistenti a molti utenti casuali',
)]
class DistributeBooksCommand extends Command
{
    public function __construct(
        private BookRepository $bookRepo,
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('copies-per-book', 'c', InputOption::VALUE_OPTIONAL, 'Numero di copie per libro', 25);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $copiesPerBook = (int) $input->getOption('copies-per-book');

        $books = $this->bookRepo->findAll();
        $users = $this->userRepo->findAll();

        if (empty($books)) {
            $io->error('Nessun libro trovato!');
            return Command::FAILURE;
        }

        if (empty($users)) {
            $io->error('Nessun utente trovato!');
            return Command::FAILURE;
        }

        $io->title(sprintf('Distribuzione di %d libri a %d utenti (%d copie per libro)', count($books), count($users), $copiesPerBook));

        // Prima, elimina tutte le copie esistenti per ricominciare da zero
        $io->section('Pulizia copie esistenti...');
        $this->em->createQuery('DELETE FROM App\Entity\UserBook')->execute();
        $io->success('Pulizia completata');

        $io->section('Creazione nuove distribuzioni...');
        $io->progressStart(count($books) * $copiesPerBook);

        $totalCreated = 0;
        $batchSize = 100;

        foreach ($books as $book) {
            // Crea N copie di questo libro per utenti casuali
            $assignedUsers = [];
            
            for ($i = 0; $i < $copiesPerBook; $i++) {
                // Scegli un utente casuale non ancora assegnato per questo libro
                $maxAttempts = 50;
                $attempt = 0;
                do {
                    $randomUser = $users[array_rand($users)];
                    $userId = $randomUser->getId();
                    $attempt++;
                } while (in_array($userId, $assignedUsers) && $attempt < $maxAttempts);

                // Se dopo 50 tentativi troviamo sempre lo stesso utente, permetti duplicati
                $assignedUsers[] = $userId;

                $userBook = new UserBook();
                $userBook->setUser($randomUser);
                $userBook->setBook($book);
                $userBook->setOwnsPhysically(true);

                $this->em->persist($userBook);
                $totalCreated++;

                if ($totalCreated % $batchSize === 0) {
                    $this->em->flush();
                }

                $io->progressAdvance();
            }
        }

        $this->em->flush();
        $io->progressFinish();

        $io->success(sprintf('Distribuite %d copie di libri tra gli utenti!', $totalCreated));

        return Command::SUCCESS;
    }
}
