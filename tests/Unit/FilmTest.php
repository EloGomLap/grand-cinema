<?php

namespace App\Tests\Unit;

use App\Entity\Film;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de l'entité Film : aucune base de données, aucun framework.
 */
class FilmTest extends TestCase
{
    #[DataProvider('durees')]
    public function testLaDureeEstAfficheeEnHeuresEtMinutes(?int $minutes, string $attendu): void
    {
        $film = (new Film())->setDureeMinutes($minutes);

        self::assertSame($attendu, $film->getDureeFormatee());
    }

    public static function durees(): iterable
    {
        yield 'film de plus d\'une heure' => [133, '2 h 13'];
        yield 'heure pile' => [120, '2 h 00'];
        yield 'court métrage' => [45, '45 min'];
        yield 'durée inconnue' => [null, ''];
        yield 'durée incohérente' => [0, ''];
    }

    public function testLaRepresentationApiContientTousLesChamps(): void
    {
        $film = (new Film())
            ->setTitre('Whiplash')
            ->setDureeMinutes(107)
            ->setRealisateur('Damien Chazelle')
            ->setGenre('Drame musical')
            ->setClassification('Tous publics')
            ->setSynopsis('Un jeune batteur de jazz...')
            ->setAfficheUrl('https://exemple.fr/whiplash.jpg')
            ->setDateSortie(new \DateTimeImmutable('2014-12-24'));

        self::assertSame([
            'id' => null,
            'titre' => 'Whiplash',
            'synopsis' => 'Un jeune batteur de jazz...',
            'dureeMinutes' => 107,
            'realisateur' => 'Damien Chazelle',
            'genre' => 'Drame musical',
            'classification' => 'Tous publics',
            'afficheUrl' => 'https://exemple.fr/whiplash.jpg',
            'dateSortie' => '2014-12-24',
        ], $film->toArray());
    }
}
