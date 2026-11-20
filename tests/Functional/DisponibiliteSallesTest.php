<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Programmation - Voir la disponibilité des salles ».
 */
class DisponibiliteSallesTest extends WebTestCase
{
    use BaseDeTestTrait;

    private string $demain;

    protected function setUp(): void
    {
        $this->preparerBase();
        $this->demain = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $salle1 = $this->creerSalle('Salle 1', 2, 5);
        $this->creerSalle('Salle 2', 2, 5);
        $this->creerSeance('Whiplash', $salle1, $this->demain.' 14:00');
        $this->creerSeance('Bird', $salle1, $this->demain.' 20:00');
    }

    public function testLesCreneauxLibresEtOccupesSontCalculesParSalle(): void
    {
        $this->client->request('GET', '/api/salles/disponibilite?date='.$this->demain, server: ['PHP_AUTH_USER' => 'programmateur@test.fr', 'PHP_AUTH_PW' => 'motdepasse']);

        self::assertResponseIsSuccessful();
        $json = json_decode($this->client->getResponse()->getContent(), true);
        [$salle1, $salle2] = $json['salles'];

        // Whiplash 107 min + 20 : 14:00 - 16:07 ; Bird 161 min + 20 : 20:00 - 23:01
        self::assertSame([['debut' => '14:00', 'fin' => '16:07'], ['debut' => '20:00', 'fin' => '23:01']],
            array_map(static fn ($s) => ['debut' => $s['debut'], 'fin' => $s['fin']], $salle1['seances']));
        self::assertSame(['10:00-14:00', '16:07-20:00', '23:01-23:59'],
            array_map(static fn ($c) => $c['debut'].'-'.$c['fin'], $salle1['creneauxLibres']));
        self::assertSame([['debut' => '10:00', 'fin' => '23:59', 'minutes' => 839]], $salle2['creneauxLibres'], 'salle vide : libre toute la journée');
    }

    public function testLaPageDeDisponibiliteEstReserveeALaProgrammation(): void
    {
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $this->client->request('GET', '/programmation/salles');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $this->client->request('GET', '/programmation/salles?date='.$this->demain);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, 'article.salle-dispo');
        self::assertSelectorTextContains('article.salle-dispo', 'Whiplash');
    }

    public function testUneDateInvalideAfficheAujourdHui(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $this->client->request('GET', '/programmation/salles?date=31/02/2026');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Journée du '.(new \DateTimeImmutable('today'))->format('d/m/Y'));
    }
}
