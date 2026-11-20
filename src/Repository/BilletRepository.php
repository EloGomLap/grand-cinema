<?php

namespace App\Repository;

use App\Entity\Billet;
use App\Entity\Reservation;
use App\Entity\Seance;
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
    public function idsPlacesOccupees(Seance $seance): array
    {
        $lignes = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.place) AS place')
            ->join('b.reservation', 'r')
            ->andWhere('b.seance = :seance')
            ->andWhere('r.statut != :annulee')
            ->setParameter('seance', $seance)
            ->setParameter('annulee', Reservation::STATUT_ANNULEE)
            ->getQuery()->getScalarResult();

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
        $lignes = $this->createQueryBuilder('b')
            ->select('IDENTITY(b.seance) AS seance, COUNT(b.id) AS prises')
            ->join('b.reservation', 'r')
            ->andWhere('b.seance IN (:seances)')
            ->andWhere('r.statut != :annulee')
            ->setParameter('seances', $seances)
            ->setParameter('annulee', Reservation::STATUT_ANNULEE)
            ->groupBy('b.seance')
            ->getQuery()->getScalarResult();

        $comptes = [];
        foreach ($lignes as $ligne) {
            $comptes[(int) $ligne['seance']] = (int) $ligne['prises'];
        }

        return $comptes;
    }
}
