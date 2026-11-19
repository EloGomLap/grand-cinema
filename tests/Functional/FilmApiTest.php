<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Tests fonctionnels de l'API : de la requête HTTP jusqu'à la base de données.
 */
class FilmApiTest extends WebTestCase
{
    use BaseDeTestTrait;

    /** @var array<string, string> */
    private array $auth = [];

    protected function setUp(): void
    {
        $this->preparerBase();
    }

    public function testLaListeDesFilmsEstPubliqueEtTrieeParTitre(): void
    {
        $this->client->request('GET', '/api/films');

        self::assertResponseIsSuccessful();
        $films = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['Bird', 'Whiplash'], array_column($films, 'titre'));
    }

    public function testLaListePeutEtreFiltreeParGenre(): void
    {
        $this->client->request('GET', '/api/films?genre=biopic');

        $films = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame(['Bird'], array_column($films, 'titre'));
    }

    public function testUnFilmInexistantRenvoieUne404Json(): void
    {
        $this->client->request('GET', '/api/films/999');

        self::assertResponseStatusCodeSame(404);
        self::assertJson($this->client->getResponse()->getContent());
    }

    public function testUnVisiteurNonConnecteNePeutPasAjouterDeFilm(): void
    {
        $this->envoyer('POST', '/api/films', ['titre' => 'Bird', 'dureeMinutes' => 161]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnClientNePeutPasAjouterDeFilm(): void
    {
        $this->seConnecter('client@test.fr');
        $this->envoyer('POST', '/api/films', ['titre' => 'Bird', 'dureeMinutes' => 161]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUnProgrammateurAjouteUnFilm(): void
    {
        $this->seConnecter('programmateur@test.fr');
        $this->envoyer('POST', '/api/films', ['titre' => 'Round Midnight', 'dureeMinutes' => 133]);

        self::assertResponseStatusCodeSame(201);
        self::assertResponseHasHeader('Location');
        self::assertNotNull($this->film('Round Midnight'), 'le film est bien en base');
    }

    public function testDesDonneesInvalidesRenvoientUne400AvecLeDetail(): void
    {
        $this->seConnecter('programmateur@test.fr');
        $this->envoyer('POST', '/api/films', ['titre' => '', 'dureeMinutes' => -5]);

        self::assertResponseStatusCodeSame(400);
        $reponse = json_decode($this->client->getResponse()->getContent(), true);
        self::assertArrayHasKey('titre', $reponse['details']);
        self::assertArrayHasKey('dureeMinutes', $reponse['details']);
    }

    public function testUnJsonMalFormeRenvoieUne400(): void
    {
        $this->seConnecter('programmateur@test.fr');
        $this->client->request('POST', '/api/films', server: ['CONTENT_TYPE' => 'application/json'] + $this->auth, content: '{pas du json');

        self::assertResponseStatusCodeSame(400);
    }

    public function testUnProgrammateurModifiePuisSupprimeUnFilm(): void
    {
        $this->seConnecter('programmateur@test.fr');
        $id = $this->film('Bird')->getId();

        $this->envoyer('PUT', '/api/films/'.$id, ['classification' => '-12']);
        self::assertResponseIsSuccessful();
        self::assertSame('-12', json_decode($this->client->getResponse()->getContent(), true)['classification']);

        $this->client->request('DELETE', '/api/films/'.$id, server: $this->auth);
        self::assertResponseStatusCodeSame(204);
        $this->em->clear();
        self::assertNull($this->film('Bird'));
    }

    public function testLaDocumentationSwaggerEstAccessible(): void
    {
        $this->client->request('GET', '/api/doc');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#swagger-ui');
    }

    /**
     * L'API est sans session : chaque requête porte ses identifiants (HTTP Basic),
     * exactement comme depuis Swagger ou une application cliente.
     */
    private function seConnecter(string $email): void
    {
        $this->auth = ['PHP_AUTH_USER' => $email, 'PHP_AUTH_PW' => 'motdepasse'];
    }

    private function envoyer(string $methode, string $url, array $donnees): void
    {
        $this->client->request($methode, $url, server: ['CONTENT_TYPE' => 'application/json'] + $this->auth, content: json_encode($donnees));
    }
}
