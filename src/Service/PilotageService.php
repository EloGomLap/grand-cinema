<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Entity\Seance;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Indicateurs de pilotage pour le gestionnaire.
 * Seules les places payées comptent (une réservation en attente n'est pas une entrée).
 */
class PilotageService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * User story « Pilotage - Suivre le taux de remplissage » : par séance, par film et en moyenne.
     *
     * @return array{seances: list<array<string, mixed>>, films: list<array<string, mixed>>, moyenne: float, placesVendues: int, placesOffertes: int}
     */
    public function tauxRemplissage(\DateTimeImmutable $du, \DateTimeImmutable $au): array
    {
        $seances = $this->entityManager->createQueryBuilder()
            ->select('s', 'f', 'sa')->from(Seance::class, 's')
            ->join('s.film', 'f')->join('s.salle', 'sa')
            ->andWhere('s.dateHeureDebut >= :du AND s.dateHeureDebut < :au')
            ->setParameter('du', $du->setTime(0, 0))
            ->setParameter('au', $au->setTime(0, 0)->modify('+1 day'))
            ->orderBy('s.dateHeureDebut', 'ASC')
            ->getQuery()->getResult();
        $vendues = $this->placesPayeesParSeance($seances);

        $lignes = [];
        $parFilm = [];
        $totalVendues = $totalOffertes = 0;
        foreach ($seances as $seance) {
            $nb = $vendues[$seance->getId()] ?? 0;
            $capacite = $seance->getSalle()->getCapacite();
            $lignes[] = [
                'seance' => $seance,
                'vendues' => $nb,
                'capacite' => $capacite,
                'taux' => self::taux($nb, $capacite),
            ];
            $titre = $seance->getFilm()->getTitre();
            $parFilm[$titre] ??= ['film' => $titre, 'seances' => 0, 'vendues' => 0, 'capacite' => 0];
            ++$parFilm[$titre]['seances'];
            $parFilm[$titre]['vendues'] += $nb;
            $parFilm[$titre]['capacite'] += $capacite;
            $totalVendues += $nb;
            $totalOffertes += $capacite;
        }

        $films = array_values(array_map(static fn (array $f) => $f + ['taux' => self::taux($f['vendues'], $f['capacite'])], $parFilm));
        usort($films, static fn ($a, $b) => $b['taux'] <=> $a['taux']);

        return [
            'seances' => $lignes,
            'films' => $films,
            'moyenne' => self::taux($totalVendues, $totalOffertes),
            'placesVendues' => $totalVendues,
            'placesOffertes' => $totalOffertes,
        ];
    }

    public static function taux(int $vendues, int $capacite): float
    {
        return 0 === $capacite ? 0.0 : round(100 * $vendues / $capacite, 1);
    }

    /**
     * @param Seance[] $seances
     *
     * @return array<int, int>
     */
    private function placesPayeesParSeance(array $seances): array
    {
        if ([] === $seances) {
            return [];
        }
        $lignes = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(b.seance) AS seance, COUNT(b.id) AS nb')
            ->from(\App\Entity\Billet::class, 'b')
            ->join('b.reservation', 'r')
            ->andWhere('b.seance IN (:seances)')
            ->andWhere('r.statut = :payee')
            ->setParameter('seances', $seances)
            ->setParameter('payee', Reservation::STATUT_PAYEE)
            ->groupBy('b.seance')
            ->getQuery()->getScalarResult();

        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[(int) $ligne['seance']] = (int) $ligne['nb'];
        }

        return $resultat;
    }
}
