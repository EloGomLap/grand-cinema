<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Contrôle - Présenter son billet ».
 */
class PresenterBilletTest extends WebTestCase
{
    use BaseDeTestTrait;

    private Reservation $reservation;

    protected function setUp(): void
    {
        $this->preparerBase();
        $seance = $this->creerSeance('Bird', $this->creerSalle('Salle 1', 2, 5), 'tomorrow 18:00');
        $this->reservation = $this->reserverPlaces($seance, ['A4'], Reservation::STATUT_PAYEE, 'client@test.fr');
    }

    public function testLeBilletAfficheLaSeanceLaPlaceEtLeQrCode(): void
    {
        $code = $this->reservation->getBillets()->first()->getCode();

        $this->client->request('GET', '/billets/'.$code);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.billet .place', 'A4');
        self::assertSelectorExists('.qr[data-code="'.$code.'"]');
        self::assertSelectorTextContains('.etat', 'Billet valable');
        self::assertSelectorExists('#presenter');
    }

    public function testUnBilletDejaUtiliseLIndique(): void
    {
        $billet = $this->reservation->getBillets()->first();
        $billet->valider(new \DateTimeImmutable('today 17:50'));
        $this->em->flush();

        $this->client->request('GET', '/billets/'.$billet->getCode());

        self::assertSelectorTextContains('.etat', 'Billet utilisé');
    }

    public function testUnCodeInventeNeDonneRien(): void
    {
        $this->client->request('GET', '/billets/'.str_repeat('a', 32));

        self::assertResponseStatusCodeSame(404);
    }
}
