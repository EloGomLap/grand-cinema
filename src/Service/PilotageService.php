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

    /**
     * User story « Pilotage - Suivre la fréquentation » : entrées (billets payés) par jour et par film,
     * part de la vente en ligne (objectif du projet : réduire les files au guichet), recettes,
     * et entrées réellement contrôlées en salle.
     *
     * @return array{entrees: int, recettes: float, partEnLigne: float, controlees: int, parJour: list<array{jour: string, entrees: int, recettes: float}>, parFilm: list<array{film: string, entrees: int}>, parCanal: array<string, int>}
     */
    public function frequentation(\DateTimeImmutable $du, \DateTimeImmutable $au): array
    {
        $billets = $this->entityManager->createQueryBuilder()
            ->select('b', 'r', 's', 'f')->from(\App\Entity\Billet::class, 'b')
            ->join('b.reservation', 'r')->join('b.seance', 's')->join('s.film', 'f')
            ->andWhere('r.statut = :payee')
            ->andWhere('s.dateHeureDebut >= :du AND s.dateHeureDebut < :au')
            ->setParameter('payee', Reservation::STATUT_PAYEE)
            ->setParameter('du', $du->setTime(0, 0))
            ->setParameter('au', $au->setTime(0, 0)->modify('+1 day'))
            ->getQuery()->getResult();

        $parJour = $parFilm = [];
        $parCanal = ['EN_LIGNE' => 0, 'GUICHET' => 0];
        $recettes = 0.0;
        $controlees = 0;
        for ($jour = $du->setTime(0, 0); $jour <= $au; $jour = $jour->modify('+1 day')) {
            $parJour[$jour->format('Y-m-d')] = ['jour' => $jour->format('Y-m-d'), 'entrees' => 0, 'recettes' => 0.0];
        }
        foreach ($billets as $billet) {
            $seance = $billet->getSeance();
            $prix = (float) $seance->getPrix();
            $jour = $seance->getDateHeureDebut()->format('Y-m-d');
            ++$parJour[$jour]['entrees'];
            $parJour[$jour]['recettes'] += $prix;
            $titre = $seance->getFilm()->getTitre();
            $parFilm[$titre] = ($parFilm[$titre] ?? 0) + 1;
            ++$parCanal[$billet->getReservation()->getCanal()];
            $recettes += $prix;
            if (null !== $billet->getDateValidation()) {
                ++$controlees;
            }
        }
        arsort($parFilm);
        $entrees = count($billets);

        return [
            'entrees' => $entrees,
            'recettes' => round($recettes, 2),
            'partEnLigne' => self::taux($parCanal['EN_LIGNE'], $entrees),
            'controlees' => $controlees,
            'parJour' => array_values($parJour),
            'parFilm' => array_map(static fn (string $film, int $nb) => ['film' => $film, 'entrees' => $nb], array_keys($parFilm), $parFilm),
            'parCanal' => $parCanal,
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
