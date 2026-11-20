<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Notification\EnvoiEmailInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Réservation - Recevoir ses billets ».
 */
class RecevoirBilletsTest extends WebTestCase
{
    use BaseDeTestTrait;

    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->preparerBase();
        $seance = $this->creerSeance('Whiplash', $this->creerSalle('Salle 1', 2, 5), 'tomorrow 20:00');
        $this->reservation = $this->reserverPlaces($seance, ['B1', 'B2'], Reservation::STATUT_EN_ATTENTE, 'client@test.fr');
    }

    public function testApresLePaiementLeClientRecoitUnEmailAvecSesBillets(): void
    {
        $this->client->disableReboot();
        $this->client->request('POST', '/api/reservations/'.$this->reservation->getId().'/paiement', server: [
            'PHP_AUTH_USER' => 'client@test.fr', 'PHP_AUTH_PW' => 'motdepasse', 'CONTENT_TYPE' => 'application/json',
        ], content: json_encode(['numeroCarte' => '4242424242424242', 'expiration' => '12/30', 'cvc' => '123']));
        self::assertResponseIsSuccessful();

        $messages = static::getContainer()->get(EnvoiEmailInterface::class)->getMessages();
        self::assertCount(1, $messages);
        self::assertSame('client@test.fr', $messages[0]['destinataire']);
        self::assertStringStartsWith('Vos billets pour Whiplash', $messages[0]['sujet']);
        foreach ($this->reservation->getBillets() as $billet) {
            self::assertStringContainsString('/billets/'.$billet->getCode(), $messages[0]['texte']);
        }
    }

    public function testLaPageDeLaReservationPayeeAfficheLesBilletsAvecQrCode(): void
    {
        $this->reservation->marquerPayee('CB_EN_LIGNE');
        $this->em->flush();
        $this->client->loginUser($this->utilisateur('client@test.fr'));

        $this->client->request('GET', '/reservations/'.$this->reservation->getId());

        self::assertSelectorCount(2, '.billets .billet');
        self::assertSelectorCount(2, '.billets .qr[data-code]');
    }

    public function testUnBilletNonPayeNEstPasAccessible(): void
    {
        $code = $this->reservation->getBillets()->first()->getCode();

        $this->client->request('GET', '/billets/'.$code);

        self::assertResponseStatusCodeSame(404);
    }
}
