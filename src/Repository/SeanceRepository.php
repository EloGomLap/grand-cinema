<?php

namespace App\Repository;

use App\Entity\Film;
use App\Entity\Salle;
use App\Entity\Seance;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Seance>
 */
class SeanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Seance::class);
    }

    /**
     * Séances d'une salle qui commencent dans l'intervalle donné (élargi de la durée maximale d'un film),
     * utilisées pour détecter un chevauchement d'horaires.
     *
     * @return Seance[]
     */
    public function autourDe(Salle $salle, \DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.film', 'f')->addSelect('f')
            ->andWhere('s.salle = :salle')
            ->andWhere('s.dateHeureDebut < :fin')
            ->andWhere('s.dateHeureDebut > :debutElargi')
            ->setParameter('salle', $salle)
            ->setParameter('fin', $fin)
            ->setParameter('debutElargi', $debut->modify('-12 hours'))
            ->getQuery()->getResult();
    }

    /**
     * Séances à venir, de la plus proche à la plus lointaine.
     * Filtres facultatifs : un jour précis, un film.
     *
     * @return Seance[]
     */
    public function aVenir(?\DateTimeImmutable $jour = null, ?Film $film = null, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable();
        $qb = $this->createQueryBuilder('s')
            ->join('s.film', 'f')->addSelect('f')
            ->join('s.salle', 'sa')->addSelect('sa')
            ->andWhere('s.dateHeureDebut > :maintenant')
            ->setParameter('maintenant', $maintenant)
            ->orderBy('s.dateHeureDebut', 'ASC');

        if (null !== $jour) {
            $qb->andWhere('s.dateHeureDebut >= :debutJour AND s.dateHeureDebut < :finJour')
                ->setParameter('debutJour', $jour->setTime(0, 0))
                ->setParameter('finJour', $jour->setTime(0, 0)->modify('+1 day'));
        }
        if (null !== $film) {
            $qb->andWhere('s.film = :film')->setParameter('film', $film);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Toutes les séances d'une journée (passées comprises), triées par salle puis par heure.
     *
     * @return Seance[]
     */
    public function duJour(\DateTimeImmutable $jour): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.film', 'f')->addSelect('f')
            ->join('s.salle', 'sa')->addSelect('sa')
            ->andWhere('s.dateHeureDebut >= :debut AND s.dateHeureDebut < :fin')
            ->setParameter('debut', $jour->setTime(0, 0))
            ->setParameter('fin', $jour->setTime(0, 0)->modify('+1 day'))
            ->orderBy('sa.nom', 'ASC')->addOrderBy('s.dateHeureDebut', 'ASC')
            ->getQuery()->getResult();
    }
}
