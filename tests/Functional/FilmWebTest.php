<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de l'interface web : pages, droits d'accès et formulaires.
 */
class FilmWebTest extends WebTestCase
{
    use BaseDeTestTrait;

    protected function setUp(): void
    {
        $this->preparerBase();
    }

    public function testLaBanqueDeFilmsEstConsultableSansCompte(): void
    {
        $crawler = $this->client->request('GET', '/films');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Banque de films');
        self::assertCount(2, $crawler->filter('article.carte'));
        self::assertSelectorNotExists('a[href="/films/nouveau"]', 'le bouton d\'ajout est masqué aux visiteurs');
    }

    public function testUnVisiteurEstRedirigeVersLaConnexionPourAjouterUnFilm(): void
    {
        $this->client->request('GET', '/films/nouveau');

        self::assertResponseRedirects('/connexion');
    }

    public function testUnClientConnecteNaPasAccesALAjout(): void
    {
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $this->client->request('GET', '/films/nouveau');

        self::assertResponseStatusCodeSame(403);
    }

    public function testLaConnexionParFormulaireFonctionne(): void
    {
        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            '_username' => 'programmateur@test.fr',
            '_password' => 'motdepasse',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="/films/nouveau"]');
    }

    public function testUnProgrammateurAjouteUnFilmDepuisLeFormulaire(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $this->client->request('GET', '/films/nouveau');
        $this->client->submitForm('Enregistrer', [
            'film[titre]' => 'Round Midnight',
            'film[dureeMinutes]' => '133',
            'film[genre]' => 'Drame musical',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Round Midnight');
        self::assertSelectorTextContains('.flash.succes', 'a été ajouté');
    }

    public function testLeFormulaireAfficheLesErreurs(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $this->client->request('GET', '/films/nouveau');
        $this->client->submitForm('Enregistrer', ['film[titre]' => '', 'film[dureeMinutes]' => '']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreur-champ', 'Le titre est obligatoire.');
    }

    public function testUnProgrammateurModifiePuisSupprimeUnFilm(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $id = $this->film('Bird')->getId();

        $this->client->request('GET', '/films/'.$id.'/modifier');
        $this->client->submitForm('Enregistrer', ['film[genre]' => 'Biopic musical']);
        self::assertResponseRedirects('/films/'.$id);

        $this->client->followRedirect();
        $this->client->submitForm('Supprimer');
        self::assertResponseRedirects('/films');
        $this->em->clear();
        self::assertNull($this->film('Bird'));
    }

    public function testUneSuppressionSansJetonCsrfEstRefusee(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $id = $this->film('Bird')->getId();

        $this->client->request('POST', '/films/'.$id.'/supprimer', ['_token' => 'faux']);

        self::assertResponseStatusCodeSame(403);
        $this->em->clear();
        self::assertNotNull($this->film('Bird'));
    }

    public function testUnFilmInexistantRenvoieUne404(): void
    {
        $this->client->request('GET', '/films/999');

        self::assertResponseStatusCodeSame(404);
    }
}
