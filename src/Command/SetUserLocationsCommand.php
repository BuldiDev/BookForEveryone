<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        // Mazara del Vallo coordinate: 37.6544°N, 12.5914°E
        $baseLat = 37.6544;
        $baseLng = 12.5914;

        $users = $this->userRepo->findAll();
        $count = 0;

        foreach ($users as $user) {
            // Variazione casuale di ~0.2 gradi (circa 20km)
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
