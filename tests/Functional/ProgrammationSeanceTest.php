<?php

namespace App\Tests\Functional;

use App\Entity\Salle;
use App\Entity\Seance;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Programmation - Programmer une séance » (interface web et API).
 */
class ProgrammationSeanceTest extends WebTestCase
{
    use BaseDeTestTrait;

    private Salle $salle;

    protected function setUp(): void
    {
        $this->preparerBase();
        $this->salle = $this->creerSalle('Salle 1', 3, 4);
    }

    public function testLeProgrammateurProgrammeUneSeanceDepuisLeFormulaire(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $this->client->request('GET', '/programmation/seances');
        $demain = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');

        $this->client->submitForm('Programmer la séance', [
            'seance[filmId]' => $this->film('Bird')->getId(),
            'seance[salleId]' => $this->salle->getId(),
            'seance[debut]' => $demain.'T20:30',
            'seance[langue]' => 'VOSTFR',
            'seance[prix]' => '9,50',
        ]);

        self::assertResponseRedirects('/programmation/seances');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.succes', 'Séance programmée');
        $seance = $this->em->getRepository(Seance::class)->findOneBy([]);
        self::assertSame('9.50', $seance->getPrix());
        self::assertSame($demain.' 20:30', $seance->getDateHeureDebut()->format('Y-m-d H:i'));
    }

    public function testDeuxSeancesNePeuventPasSeChevaucherDansLaMemeSalle(): void
    {
        $demain = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->programmerViaApi(['filmId' => $this->film('Bird')->getId(), 'debut' => $demain.'T14:00']);

        // Bird dure 161 min + 20 min entre deux séances : la salle est occupée jusqu'à 17:01
        $reponse = $this->programmerViaApi(['filmId' => $this->film('Whiplash')->getId(), 'debut' => $demain.'T16:30']);

        self::assertSame(400, $reponse['statut']);
        self::assertStringContainsString('déjà occupée par « Bird » de 14:00 à 17:01', $reponse['json']['details']['debut']);

        $ok = $this->programmerViaApi(['filmId' => $this->film('Whiplash')->getId(), 'debut' => $demain.'T17:05']);
        self::assertSame(201, $ok['statut']);
    }

    public function testLesDonneesInvalidesSontToutesSignalees(): void
    {
        $reponse = $this->programmerViaApi(['filmId' => 999, 'salleId' => 999, 'debut' => '2020-01-01T10:00', 'prix' => '-3', 'langue' => 'VO']);

        self::assertSame(400, $reponse['statut']);
        self::assertSame(['filmId', 'salleId', 'debut', 'langue', 'prix'], array_keys($reponse['json']['details']));
    }

    public function testUneSeanceDansLePasseEstRefusee(): void
    {
        $reponse = $this->programmerViaApi(['filmId' => $this->film('Bird')->getId(), 'debut' => '2020-01-01T10:00']);

        self::assertSame('Une séance ne peut pas être programmée dans le passé.', $reponse['json']['details']['debut']);
    }

    public function testSeulsLesProgrammateursPeuventProgrammer(): void
    {
        $this->client->loginUser($this->utilisateur('guichetier@test.fr'));
        $this->client->request('GET', '/programmation/seances');
        self::assertResponseStatusCodeSame(403);

        $this->client->request('POST', '/api/seances', server: ['CONTENT_TYPE' => 'application/json'], content: '{}');
        self::assertResponseStatusCodeSame(401);
    }

    public function testUnFilmProgrammeNePeutPasEtreSupprime(): void
    {
        $this->programmerViaApi(['filmId' => $this->film('Bird')->getId(), 'debut' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d').'T14:00']);

        $this->client->request('DELETE', '/api/films/'.$this->film('Bird')->getId(), server: $this->authProgrammateur());

        self::assertResponseStatusCodeSame(409);
    }

    public function testLeDetailDUneSeanceEstPublic(): void
    {
        $creee = $this->programmerViaApi(['filmId' => $this->film('Whiplash')->getId(), 'debut' => (new \DateTimeImmutable('tomorrow'))->format('Y-m-d').'T18:00']);

        $this->client->request('GET', '/api/seances/'.$creee['json']['id']);

        self::assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        self::assertSame('Whiplash', $json['film']['titre']);
        self::assertSame(12, $json['salle']['capacite']);
    }

    /**
     * @param array<string, mixed> $donnees
     *
     * @return array{statut: int, json: array<string, mixed>}
     */
    private function programmerViaApi(array $donnees): array
    {
        $donnees += ['salleId' => $this->salle->getId(), 'langue' => 'VF', 'prix' => '8.50'];
        $this->client->request('POST', '/api/seances', server: $this->authProgrammateur() + ['CONTENT_TYPE' => 'application/json'], content: json_encode($donnees));
        $reponse = $this->client->getResponse();

        return ['statut' => $reponse->getStatusCode(), 'json' => json_decode($reponse->getContent(), true) ?? []];
    }

    /**
     * @return array<string, string>
     */
    private function authProgrammateur(): array
    {
        return ['PHP_AUTH_USER' => 'programmateur@test.fr', 'PHP_AUTH_PW' => 'motdepasse'];
    }
}
