<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Pilotage - Suivre le taux de remplissage ».
 */
class TauxRemplissageTest extends WebTestCase
{
    use BaseDeTestTrait;

    protected function setUp(): void
    {
        $this->preparerBase();
        $salle = $this->creerSalle('Salle 1', 2, 5); // 10 places
        $bird = $this->creerSeance('Bird', $salle, 'tomorrow 14:00');
        $whiplash = $this->creerSeance('Whiplash', $salle, 'tomorrow 20:00');
        $this->reserverPlaces($bird, ['A1', 'A2', 'A3', 'A4']);
        $this->reserverPlaces($bird, ['B1'], Reservation::STATUT_EN_ATTENTE);
        $this->reserverPlaces($whiplash, ['A1'], canal: 'GUICHET');
    }

    public function testLeTauxEstCalculeParSeanceParFilmEtEnMoyenne(): void
    {
        $this->client->request('GET', '/api/pilotage/remplissage', server: ['PHP_AUTH_USER' => 'programmateur@test.fr', 'PHP_AUTH_PW' => 'motdepasse']);
        $json = json_decode($this->client->getResponse()->getContent(), true);

        self::assertResponseIsSuccessful();
        self::assertSame([40, 10], array_map('intval', array_column($json['seances'], 'taux')), 'la réservation en attente ne compte pas');
        self::assertEquals(25.0, $json['moyenne']);
        self::assertSame('Bird', $json['films'][0]['film'], 'les films sont classés du plus rempli au moins rempli');
        self::assertSame(5, $json['placesVendues']);
    }

    public function testLaPageDePilotageAfficheLesTaux(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $this->client->request('GET', '/pilotage');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#remplissage .indicateur', '25,0 %');
        self::assertSelectorCount(2, '#remplissage-seances tbody tr');
    }

    public function testLePilotageEstReserveAuGestionnaire(): void
    {
        $this->client->loginUser($this->utilisateur('guichetier@test.fr'));
        $this->client->request('GET', '/pilotage');

        self::assertResponseStatusCodeSame(403);
    }
}
