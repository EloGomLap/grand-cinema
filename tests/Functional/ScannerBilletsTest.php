<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Contrôle - Scanner les billets ».
 */
class ScannerBilletsTest extends WebTestCase
{
    use BaseDeTestTrait;

    private string $code;

    protected function setUp(): void
    {
        $this->preparerBase();
        $seance = $this->creerSeance('Whiplash', $this->creerSalle('Salle 1', 2, 5), '+30 minutes');
        $this->code = $this->reserverPlaces($seance, ['A1'], Reservation::STATUT_PAYEE, 'client@test.fr')->getBillets()->first()->getCode();
    }

    public function testLeControleurScanneUnBilletValidePuisLeMemeBilletEstRefuse(): void
    {
        $this->client->loginUser($this->utilisateur('controleur@test.fr'));
        $this->client->request('GET', '/controle');
        $this->client->submitForm('Valider', ['code' => $this->code]);

        self::assertSelectorTextContains('.resultat-controle.accepte', 'ENTRÉE AUTORISÉE');
        self::assertSelectorTextContains('.resultat-controle', 'place A1');

        $this->client->submitForm('Valider', ['code' => $this->code]);
        self::assertSelectorTextContains('.resultat-controle.refuse', 'déjà utilisé');
    }

    public function testLApiDeScanRenvoieUnCodeHttpSelonLeResultat(): void
    {
        $auth = ['PHP_AUTH_USER' => 'controleur@test.fr', 'PHP_AUTH_PW' => 'motdepasse'];

        $this->client->request('POST', '/api/billets/'.$this->code.'/validation', server: $auth);
        self::assertResponseIsSuccessful();
        self::assertSame('Whiplash', json_decode($this->client->getResponse()->getContent(), true)['billet']['film']);

        $this->client->request('POST', '/api/billets/'.$this->code.'/validation', server: $auth);
        self::assertResponseStatusCodeSame(409);

        $this->client->request('POST', '/api/billets/inconnu/validation', server: $auth);
        self::assertResponseStatusCodeSame(404);
    }

    public function testSeulsLesControleursPeuventScanner(): void
    {
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $this->client->request('GET', '/controle');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/api/billets/'.$this->code.'/validation', server: ['PHP_AUTH_USER' => 'guichetier@test.fr', 'PHP_AUTH_PW' => 'motdepasse']);
        self::assertResponseStatusCodeSame(403);
    }
}
