<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Consultation - Consulter les séances au guichet ».
 */
class GuichetConsultationTest extends WebTestCase
{
    use BaseDeTestTrait;

    public function testLeGuichetierVoitToutesLesSeancesDuJourDansLOrdre(): void
    {
        $this->preparerBase();
        $salle1 = $this->creerSalle('Salle 1', 2, 5);
        $salle2 = $this->creerSalle('Salle 2', 2, 5);
        $this->creerSeance('Whiplash', $salle2, 'tomorrow 21:00');
        $this->creerSeance('Bird', $salle1, 'tomorrow 14:00');
        $this->creerSeance('Bird', $salle1, '+3 days 14:00');

        $this->client->loginUser($this->utilisateur('guichetier@test.fr'));
        $crawler = $this->client->request('GET', '/guichet?date='.(new \DateTimeImmutable('tomorrow'))->format('Y-m-d'));

        self::assertResponseIsSuccessful();
        $lignes = $crawler->filter('#seances-guichet tbody tr');
        self::assertCount(2, $lignes);
        self::assertStringContainsString('14:00', $lignes->eq(0)->text());
        self::assertStringContainsString('Salle 2', $lignes->eq(1)->text());
    }

    public function testUneSeanceCommenceeEstSignalee(): void
    {
        $this->preparerBase();
        $this->creerSeance('Bird', $this->creerSalle('Salle 1', 2, 5), '-10 minutes');

        $this->client->loginUser($this->utilisateur('guichetier@test.fr'));
        $this->client->request('GET', '/guichet');

        self::assertSelectorTextContains('#seances-guichet tr.commencee', 'Commencée');
    }

    public function testLeGuichetEstReserveAuPersonnelDuGuichet(): void
    {
        $this->preparerBase();
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $this->client->request('GET', '/guichet');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->utilisateur('admin@test.fr'));
        $this->client->request('GET', '/guichet');
        self::assertResponseIsSuccessful();
    }
}
