<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Compte client - Retrouver ses billets dans son compte ».
 */
class MesBilletsTest extends WebTestCase
{
    use BaseDeTestTrait;

    protected function setUp(): void
    {
        $this->preparerBase();
        $salle = $this->creerSalle('Salle 1', 2, 5);
        $demain = $this->creerSeance('Whiplash', $salle, 'tomorrow 20:00');
        $hier = $this->creerSeance('Bird', $salle, '-1 day 14:00');
        $this->reserverPlaces($demain, ['A1', 'A2'], Reservation::STATUT_PAYEE, 'client@test.fr');
        $this->reserverPlaces($demain, ['B1'], Reservation::STATUT_EN_ATTENTE, 'client@test.fr');
        $this->reserverPlaces($hier, ['A3'], Reservation::STATUT_PAYEE, 'client@test.fr');
        $this->reserverPlaces($demain, ['A5'], Reservation::STATUT_PAYEE, 'programmateur@test.fr');
    }

    public function testLeClientRetrouveSesBilletsAVenirEnAttenteEtPasses(): void
    {
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $this->client->request('GET', '/mon-compte/billets');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '#a-venir .reservation');
        self::assertSelectorTextContains('#a-venir', 'Places A1, A2');
        self::assertSelectorCount(2, '#a-venir a[href^="/billets/"]');
        self::assertSelectorTextContains('main', 'Finaliser le paiement');
        self::assertSelectorTextContains('#historique', 'Bird');
        self::assertSelectorTextNotContains('main', 'A5', 'les billets des autres clients n\'apparaissent pas');
    }

    public function testLApiRenvoieLesBilletsPayesDuClient(): void
    {
        $this->client->request('GET', '/api/reservations/mes-billets', server: ['PHP_AUTH_USER' => 'client@test.fr', 'PHP_AUTH_PW' => 'motdepasse']);
        $json = json_decode($this->client->getResponse()->getContent(), true);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $json);
        self::assertSame(['A3'], $json[0]['places']);
        self::assertCount(2, $json[1]['billets']);
    }

    public function testIlFautEtreConnecte(): void
    {
        $this->client->request('GET', '/mon-compte/billets');

        self::assertResponseRedirects('/connexion');
    }
}
