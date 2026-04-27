<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Comando Symfony CLI per concentrare tutti gli utenti intorno a Mazara del Vallo.
 *
 * Esecuzione: php bin/console app:set-user-locations
 *
 * Sovrascrive la posizione GPS di ogni utente con coordinate casuali
 * entro un raggio di circa 20 km da Mazara del Vallo (37.6544°N, 12.5914°E).
 * Usato per testare la funzionalità di ricerca libri in zona con dati realistici
 * senza dover registrare utenti reali con posizioni geografiche diverse.
 */
#[AsCommand(
    name: 'app:set-user-locations',
    description: 'Imposta la posizione di tutti gli utenti vicino a Mazara del Vallo',
)]
class SetUserLocationsCommand extends Command
{
    public function __construct(
        private UserRepository $userRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Coordinate base di Mazara del Vallo
        $baseLat = 37.6544;
        $baseLng = 12.5914;

        $users = $this->userRepo->findAll();
        $count = 0;

        foreach ($users as $user) {
            // Offset casuale di ±0.2 gradi (~20 km) per simulare una distribuzione realistica
            $randomLat = $baseLat + (mt_rand(-200, 200) / 1000);
            $randomLng = $baseLng + (mt_rand(-200, 200) / 1000);

            $user->setLatitude($randomLat);
            $user->setLongitude($randomLng);
            $count++;
        }

        $this->em->flush();

        $io->success("Aggiornati {$count} utenti con posizioni vicino a Mazara del Vallo (37.6544, 12.5914)");

        return Command::SUCCESS;
    }
}
