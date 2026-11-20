<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Pilotage - Suivre la fréquentation ».
 */
class FrequentationTest extends WebTestCase
{
    use BaseDeTestTrait;

    protected function setUp(): void
    {
        $this->preparerBase();
        $salle = $this->creerSalle('Salle 1', 2, 5);
        $demain = $this->creerSeance('Bird', $salle, 'tomorrow 14:00', '10.00');
        $apresDemain = $this->creerSeance('Whiplash', $salle, '+2 days 20:00', '8.00');
        $this->reserverPlaces($demain, ['A1', 'A2', 'A3']);
        $this->reserverPlaces($demain, ['B1'], canal: 'GUICHET')->getBillets()->first()->valider();
        $this->reserverPlaces($apresDemain, ['A1', 'A2'], Reservation::STATUT_EN_ATTENTE);
        $this->reserverPlaces($apresDemain, ['B5']);
        $this->em->flush();
    }

    public function testLesEntreesSontComptabiliseesParJourParFilmEtParCanal(): void
    {
        $this->client->request('GET', '/api/pilotage/frequentation', server: ['PHP_AUTH_USER' => 'programmateur@test.fr', 'PHP_AUTH_PW' => 'motdepasse']);
        $json = json_decode($this->client->getResponse()->getContent(), true);

        self::assertResponseIsSuccessful();
        self::assertSame(5, $json['entrees'], 'les places en attente de paiement ne comptent pas');
        self::assertEquals(48.0, $json['recettes']);
        self::assertEquals(80.0, $json['partEnLigne']);
        self::assertSame(['EN_LIGNE' => 4, 'GUICHET' => 1], $json['parCanal']);
        self::assertSame(1, $json['controlees']);
        self::assertSame([['film' => 'Bird', 'entrees' => 4], ['film' => 'Whiplash', 'entrees' => 1]], $json['parFilm']);
        $jours = array_column($json['parJour'], 'entrees', 'jour');
        self::assertSame(4, $jours[(new \DateTimeImmutable('tomorrow'))->format('Y-m-d')]);
        self::assertCount(15, $json['parJour'], 'un point par jour de la période, même sans entrée');
    }

    public function testLaPageAfficheLesIndicateursDeFrequentation(): void
    {
        $this->client->loginUser($this->utilisateur('admin@test.fr'));
        $this->client->request('GET', '/pilotage');

        self::assertSelectorTextContains('#frequentation', '48,00 €');
        self::assertSelectorCount(15, '#frequentation .histogramme .barre');
    }
}
