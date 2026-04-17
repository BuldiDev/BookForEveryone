<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\GenreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:populate-users',
    description: 'Crea utenti casuali con relazioni di follow tra di loro',
)]
class PopulateUsersCommand extends Command
{
    private const FIRST_NAMES = [
        'Marco', 'Giulia', 'Luca', 'Sara', 'Alessandro', 'Francesca', 'Andrea',
        'Elena', 'Matteo', 'Chiara', 'Lorenzo', 'Valentina', 'Davide', 'Anna',
        'Federico', 'Marta', 'Simone', 'Laura', 'Giovanni', 'Sofia', 'Roberto',
        'Elisa', 'Tommaso', 'Alice', 'Riccardo', 'Beatrice', 'Giuseppe', 'Camilla',
        'Antonio', 'Giorgia', 'Paolo', 'Martina', 'Stefano', 'Ilaria', 'Nicola',
        'Claudia', 'Emanuele', 'Arianna', 'Francesco', 'Serena', 'Pietro', 'Noemi',
        'Carlo', 'Roberta', 'Michele', 'Silvia', 'Gabriele', 'Monica', 'Fabio',
        'Veronica', 'Enrico', 'Federica', 'Alberto', 'Paola', 'Daniele', 'Irene',
        'Filippo', 'Alessia', 'Cristian', 'Lucia', 'Giacomo', 'Teresa', 'Massimo',
        'Eleonora', 'Vincenzo', 'Michela', 'Leonardo', 'Viviana', 'Diego', 'Aurora',
    ];

    private const LAST_NAMES = [
        'Rossi', 'Russo', 'Ferrari', 'Esposito', 'Bianchi', 'Romano', 'Colombo',
        'Ricci', 'Marino', 'Greco', 'Bruno', 'Gallo', 'Conti', 'DeLuca', 'Mancini',
        'Costa', 'Giordano', 'Rizzo', 'Lombardi', 'Moretti', 'Barbieri', 'Fontana',
        'Santoro', 'Mariani', 'Rinaldi', 'Caruso', 'Ferrara', 'Galli', 'Martini',
        'Leone', 'Longo', 'Gentile', 'Martinelli', 'Vitale', 'Lombardo', 'Serra',
        'Coppola', 'DeSantis', 'DAngelis', 'Marchetti', 'Farina', 'Rizzi', 'Monti',
        'Cattaneo', 'Morelli', 'Amato', 'Silvestri', 'Mazza', 'Testa', 'Grassi',
    ];

    private const BIOS = [
        'Appassionato di lettura da sempre 📚',
        'Lettore vorace, amante dei classici.',
        'Sempre alla ricerca del prossimo libro da divorare.',
        'Leggo di tutto, dal fantasy alla saggistica.',
        'Bookworm incallito. Consigli sempre benvenuti!',
        'Amo perdermi nelle storie e condividerle.',
        'La lettura è il mio superpotere.',
        'Collezionista di storie e di sogni.',
        'Ogni libro è un viaggio.',
        'Leggo, quindi sono.',
        'Fan di thriller e gialli.',
        'Amante della fantascienza e del fantasy.',
        'Lettrice appassionata di romanzi storici.',
        'Mi piace scoprire nuovi autori italiani.',
        'Sempre con un libro in borsa.',
        'Leggo prima di dormire, ogni sera.',
        'Biblioteca personale: oltre 500 volumi.',
        'Appassionata di manga e fumetti.',
        'Lettore lento ma costante.',
        'Adoro i libri che fanno riflettere.',
        null,
        null,
        null,
    ];

