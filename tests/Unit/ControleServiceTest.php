<?php

namespace App\Tests\Unit;

use App\Entity\Film;
use App\Entity\Reservation;
use App\Entity\Salle;
use App\Entity\Seance;
use App\Repository\BilletRepository;
use App\Service\ControleService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Règles de validation d'un billet à l'entrée, testées sans base de données.
 */
class ControleServiceTest extends TestCase
{
    private Reservation $reservation;
    private ControleService $service;
    private \DateTimeImmutable $debut;

    protected function setUp(): void
    {
        $this->debut = new \DateTimeImmutable('2026-12-05 20:00');
        $film = (new Film())->setTitre('Bird')->setDureeMinutes(161);
        $salle = (new Salle('Salle Jazz'))->genererPlan(1, 2);
        $this->reservation = new Reservation(new Seance($film, $salle, $this->debut, '10.00'), 'EN_LIGNE');
        $billet = $this->reservation->ajouterBillet($salle->getPlaces()->first(), 'abc123');

        $repository = $this->createMock(BilletRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $critere) => 'abc123' === $critere['code'] ? $billet : null);
        $this->service = new ControleService($repository, $this->createMock(EntityManagerInterface::class));
    }

    public function testUnBilletPayeEstAccepteUneSeuleFois(): void
    {
        $this->reservation->marquerPayee('CB_EN_LIGNE');

        $premier = $this->service->valider('ABC123 ', $this->debut->modify('-10 minutes'));
        $second = $this->service->valider('abc123', $this->debut->modify('-5 minutes'));

        self::assertSame(ControleService::VALIDE, $premier['resultat']);
        self::assertTrue($premier['accepte']);
        self::assertSame(ControleService::DEJA_UTILISE, $second['resultat']);
        self::assertSame('Billet déjà utilisé à 19:50 : refusez l\'entrée.', $second['message']);
    }

    public function testUnBilletInconnuOuNonPayeEstRefuse(): void
    {
        self::assertSame(ControleService::INCONNU, $this->service->valider('zzz', $this->debut)['resultat']);
        self::assertSame(ControleService::NON_PAYE, $this->service->valider('abc123', $this->debut)['resultat']);
    }

    public function testOnNePeutPasEntrerTropTotNiApresLaSeance(): void
    {
        $this->reservation->marquerPayee('CB_EN_LIGNE');

        self::assertSame(ControleService::TROP_TOT, $this->service->valider('abc123', $this->debut->modify('-2 hours'))['resultat']);
        self::assertSame(ControleService::SEANCE_TERMINEE, $this->service->valider('abc123', $this->debut->modify('+4 hours'))['resultat']);
        self::assertSame(ControleService::VALIDE, $this->service->valider('abc123', $this->debut->modify('+15 minutes'))['resultat'], 'un retardataire peut encore entrer');
    }
}
