<?php

namespace App\DataFixtures;

use App\Entity\Book;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class BookFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(private UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Crea un utente "sistema" per addedBy
        $user = new User();
        $user->setEmail('admin@bookforeveryone.it');
        $user->setUsername('bookadmin');
        $user->setDisplayName('BookForEveryone');
        $user->setOnboardingCompleted(true);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
        $manager->persist($user);

        $books = [
            [
                'title' => 'Il Signore degli Anelli',
                'author' => 'J.R.R. Tolkien',
                'description' => 'Un hobbit intraprende un viaggio epico per distruggere un anello maligno e salvare la Terra di Mezzo dal signore oscuro Sauron. Un\'opera fondamentale del genere fantasy che ha ridefinito la letteratura mondiale.',
                'isbn' => '9788845292613',
                'pageCount' => 1200,
                'publisher' => 'Bompiani',
                'year' => 1954,
                'cover' => '/uploads/books/signore-degli-anelli.jpg',
                'genres' => ['fantasy', 'avventura', 'classici'],
            ],
            [
                'title' => '1984',
                'author' => 'George Orwell',
                'description' => 'In un futuro distopico, Winston Smith lotta contro un regime totalitario che controlla ogni aspetto della vita. Un romanzo profetico sulla sorveglianza, la propaganda e la libertà individuale.',
                'isbn' => '9788804668237',
                'pageCount' => 328,
                'publisher' => 'Mondadori',
                'year' => 1949,
                'cover' => '/uploads/books/1984.jpg',
                'genres' => ['dystopia', 'classici', 'fantascienza'],
            ],
            [
                'title' => 'Harry Potter e la Pietra Filosofale',
                'author' => 'J.K. Rowling',
                'description' => 'Harry Potter scopre di essere un mago e viene ammesso alla Scuola di Magia e Stregoneria di Hogwarts, dove inizia un\'avventura straordinaria tra amicizia, coraggio e mistero.',
                'isbn' => '9788831003384',
                'pageCount' => 293,
                'publisher' => 'Salani',
                'year' => 1997,
                'cover' => '/uploads/books/harry-potter.jpg',
                'genres' => ['fantasy', 'avventura', 'young-adult'],
            ],
            [
                'title' => 'Il Nome della Rosa',
                'author' => 'Umberto Eco',
                'description' => 'Un frate francescano indaga su una serie di misteriosi omicidi in un\'abbazia benedettina del XIV secolo. Un capolavoro che intreccia giallo, storia e filosofia.',
                'isbn' => '9788845927720',
                'pageCount' => 512,
                'publisher' => 'Bompiani',
                'year' => 1980,
                'cover' => '/uploads/books/nome-della-rosa.jpg',
                'genres' => ['giallo', 'storico', 'classici'],
            ],
            [
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'description' => 'Sul pianeta desertico Arrakis, il giovane Paul Atreides affronta intrighi politici e lotte per il controllo della spezia, la sostanza più preziosa dell\'universo.',
                'isbn' => '9788834739396',
                'pageCount' => 688,
                'publisher' => 'Fanucci',
                'year' => 1965,
                'cover' => '/uploads/books/dune.jpg',
                'genres' => ['fantascienza', 'avventura'],
            ],
            [
                'title' => 'It',
                'author' => 'Stephen King',
                'description' => 'Un gruppo di amici d\'infanzia si riunisce per affrontare un\'entità malvagia che terrorizza la loro cittadina ogni 27 anni, assumendo la forma delle loro paure più profonde.',
                'isbn' => '9788868368241',
                'pageCount' => 1184,
                'publisher' => 'Sperling & Kupfer',
                'year' => 1986,
                'cover' => '/uploads/books/it-stephen-king.jpg',
                'genres' => ['horror', 'thriller'],
            ],
            [
                'title' => 'Orgoglio e Pregiudizio',
                'author' => 'Jane Austen',
                'description' => 'Elizabeth Bennet e il signor Darcy superano le barriere sociali e i propri pregiudizi per trovare il vero amore nell\'Inghilterra della Reggenza.',
                'isbn' => '9788807900389',
                'pageCount' => 432,
                'publisher' => 'Feltrinelli',
                'year' => 1813,
                'cover' => '/uploads/books/orgoglio-pregiudizio.jpg',
                'genres' => ['romanzo', 'classici'],
            ],
            [
                'title' => 'Sapiens: Da animali a dèi',
                'author' => 'Yuval Noah Harari',
                'description' => 'Un viaggio attraverso la storia dell\'umanità, dalla comparsa dell\'Homo sapiens alla rivoluzione scientifica, che ci porta a riflettere su chi siamo e dove stiamo andando.',
                'isbn' => '9788845929885',
                'pageCount' => 540,
                'publisher' => 'Bompiani',
                'year' => 2011,
                'cover' => '/uploads/books/sapiens.jpg',
                'genres' => ['saggistica', 'scienza', 'storico'],
            ],
            [
                'title' => 'Naruto Vol. 1',
                'author' => 'Masashi Kishimoto',
                'description' => 'Naruto Uzumaki, un giovane ninja con un demone sigillato dentro di sé, sogna di diventare Hokage, il leader più forte del suo villaggio.',
                'isbn' => '9788828730118',
                'pageCount' => 192,
                'publisher' => 'Planet Manga',
                'year' => 1999,
                'cover' => '/uploads/books/naruto.jpg',
                'genres' => ['manga', 'avventura', 'fantasy'],
            ],
            [
                'title' => 'Cent\'anni di solitudine',
                'author' => 'Gabriel García Márquez',
                'description' => 'La saga della famiglia Buendía nel villaggio immaginario di Macondo, un\'opera di realismo magico che esplora amore, solitudine e il destino attraverso sette generazioni.',
                'isbn' => '9788804667865',
                'pageCount' => 368,
                'publisher' => 'Mondadori',
                'year' => 1967,
                'cover' => '/uploads/books/centanni-solitudine.jpg',
                'genres' => ['romanzo', 'classici'],
            ],
            [
                'title' => 'Il Piccolo Principe',
                'author' => 'Antoine de Saint-Exupéry',
                'description' => 'Un pilota precipitato nel deserto incontra un piccolo principe venuto da un asteroide lontano. Un racconto poetico sull\'essenziale, sull\'amicizia e sull\'amore.',
                'isbn' => '9788845292613',
                'pageCount' => 96,
                'publisher' => 'Bompiani',
                'year' => 1943,
                'cover' => '/uploads/books/piccolo-principe.jpg',
                'genres' => ['classici', 'young-adult'],
            ],
            [
                'title' => 'Fahrenheit 451',
                'author' => 'Ray Bradbury',
                'description' => 'In una società futura dove i libri sono proibiti e bruciati, un pompiere inizia a mettere in discussione il sistema. Una potente riflessione sulla censura e il valore della letteratura.',
                'isbn' => '9788804667261',
                'pageCount' => 186,
                'publisher' => 'Mondadori',
                'year' => 1953,
                'cover' => '/uploads/books/fahrenheit-451.jpg',
                'genres' => ['fantascienza', 'dystopia', 'classici'],
            ],
            [
                'title' => 'L\'Alchimista',
                'author' => 'Paulo Coelho',
                'description' => 'Santiago, un giovane pastore andaluso, intraprende un viaggio alla ricerca di un tesoro nascosto presso le Piramidi d\'Egitto, scoprendo lungo il cammino la propria Leggenda Personale.',
                'isbn' => '9788845247729',
                'pageCount' => 198,
                'publisher' => 'Bompiani',
                'year' => 1988,
                'cover' => '/uploads/books/alchimista.jpg',
                'genres' => ['romanzo', 'avventura', 'self-help'],
            ],
            [
                'title' => 'Diario di una Schiappa',
                'author' => 'Jeff Kinney',
                'description' => 'Greg Heffley racconta la sua vita caotica da studente delle medie in un diario pieno di disavventure esilaranti, illustrazioni e situazioni in cui tutti possono riconoscersi.',
                'isbn' => '9788880336716',
                'pageCount' => 224,
                'publisher' => 'Il Castoro',
                'year' => 2007,
                'cover' => '/uploads/books/diario-schiappa.jpg',
                'genres' => ['young-adult', 'fumetto'],
            ],
            [
                'title' => 'Steve Jobs',
                'author' => 'Walter Isaacson',
                'description' => 'La biografia autorizzata del fondatore di Apple, basata su oltre quaranta interviste con Jobs e centinaia di colloqui con familiari, colleghi e rivali.',
                'isbn' => '9788804621126',
                'pageCount' => 630,
                'publisher' => 'Mondadori',
                'year' => 2011,
                'cover' => '/uploads/books/steve-jobs.jpg',
                'genres' => ['biografia', 'saggistica'],
            ],
            [
                'title' => 'Il Gioco di Ender',
                'author' => 'Orson Scott Card',
                'description' => 'Il giovane Ender Wiggin viene reclutato in una scuola militare spaziale per essere addestrato a combattere un\'invasione aliena. Ma il confine tra gioco e realtà è sempre più sottile.',
                'isbn' => '9788842916291',
                'pageCount' => 374,
                'publisher' => 'Nord',
                'year' => 1985,
                'cover' => '/uploads/books/gioco-ender.jpg',
                'genres' => ['fantascienza', 'avventura', 'young-adult'],
            ],
            [
                'title' => 'La Ragazza con la Leica',
                'author' => 'Helena Janeczek',
                'description' => 'La storia di Gerda Taro, fotografa e compagna di Robert Capa, raccontata attraverso tre voci che ne ricostruiscono la vita breve e appassionata durante la Guerra Civile Spagnola.',
                'isbn' => '9788823520622',
                'pageCount' => 326,
                'publisher' => 'Guanda',
                'year' => 2017,
                'cover' => 'https://covers.openlibrary.org/b/isbn/9788823520622-L.jpg',
                'genres' => ['storico', 'biografia', 'romanzo'],
            ],
            [
                'title' => 'In Cucina con Amore',
                'author' => 'Benedetta Rossi',
                'description' => 'Ricette semplici e gustose per tutti i giorni, dalla cuoca più amata d\'Italia. Piatti della tradizione rivisitati con semplicità e tanto amore.',
                'isbn' => '9788804712008',
                'pageCount' => 256,
                'publisher' => 'Mondadori',
                'year' => 2019,
                'cover' => 'https://covers.openlibrary.org/b/isbn/9788804712008-L.jpg',
                'genres' => ['cucina'],
            ],
            [
                'title' => 'Nelle Terre Estreme',
                'author' => 'Jon Krakauer',
                'description' => 'La storia vera di Christopher McCandless, un giovane che abbandona tutto per vivere nella natura selvaggia dell\'Alaska. Un racconto di libertà, ricerca e tragedia.',
                'isbn' => '9788863800944',
                'pageCount' => 250,
                'publisher' => 'Corbaccio',
                'year' => 1996,
                'cover' => '/uploads/books/terre-estreme.jpg',
                'genres' => ['viaggio', 'avventura', 'biografia'],
            ],
            [
                'title' => 'Breve Storia di (quasi) Tutto',
                'author' => 'Bill Bryson',
                'description' => 'Un viaggio affascinante e divertente attraverso le meraviglie della scienza, dalla nascita dell\'universo alle particelle subatomiche, raccontato in modo accessibile a tutti.',
                'isbn' => '9788823507524',
                'pageCount' => 576,
                'publisher' => 'Guanda',
                'year' => 2003,
                'cover' => '/uploads/books/breve-storia.jpg',
                'genres' => ['scienza', 'saggistica'],
            ],
        ];

        foreach ($books as $data) {
            $book = new Book();
            $book->setTitle($data['title']);
            $book->setBookAuthor($data['author']);
            $book->setDescription($data['description']);
            $book->setIsbn($data['isbn']);
            $book->setPageCount($data['pageCount']);
            $book->setPublisher($data['publisher']);
            $book->setPublicationYear($data['year']);
            $book->setCoverUrl($data['cover']);
            $book->setAddedBy($user);

            foreach ($data['genres'] as $genreSlug) {
                $book->addGenre($this->getReference('genre_' . $genreSlug, \App\Entity\Genre::class));
            }

            $manager->persist($book);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            GenreFixtures::class,
        ];
    }
}
