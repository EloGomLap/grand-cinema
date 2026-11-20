<?php

namespace App\Repository;

use App\Entity\Billet;
use App\Entity\Reservation;
use App\Entity\Seance;
use App\Service\ReservationService;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Billet>
 */
class BilletRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Billet::class);
    }

    /**
     * Identifiants des places déjà prises pour une séance (réservations payées ou en cours de paiement).
     *
     * @return int[]
     */
    public function idsPlacesOccupees(Seance $seance, ?\DateTimeImmutable $maintenant = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.place) AS place')
            ->join('b.reservation', 'r')
            ->andWhere('b.seance = :seance')
            ->setParameter('seance', $seance);
        $lignes = $this->filtrerPlacesPrises($qb, $maintenant)->getQuery()->getScalarResult();

        return array_map(static fn (array $l) => (int) $l['place'], $lignes);
    }

    /**
     * Nombre de places prises par séance, en une seule requête (évite une requête par séance).
     *
     * @param Seance[] $seances
     *
     * @return array<int, int> identifiant de séance => places prises
     */
    public function comptesParSeance(array $seances): array
    {
        if ([] === $seances) {
            return [];
        }
        $qb = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.seance) AS seance, COUNT(b.id) AS prises')
            ->join('b.reservation', 'r')
            ->andWhere('b.seance IN (:seances)')
            ->setParameter('seances', $seances)
            ->groupBy('b.seance');
        $lignes = $this->filtrerPlacesPrises($qb)->getQuery()->getScalarResult();

        $comptes = [];
        foreach ($lignes as $ligne) {
            $comptes[(int) $ligne['seance']] = (int) $ligne['prises'];
        }

        return $comptes;
    }

    /**
     * Une place est prise si sa réservation est payée, ou en attente de paiement depuis moins de 15 minutes.
     */
    private function filtrerPlacesPrises(QueryBuilder $qb, ?\DateTimeImmutable $maintenant = null): QueryBuilder
    {
        $limite = ($maintenant ?? new \DateTimeImmutable())->modify(sprintf('-%d minutes', ReservationService::MINUTES_BLOCAGE));

        return $qb
            ->andWhere('r.statut = :payee OR (r.statut = :attente AND r.dateReservation > :limite)')
            ->setParameter('payee', Reservation::STATUT_PAYEE)
            ->setParameter('attente', Reservation::STATUT_EN_ATTENTE)
            ->setParameter('limite', $limite);
    }
}
