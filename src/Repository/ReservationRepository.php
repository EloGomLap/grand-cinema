<?php

namespace App\Repository;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Réservations d'un client (hors annulées), de la séance la plus proche à la plus lointaine.
     *
     * @return Reservation[]
     */
    public function duClient(Utilisateur $client): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.seance', 's')->addSelect('s')
            ->join('s.film', 'f')->addSelect('f')
            ->leftJoin('r.billets', 'b')->addSelect('b')
            ->andWhere('r.utilisateur = :client')
            ->andWhere('r.statut != :annulee')
            ->setParameter('client', $client)
            ->setParameter('annulee', Reservation::STATUT_ANNULEE)
            ->orderBy('s.dateHeureDebut', 'ASC')
            ->getQuery()->getResult();
    }
}
