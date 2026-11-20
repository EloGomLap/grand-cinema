<?php

namespace App\Tests\Functional;

use App\Entity\Reservation;
use App\Entity\Seance;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Réservation - Choisir sa place », dont la protection contre la double réservation.
 */
class ChoisirPlaceTest extends WebTestCase
{
    use BaseDeTestTrait;

    private Seance $seance;

    protected function setUp(): void
    {
        $this->preparerBase();
        $salle = $this->creerSalle('Salle 1', 3, 5);
        $this->seance = $this->creerSeance('Whiplash', $salle, 'tomorrow 20:00', '9.50');
    }

    public function testLeClientChoisitSesPlacesSurLePlan(): void
    {
        $this->client->loginUser($this->utilisateur('client@test.fr'));
        $crawler = $this->client->request('GET', '/seances/'.$this->seance->getId());

        $formulaire = $crawler->selectButton('Réserver les places choisies')->form();
        $formulaire['places'][0]->tick();
        $formulaire['places'][1]->tick();
        $this->client->submit($formulaire);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.recap', 'Places : A1, A2');
        self::assertSelectorTextContains('.recap', '19,00 €');
        self::assertSelectorTextContains('.statut', 'En attente de paiement');
    }

    public function testUnVisiteurDoitSeConnecterPourReserver(): void
    {
        $this->client->request('GET', '/seances/'.$this->seance->getId());

        self::assertSelectorNotExists('#form-reservation');
        self::assertSelectorTextContains('main', 'Connectez-vous pour réserver');
    }

    public function testUnePlaceDejaPriseEstRefuseeAvecUnCode409(): void
    {
        $this->reserverPlaces($this->seance, ['B3']);

        $reponse = $this->reserverViaApi(['B2', 'B3']);

        self::assertSame(409, $reponse['statut']);
        self::assertSame(['B3'], $reponse['json']['placesDejaPrises']);
        self::assertSame(1, $this->em->getRepository(Reservation::class)->count([]), 'aucune réservation partielle');
    }

    public function testLaBaseRefuseUneDoubleVenteMemeSiLaVerificationEstContournee(): void
    {
        // Simule deux requêtes simultanées : on insère directement un billet en double,
        // comme si la vérification applicative avait été passée par les deux clients.
        $this->reserverPlaces($this->seance, ['C1']);
        $doublon = new Reservation($this->seance, 'EN_LIGNE');
        $doublon->ajouterBillet($this->place('C1'), 'code-doublon');
        $this->em->persist($doublon);

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testLesDemandesInvalidesSontRefusees(): void
    {
        self::assertSame('Choisissez au moins une place.', $this->reserverViaApi([])['json']['details']['places']);

        $onze = ['A1', 'A2', 'A3', 'A4', 'A5', 'B1', 'B2', 'B3', 'B4', 'B5', 'C1'];
        self::assertSame('Vous pouvez réserver 10 places au maximum.', $this->reserverViaApi($onze)['json']['details']['places']);

        $autreSalle = $this->creerSalle('Salle 2', 1, 2);
        $this->client->request('POST', '/api/reservations', server: $this->authClient(), content: json_encode([
            'seanceId' => $this->seance->getId(), 'places' => [$autreSalle->getPlaces()->first()->getId()],
        ]));
        self::assertResponseStatusCodeSame(400);
    }

    public function testUneSeancePasseeNEstPlusReservable(): void
    {
        $passee = $this->creerSeance('Bird', $this->seance->getSalle(), '-1 hour');

        $this->client->request('POST', '/api/reservations', server: $this->authClient(), content: json_encode([
            'seanceId' => $passee->getId(), 'places' => [$this->place('A1')->getId()],
        ]));

        self::assertResponseStatusCodeSame(400);
    }

    public function testUneReservationNonPayeeExpireEtLiberePlaces(): void
    {
        $ancienne = $this->reserverPlaces($this->seance, ['A5'], Reservation::STATUT_EN_ATTENTE);
        $this->em->getConnection()->executeStatement('UPDATE reservation SET date_reservation = ? WHERE id = ?', [
            (new \DateTimeImmutable('-20 minutes'))->format('Y-m-d H:i:s'), $ancienne->getId(),
        ]);
        $this->em->clear();

        $reponse = $this->reserverViaApi(['A5']);

        self::assertSame(201, $reponse['statut'], 'la place bloquée depuis plus de 15 minutes est de nouveau disponible');
        self::assertSame('ANNULEE', $this->em->getRepository(Reservation::class)->find($ancienne->getId())->getStatut());
    }

    public function testUnClientNePeutPasVoirLaReservationDUnAutre(): void
    {
        $reservation = $this->reserverPlaces($this->seance, ['A1'], emailClient: 'client@test.fr');

        $this->client->request('GET', '/api/reservations/'.$reservation->getId(), server: ['PHP_AUTH_USER' => 'programmateur@test.fr', 'PHP_AUTH_PW' => 'motdepasse']);
        self::assertResponseStatusCodeSame(403);

        $this->client->request('GET', '/api/reservations/'.$reservation->getId(), server: $this->authClient());
        self::assertResponseIsSuccessful();
    }

    /**
     * @param string[] $libelles
     *
     * @return array{statut: int, json: array<string, mixed>}
     */
    private function reserverViaApi(array $libelles): array
    {
        $ids = array_map(fn (string $l) => $this->place($l)->getId(), $libelles);
        $this->client->request('POST', '/api/reservations', server: $this->authClient(), content: json_encode([
            'seanceId' => $this->seance->getId(), 'places' => $ids,
        ]));
        $reponse = $this->client->getResponse();

        return ['statut' => $reponse->getStatusCode(), 'json' => json_decode($reponse->getContent(), true) ?? []];
    }

    private function place(string $libelle): \App\Entity\Place
    {
        foreach ($this->em->getRepository(\App\Entity\Place::class)->findBy(['salle' => $this->seance->getSalle()->getId()]) as $place) {
            if ($place->getLibelle() === $libelle) {
                return $place;
            }
        }
        throw new \LogicException('Place inconnue '.$libelle);
    }

    /**
     * @return array<string, string>
     */
    private function authClient(): array
    {
        return ['PHP_AUTH_USER' => 'client@test.fr', 'PHP_AUTH_PW' => 'motdepasse', 'CONTENT_TYPE' => 'application/json'];
    }
}
