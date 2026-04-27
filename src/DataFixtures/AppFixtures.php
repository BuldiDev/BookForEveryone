<?php

namespace App\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Fixture principale generata automaticamente da Symfony.
 *
 * Attualmente non carica dati: le fixture reali sono distribuite
 * nelle classi dedicate GenreFixtures e BookFixtures.
 * Questo file è mantenuto come punto di estensione futuro.
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $manager->flush();
    }
}
