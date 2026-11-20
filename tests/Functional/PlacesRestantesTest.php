<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Entity\Seance;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Réservation - Voir les places restantes ».
 */
class PlacesRestantesTest extends WebTestCase
{
    use BaseDeTestTrait;

    private Seance $seance;

    protected function setUp(): void
    {
        $this->preparerBase();
        $salle = $this->creerSalle('Salle 1', 2, 6); // 12 places
        $this->seance = $this->creerSeance('Bird', $salle, 'tomorrow 20:00');
    }

    public function testLesPlacesPayeesEtEnAttenteSontDeduitesMaisPasLesAnnulees(): void
    {
        $this->reserverPlaces($this->seance, ['A1', 'A2']);
        $this->reserverPlaces($this->seance, ['A3'], Reservation::STATUT_EN_ATTENTE);
        $this->reserverPlaces($this->seance, ['B1', 'B2'], Reservation::STATUT_ANNULEE);

        $this->client->request('GET', '/api/seances/'.$this->seance->getId().'/places');
        $json = json_decode($this->client->getResponse()->getContent(), true);

        self::assertResponseIsSuccessful();
        self::assertSame(9, $json['placesRestantes']);
        self::assertFalse($json['complet']);
        $etat = array_column($json['places'], 'libre', 'libelle');
        self::assertFalse($etat['A1']);
        self::assertFalse($etat['A3']);
        self::assertTrue($etat['B1'], 'une réservation annulée libère ses places');
    }

    public function testLaListeDesSeancesAfficheLesPlacesRestantes(): void
    {
        $this->reserverPlaces($this->seance, ['A1', 'A2', 'A3']);

        $this->client->request('GET', '/seances');

        self::assertSelectorTextContains('article.seance .places-restantes', 'Plus que 9 places');
    }

    public function testUneSeancePleineEstAfficheeComplete(): void
    {
        $toutes = array_map(static fn ($p) => $p->getLibelle(), $this->seance->getSalle()->getPlaces()->toArray());
        $this->reserverPlaces($this->seance, $toutes);

        $this->client->request('GET', '/seances/'.$this->seance->getId());

        self::assertSelectorTextContains('.places-restantes', 'Complet');
        self::assertSelectorCount(12, '.plan .rang .siege.pris');
    }

    public function testLePlanMontreLesPlacesLibresEtPrises(): void
    {
        $this->reserverPlaces($this->seance, ['B4']);

        $this->client->request('GET', '/seances/'.$this->seance->getId());

        self::assertSelectorCount(11, '.plan .rang .siege.libre');
        self::assertSelectorExists('.plan .siege.pris[title="Place B4 - déjà prise"]');
    }
}
