<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Paiement - Payer en ligne ».
 */
class PaiementEnLigneTest extends WebTestCase
{
    use BaseDeTestTrait;

    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->preparerBase();
        $seance = $this->creerSeance('Bird', $this->creerSalle('Salle 1', 2, 5), 'tomorrow 20:00', '9.50');
        $this->reservation = $this->reserverPlaces($seance, ['A1', 'A2'], Reservation::STATUT_EN_ATTENTE, 'client@test.fr');
    }

    public function testLeClientPaieParCarteEtLaReservationDevientPayee(): void
    {
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $this->client->request('GET', '/reservations/'.$this->reservation->getId());
        $this->client->clickLink('Payer 19,00 €');
        $this->client->submitForm('Payer 19,00 €', [
            'carte[numeroCarte]' => '4242 4242 4242 4242',
            'carte[expiration]' => '12/30',
            'carte[cvc]' => '123',
        ]);

        self::assertResponseRedirects('/reservations/'.$this->reservation->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.statut', 'Payée');
        $this->em->clear();
        $payee = $this->em->getRepository(Reservation::class)->find($this->reservation->getId());
        self::assertSame('CB_EN_LIGNE', $payee->getModePaiement());
    }

    public function testUneCarteRefuseeLaisseLaReservationEnAttente(): void
    {
        $this->payerViaApi(['numeroCarte' => '4000 0000 0000 0002', 'expiration' => '12/30', 'cvc' => '123']);

        self::assertResponseStatusCodeSame(402);
        $this->em->clear();
        self::assertSame('EN_ATTENTE', $this->em->getRepository(Reservation::class)->find($this->reservation->getId())->getStatut());
    }

    public function testUneCarteMalSaisieEstRefuseeAvantLaBanque(): void
    {
        $json = $this->payerViaApi(['numeroCarte' => '4242 4242 4242 4241', 'expiration' => '01/20', 'cvc' => '1']);

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['numeroCarte', 'expiration', 'cvc'], array_keys($json['details']));
    }

    public function testUneReservationNePeutPasEtrePayeeDeuxFois(): void
    {
        $carte = ['numeroCarte' => '4242424242424242', 'expiration' => '12/30', 'cvc' => '123'];
        $json = $this->payerViaApi($carte);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $json['billets'], 'les codes des billets sont fournis après paiement');

        $this->payerViaApi($carte);
        self::assertResponseStatusCodeSame(409);
    }

    public function testUneReservationExpireeNePeutPlusEtrePayee(): void
    {
        $this->em->getConnection()->executeStatement('UPDATE reservation SET date_reservation = ? WHERE id = ?', [
            (new \DateTimeImmutable('-16 minutes'))->format('Y-m-d H:i:s'), $this->reservation->getId(),
        ]);
        $this->em->clear();

        $json = $this->payerViaApi(['numeroCarte' => '4242424242424242', 'expiration' => '12/30', 'cvc' => '123']);

        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('délai de paiement est dépassé', $json['details']['reservation']);
    }

    /**
     * @param array<string, string> $carte
     *
     * @return array<string, mixed>
     */
    private function payerViaApi(array $carte): array
    {
        $this->client->request('POST', '/api/reservations/'.$this->reservation->getId().'/paiement', server: [
            'PHP_AUTH_USER' => 'client@test.fr', 'PHP_AUTH_PW' => 'motdepasse', 'CONTENT_TYPE' => 'application/json',
        ], content: json_encode($carte));

        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }
}
