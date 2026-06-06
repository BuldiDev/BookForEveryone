<?php

namespace App\Command;

use App\Entity\Review;
use App\Repository\BookRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Comando Symfony CLI per assegnare recensioni e valutazioni casuali ai libri.
 *
 * Esecuzione: php bin/console app:populate-reviews [maxPerBook]
 *
 * Per ogni libro seleziona un numero casuale di utenti distinti e crea una recensione
 * con un voto da 1 a 5 stelle e, in circa metà dei casi, un breve commento testuale.
 * Rispetta il vincolo di unicità (una sola recensione per coppia utente-libro).
 * Usato per arricchire i dati di test con valutazioni e recensioni realistiche.
 */
#[AsCommand(
    name: 'app:populate-reviews',
    description: 'Aggiunge recensioni e valutazioni casuali ai libri',
)]
class PopulateReviewsCommand extends Command
{
    /** Commenti brevi usati casualmente come testo di alcune recensioni */
    private const COMMENTS = [
        'Bello', 'Bellissimo', 'Brutto', 'Da recuperare', 'Carino', 'Consigliato',
        'Non male', 'Mediocre', 'Capolavoro', 'Mi è piaciuto molto', 'Deludente',
        'Si lascia leggere', 'Noioso', 'Da leggere assolutamente', 'Niente di che',
        'Sorprendente', 'Lento all\'inizio ma migliora', 'Lo rileggerei',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepo,
        private BookRepository $bookRepo,
        private ReviewRepository $reviewRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('maxPerBook', InputArgument::OPTIONAL, 'Numero massimo di recensioni per libro', 8);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $maxPerBook = max(1, (int) $input->getArgument('maxPerBook'));

        $io->title("Popolamento recensioni (fino a {$maxPerBook} per libro)");

        $users = $this->userRepo->findAll();
        $books = $this->bookRepo->findAll();

        if (empty($users) || empty($books)) {
            $io->error('Servono utenti e libri già presenti nel database!');
            return Command::FAILURE;
        }

        $io->info(sprintf('Trovati %d utenti e %d libri', count($users), count($books)));

        $total = 0;
        $batch = 0;
        $io->progressStart(count($books));

        foreach ($books as $book) {
            // Numero casuale di recensioni per questo libro (alcuni libri non ne ricevono)
            $count = random_int(0, $maxPerBook);

            // Sceglie recensori distinti mescolando gli utenti
            $usersCopy = $users;
            shuffle($usersCopy);
            $reviewers = array_slice($usersCopy, 0, $count);

            foreach ($reviewers as $user) {
                // Rispetta il vincolo di unicità: una sola recensione per coppia utente-libro
                if ($this->reviewRepo->findOneBy(['user' => $user, 'book' => $book])) {
                    continue;
                }

                $review = new Review();
                $review->setUser($user);
                $review->setBook($book);
                $review->setRating($this->randomRating());

                // Circa metà delle recensioni ha anche un breve commento testuale
                if (random_int(0, 1) === 1) {
                    $review->setContent(self::COMMENTS[array_rand(self::COMMENTS)]);
                }

                $this->em->persist($review);
                $total++;
            }

            // Flush periodico per non accumulare troppe entità in memoria
            $batch++;
            if ($batch % 20 === 0) {
                $this->em->flush();
            }

            $io->progressAdvance();
        }

        // Flush finale
        $this->em->flush();
        $io->progressFinish();

        $io->success("✅ Create {$total} recensioni con valutazione!");

        return Command::SUCCESS;
    }

    /**
     * Restituisce un voto casuale da 1 a 5, leggermente sbilanciato verso le
     * valutazioni positive (3, 4 e 5 più probabili) per risultare più realistico.
     */
    private function randomRating(): int
    {
        $pool = [1, 2, 3, 3, 4, 4, 4, 5, 5];

        return $pool[array_rand($pool)];
    }
}
