<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Entity\Seance;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Paiement - Plusieurs moyens de paiement au guichet ».
 */
class PaiementGuichetTest extends WebTestCase
{
    use BaseDeTestTrait;

    private Seance $seance;

    protected function setUp(): void
    {
        $this->preparerBase();
        $this->seance = $this->creerSeance('Bird', $this->creerSalle('Salle 1', 5, 6), 'tomorrow 20:00', '8.50');
        $this->client->loginUser($this->utilisateur('guichetier@test.fr'));
    }

    public function testVenteEnEspecesAvecMonnaieARendreEtAttributionAutomatique(): void
    {
        $this->client->request('GET', '/guichet/seances/'.$this->seance->getId());
        $this->client->submitForm('Encaisser et éditer les billets', [
            'nombre' => 2,
            'mode' => 'ESPECES',
            'montantRecu' => '20',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.succes', 'Vente enregistrée : 2 places, 17,00 € (Espèces). Monnaie à rendre : 3,00 €.');
        self::assertSelectorCount(2, '.billets .billet');

        $vente = $this->em->getRepository(Reservation::class)->findOneBy([]);
        self::assertSame('GUICHET', $vente->getCanal());
        self::assertNull($vente->getUtilisateur(), 'client sans compte');
        $places = array_map(static fn ($b) => $b->getPlace()->getLibelle(), $vente->getBillets()->toArray());
        self::assertSame(['C1', 'C2'], $places, 'places côte à côte dans le rang du milieu');
    }

    public function testVenteParCarteDePlacesChoisiesSurLePlan(): void
    {
        $crawler = $this->client->request('GET', '/guichet/seances/'.$this->seance->getId());
        $formulaire = $crawler->selectButton('Encaisser et éditer les billets')->form(['mode' => 'CB']);
        $formulaire['places'][0]->tick();
        $this->client->submit($formulaire);

        self::assertResponseRedirects();
        $vente = $this->em->getRepository(Reservation::class)->findOneBy([]);
        self::assertSame('CB', $vente->getModePaiement());
        self::assertTrue($vente->estPayee());
    }

    public function testDesEspecesInsuffisantesNEnregistrentRien(): void
    {
        $this->client->request('GET', '/guichet/seances/'.$this->seance->getId());
        $this->client->submitForm('Encaisser et éditer les billets', ['nombre' => 2, 'mode' => 'ESPECES', 'montantRecu' => '10']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.flash.erreur', 'doit couvrir le total de 17,00 €');
        self::assertSame(0, $this->em->getRepository(Reservation::class)->count([]), 'la réservation est annulée avec le paiement (transaction)');
    }

    public function testUnClientNePeutPasVendreAuGuichet(): void
    {
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $this->client->request('POST', '/guichet/seances/'.$this->seance->getId().'/vente');

        self::assertResponseStatusCodeSame(403);
    }
}
