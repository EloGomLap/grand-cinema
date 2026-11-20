<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Programmation - Renseigner la fiche d'un film » :
 * la fiche affiche les informations du film et ses prochaines séances.
 */
class FicheFilmTest extends WebTestCase
{
    use BaseDeTestTrait;

    protected function setUp(): void
    {
        $this->preparerBase();
    }

    public function testLaFicheAfficheLesProchainesSeancesDuFilm(): void
    {
        $salle = $this->creerSalle('Salle Jazz', 2, 4);
        $demain = (new \DateTimeImmutable('tomorrow'))->format('Y-m-d');
        $this->creerSeance('Bird', $salle, $demain.' 18:00', '10.00');
        $this->creerSeance('Bird', $salle, '-1 day 18:00');
        $this->creerSeance('Whiplash', $salle, $demain.' 22:00');

        $crawler = $this->client->request('GET', '/films/'.$this->film('Bird')->getId());

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('ul.seances li'), 'ni la séance passée ni celle d\'un autre film');
        self::assertSelectorTextContains('ul.seances', 'Salle Jazz - VF - 10,00 €');
    }

    public function testUnFilmSansSeanceLIndique(): void
    {
        $this->client->request('GET', '/films/'.$this->film('Whiplash')->getId());

        self::assertSelectorTextContains('main', 'pas encore programmé');
    }

    public function testLeProgrammateurComplèteLaFicheEtLAfficheApparait(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $id = $this->film('Whiplash')->getId();
        $this->client->request('GET', '/films/'.$id.'/modifier');
        $this->client->submitForm('Enregistrer', [
            'film[synopsis]' => 'Un jeune batteur de jazz et son professeur.',
            'film[afficheUrl]' => 'https://exemple.fr/whiplash.jpg',
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('main', 'Un jeune batteur de jazz');
        self::assertSelectorExists('img.affiche[src="https://exemple.fr/whiplash.jpg"]');
    }
}
