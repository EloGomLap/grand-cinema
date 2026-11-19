<?php

namespace App\Tests\Unit;

use App\Entity\Film;
use App\Exception\FilmInvalideException;
use App\Service\FilmService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;

/**
 * Tests unitaires du service métier.
 * La base de données est remplacée par un "mock" (doublure) de l'EntityManager :
 * on vérifie la logique sans rien écrire en base.
 */
class FilmServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private FilmService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $this->service = new FilmService($this->entityManager, $validator);
    }

    public function testUnFilmValideEstEnregistre(): void
    {
        $this->entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Film::class));
        $this->entityManager->expects(self::once())->method('flush');

        $film = $this->service->creer([
            'titre' => '  Round Midnight  ',
            'dureeMinutes' => '133',
            'genre' => '',
            'dateSortie' => '1986-11-05',
        ]);

        self::assertSame('Round Midnight', $film->getTitre(), 'les espaces superflus sont retirés');
        self::assertSame(133, $film->getDureeMinutes(), 'la durée saisie en texte est convertie en entier');
        self::assertNull($film->getGenre(), 'un champ vide devient null');
        self::assertSame('1986-11-05', $film->getDateSortie()?->format('Y-m-d'));
    }

    public function testUnFilmSansTitreNiDureeEstRefuseEtRienNEstEnregistre(): void
    {
        $this->entityManager->expects(self::never())->method('persist');
        $this->entityManager->expects(self::never())->method('flush');

        try {
            $this->service->creer(['titre' => '   ']);
            self::fail('Une FilmInvalideException était attendue.');
        } catch (FilmInvalideException $e) {
            self::assertArrayHasKey('titre', $e->getErreurs());
            self::assertArrayHasKey('dureeMinutes', $e->getErreurs());
        }
    }

    public function testToutesLesErreursSontRemonteesEnUneFois(): void
    {
        try {
            $this->service->creer([
                'titre' => '',
                'dureeMinutes' => 'deux heures',
                'dateSortie' => '31/12/2026',
                'classification' => '-10',
                'afficheUrl' => 'pas-une-url',
            ]);
            self::fail('Une FilmInvalideException était attendue.');
        } catch (FilmInvalideException $e) {
            self::assertEqualsCanonicalizing(
                ['titre', 'dureeMinutes', 'dateSortie', 'classification', 'afficheUrl'],
                array_keys($e->getErreurs()),
            );
        }
    }

    public function testUneDureeNegativeEstRefusee(): void
    {
        $this->expectException(FilmInvalideException::class);

        $this->service->creer(['titre' => 'Bird', 'dureeMinutes' => -10]);
    }

    public function testUneDateInexistanteEstRefusee(): void
    {
        $this->expectException(FilmInvalideException::class);

        $this->service->creer(['titre' => 'Bird', 'dureeMinutes' => 161, 'dateSortie' => '2026-02-30']);
    }

    public function testLaModificationNeChangeQueLesChampsEnvoyes(): void
    {
        $film = (new Film())->setTitre('Bird')->setDureeMinutes(161)->setGenre('Biopic');
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->modifier($film, ['classification' => '-12']);

        self::assertSame('Bird', $film->getTitre());
        self::assertSame('Biopic', $film->getGenre());
        self::assertSame('-12', $film->getClassification());
    }

    public function testUneModificationInvalideEstRefusee(): void
    {
        $film = (new Film())->setTitre('Bird')->setDureeMinutes(161);
        $this->entityManager->expects(self::never())->method('flush');

        $this->expectException(FilmInvalideException::class);
        $this->service->modifier($film, ['titre' => '']);
    }

    public function testLaSuppressionRetireLeFilmDeLaBase(): void
    {
        $film = (new Film())->setTitre('Bird')->setDureeMinutes(161);
        $this->entityManager->expects(self::once())->method('remove')->with($film);
        $this->entityManager->expects(self::once())->method('flush');

        $this->service->supprimer($film);
    }
}
