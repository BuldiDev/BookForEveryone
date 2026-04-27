<?php

namespace App\DataFixtures;

use App\Entity\Genre;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture per popolare la tabella dei generi letterari.
 *
 * Ogni genere ha un nome visualizzato e uno slug URL-friendly.
 * La fixture è idempotente: se un genere esiste già (ricerca per slug)
 * non viene ricreato, ma viene comunque registrata la Reference Doctrine
 * affinché le altre fixture possano usare getReference('genre_<slug>').
 *
 * Esecuzione: php bin/console doctrine:fixtures:load
 */
class GenreFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $genreRepo = $manager->getRepository(Genre::class);

        // 20 generi letterari che coprono le categorie principali del catalogo
        $genres = [
            ['name' => 'Fantasy', 'slug' => 'fantasy'],
            ['name' => 'Fantascienza', 'slug' => 'fantascienza'],
            ['name' => 'Giallo', 'slug' => 'giallo'],
            ['name' => 'Thriller', 'slug' => 'thriller'],
            ['name' => 'Horror', 'slug' => 'horror'],
            ['name' => 'Romanzo', 'slug' => 'romanzo'],
            ['name' => 'Avventura', 'slug' => 'avventura'],
            ['name' => 'Storico', 'slug' => 'storico'],
            ['name' => 'Biografia', 'slug' => 'biografia'],
            ['name' => 'Saggistica', 'slug' => 'saggistica'],
            ['name' => 'Poesia', 'slug' => 'poesia'],
            ['name' => 'Manga', 'slug' => 'manga'],
            ['name' => 'Fumetto', 'slug' => 'fumetto'],
            ['name' => 'Dystopia', 'slug' => 'dystopia'],
            ['name' => 'Young Adult', 'slug' => 'young-adult'],
            ['name' => 'Classici', 'slug' => 'classici'],
            ['name' => 'Self-help', 'slug' => 'self-help'],
            ['name' => 'Cucina', 'slug' => 'cucina'],
            ['name' => 'Viaggio', 'slug' => 'viaggio'],
            ['name' => 'Scienza', 'slug' => 'scienza'],
        ];

        foreach ($genres as $data) {
            $genre = $genreRepo->findOneBy(['slug' => $data['slug']]);
            if (!$genre) {
                $genre = new Genre();
                $genre->setName($data['name']);
                $genre->setSlug($data['slug']);
                $manager->persist($genre);
            }

            $this->addReference('genre_' . $data['slug'], $genre);
        }

        $manager->flush();
    }
}
