<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Consultation - Consulter les séances » (client, sans compte).
 */
class ConsultationSeancesTest extends WebTestCase
{
    use BaseDeTestTrait;

    private string $demain;
    private string $apresDemain;

    protected function setUp(): void
    {
        $this->preparerBase();
        $this->demain = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->apresDemain = (new \DateTimeImmutable('+2 days'))->format('Y-m-d');
        $salle = $this->creerSalle('Salle 1', 2, 5);
        $this->creerSeance('Bird', $salle, $this->demain.' 14:00');
        $this->creerSeance('Whiplash', $salle, $this->demain.' 20:00');
        $this->creerSeance('Whiplash', $salle, $this->apresDemain.' 20:00');
        $this->creerSeance('Bird', $salle, '-2 days 14:00');
    }

    public function testLesSeancesAVenirSontListeesParJourSansCompte(): void
    {
        $crawler = $this->client->request('GET', '/seances');

        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('section.jour'));
        self::assertCount(3, $crawler->filter('article.seance'), 'la séance passée n\'est pas affichée');
    }

    public function testOnPeutFiltrerParJourEtParFilm(): void
    {
        $crawler = $this->client->request('GET', '/seances?date='.$this->demain.'&film='.$this->film('Whiplash')->getId());

        self::assertCount(1, $crawler->filter('article.seance'));
        self::assertSelectorTextContains('article.seance', 'Whiplash');
        self::assertSelectorTextContains('article.seance', '20:00');
    }

    public function testLApiListeEtFiltreLesSeances(): void
    {
        $this->client->request('GET', '/api/seances?filmId='.$this->film('Whiplash')->getId());
        $json = json_decode($this->client->getResponse()->getContent(), true);

        self::assertResponseIsSuccessful();
        self::assertCount(2, $json);
        self::assertSame($this->demain.'T20:00', substr($json[0]['debut'], 0, 16), 'triées de la plus proche à la plus lointaine');

        $this->client->request('GET', '/api/seances?filmId=999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testLaPageDUneSeanceAfficheLesInformationsUtiles(): void
    {
        $crawler = $this->client->request('GET', '/seances');
        $this->client->click($crawler->filter('article.seance a')->first()->link());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bird');
        self::assertSelectorTextContains('main', 'Prix de la place : 8,50 €');
    }

    public function testLAccueilMeneAuxSeances(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/seances');
    }
}
