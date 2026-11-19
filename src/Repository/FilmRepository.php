<?php

namespace App\Repository;

use App\Entity\Film;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Film>
 */
class FilmRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Film::class);
    }

    /**
     * Liste des films triés par titre, éventuellement filtrés par genre.
     *
     * @return Film[]
     */
    public function rechercher(?string $genre = null): array
    {
        $qb = $this->createQueryBuilder('f')->orderBy('f.titre', 'ASC');

        if (null !== $genre && '' !== $genre) {
            // Paramètre lié : la valeur n'est jamais concaténée dans la requête (pas d'injection SQL)
            $qb->andWhere('LOWER(f.genre) = LOWER(:genre)')->setParameter('genre', $genre);
        }

        return $qb->getQuery()->getResult();
    }
}
