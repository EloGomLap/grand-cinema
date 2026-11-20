<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Réservation - Voir les places restantes au guichet ».
 */
class GuichetPlacesRestantesTest extends WebTestCase
{
    use BaseDeTestTrait;

    public function testLeGuichetVoitLesPlacesRestantesYComprisLesVentesEnLigne(): void
    {
        $this->preparerBase();
        $seance = $this->creerSeance('Bird', $this->creerSalle('Salle 1', 2, 5), 'tomorrow 20:00');
        $complete = $this->creerSeance('Whiplash', $this->creerSalle('Salle 2', 1, 2), 'tomorrow 21:00');
        $this->reserverPlaces($seance, ['A1', 'A2', 'A3'], Reservation::STATUT_PAYEE, 'client@test.fr');
        $this->reserverPlaces($complete, ['A1', 'A2']);

        $this->client->loginUser($this->utilisateur('guichetier@test.fr'));
        $crawler = $this->client->request('GET', '/guichet?date='.(new \DateTimeImmutable('tomorrow'))->format('Y-m-d'));

        $lignes = $crawler->filter('#seances-guichet tbody tr');
        self::assertSame('7 / 10', $lignes->eq(0)->filter('.restantes')->text());
        self::assertSame('Complet', $lignes->eq(1)->filter('.restantes')->text());

        $this->client->click($crawler->selectLink('Bird')->link());
        self::assertSelectorTextContains('.places-restantes', '7 places restantes sur 10');
        self::assertSelectorCount(3, '.plan .rang .siege.pris');
    }
}