    // Coordinate di città italiane (lat, lng)
    private const ITALIAN_CITIES = [
        [41.9028, 12.4964],  // Roma
        [45.4642, 9.1900],   // Milano
        [40.8518, 14.2681],  // Napoli
        [45.0703, 7.6869],   // Torino
        [44.4949, 11.3426],  // Bologna
        [43.7696, 11.2558],  // Firenze
        [45.4384, 12.3267],  // Venezia
        [38.1157, 13.3615],  // Palermo
        [44.4056, 8.9463],   // Genova
        [40.3516, 18.1750],  // Lecce
        [45.4064, 11.8768],  // Padova
        [37.5079, 15.0830],  // Catania
        [41.1171, 16.8719],  // Bari
        [43.3188, 11.3308],  // Siena
        [46.0693, 11.1212],  // Trento
        [45.6495, 13.7768],  // Trieste
        [43.6158, 13.5189],  // Ancona
        [39.2238, 9.1217],   // Cagliari
        [42.3505, 13.3995],  // L'Aquila
        [38.9098, 16.5877],  // Catanzaro
        [44.0579, 12.5654],  // Rimini
        [45.5416, 10.2118],  // Brescia
        [44.8015, 10.3279],  // Parma
        [41.4623, 15.5446],  // Foggia
        [40.6401, 15.8056],  // Potenza
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
        private GenreRepository $genreRepo,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('count', InputArgument::OPTIONAL, 'Numero di utenti da creare', 1000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $input->getArgument('count');

        $io->title("Creazione di {$count} utenti casuali");

        $genres = $this->genreRepo->findAll();
        if (empty($genres)) {
            $io->error('Nessun genere trovato! Esegui prima le fixture dei generi.');
            return Command::FAILURE;
        }

        // ── Fase 1: Creazione utenti ──
        $io->section('Fase 1: Creazione utenti...');
        $progressBar = $io->createProgressBar($count);

        // Hash la password una sola volta e riusala per tutti
        $dummyUser = new User();
        $hashedPassword = $this->hasher->hashPassword($dummyUser, 'prova');

        $users = [];
        $usedUsernames = [];

        // Carica username già esistenti nel DB
        $existingUsers = $this->em->getRepository(User::class)->findAll();
        foreach ($existingUsers as $eu) {
            $usedUsernames[$eu->getUsername()] = true;
        }

        // ── Utente fisso BookForEveryone ──
        if (!isset($usedUsernames['bookforeveryone'])) {
            $fixedUser = new User();
            $fixedUser->setEmail('bookforeveryone@gmail.com');
            $fixedUser->setUsername('bookforeveryone');
            $fixedUser->setPassword($hashedPassword);
            $fixedUser->setDisplayName('BookForEveryone');
            $fixedUser->setIsVerified(true);
            $fixedUser->setOnboardingCompleted(true);
            $fixedUser->addCulturePoints(1000);
            $fixedUser->setBio('Account ufficiale BookForEveryone 📚');
            
            // Posizione a Mazara del Vallo
            $fixedUser->setLatitude(37.6544);
            $fixedUser->setLongitude(12.5914);
            
            // Aggiungi tutti i generi
            foreach ($genres as $genre) {
                $fixedUser->addPreferredGenre($genre);
            }
            
            $this->em->persist($fixedUser);
            $users[] = $fixedUser;
            $usedUsernames['bookforeveryone'] = true;
            
            $io->note('✓ Creato utente fisso: bookforeveryone / prova');
        }

        for ($i = 0; $i < $count; $i++) {
            $firstName = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $lastName = self::LAST_NAMES[array_rand(self::LAST_NAMES)];

            // Username unico
            $baseUsername = strtolower($firstName . '.' . $lastName);
            $username = $baseUsername . random_int(100, 9999);
            while (isset($usedUsernames[$username])) {
                $username = $baseUsername . random_int(100, 99999);
            }
            $usedUsernames[$username] = true;

            $user = new User();
            $user->setEmail($username . '@example.com');
            $user->setUsername($username);
            $user->setPassword($hashedPassword);
            $user->setDisplayName($firstName . ' ' . $lastName);
            $user->setIsVerified(true);
            $user->setOnboardingCompleted(true);
            $user->addCulturePoints(random_int(0, 500));

            // Bio casuale (70% ha una bio)
            $bio = self::BIOS[array_rand(self::BIOS)];
            if ($bio) {
                $user->setBio($bio);
            }

            // Posizione: 80% degli utenti ha una posizione
            if (random_int(1, 100) <= 80) {
                $city = self::ITALIAN_CITIES[array_rand(self::ITALIAN_CITIES)];
                // Aggiungi leggero offset per non avere tutti nello stesso punto
                $user->setLatitude($city[0] + (random_int(-500, 500) / 10000));
                $user->setLongitude($city[1] + (random_int(-500, 500) / 10000));
            }

            // Generi preferiti: 2-5 casuali
            $numGenres = random_int(2, 5);
            $shuffled = $genres;
            shuffle($shuffled);
            for ($g = 0; $g < $numGenres; $g++) {
                $user->addPreferredGenre($shuffled[$g]);
            }

            $this->em->persist($user);
            $users[] = $user;

            if (($i + 1) % 50 === 0) {
                $this->em->flush();
            }

            $progressBar->advance();
        }

        $this->em->flush();
        $progressBar->finish();
        $io->newLine(2);

        // ── Fase 2: Relazioni di follow ──
        $io->section('Fase 2: Creazione relazioni di follow...');

        $totalUsers = count($users);
        $totalFollows = 0;

        $progressBar = $io->createProgressBar($totalUsers);

        for ($i = 0; $i < $totalUsers; $i++) {
            $user = $users[$i];

            // Ogni utente segue tra 5 e 40 persone
            $numFollowing = random_int(5, min(40, $totalUsers - 1));
            $candidates = range(0, $totalUsers - 1);
            unset($candidates[$i]); // Non seguire se stessi
            $candidates = array_values($candidates);
            shuffle($candidates);

            $toFollow = array_slice($candidates, 0, $numFollowing);

            foreach ($toFollow as $targetIdx) {
                $user->follow($users[$targetIdx]);
                $totalFollows++;
            }

            if (($i + 1) % 50 === 0) {
                $this->em->flush();
                $this->em->clear();

                // Ricarica i riferimenti dopo il clear
                $users = $this->em->getRepository(User::class)->findAll();
                $totalUsers = count($users);
            }

            $progressBar->advance();
        }

        $this->em->flush();
        $progressBar->finish();
        $io->newLine(2);

        $io->success(sprintf(
            'Creati %d utenti con %d relazioni di follow (media: %.0f follow/utente)',
            $count,
            $totalFollows,
            $totalFollows / $count,
        ));

        return Command::SUCCESS;
    }
}
