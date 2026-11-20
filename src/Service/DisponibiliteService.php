<?php

namespace App\Service;

use App\Entity\Salle;
use App\Entity\Seance;
use App\Repository\SalleRepository;
use App\Repository\SeanceRepository;

/**
 * User story « Programmation - Voir la disponibilité des salles ».
 * Pour une journée, liste par salle les créneaux occupés et les créneaux libres
 * (entre l'ouverture et la fermeture du cinéma).
 */
class DisponibiliteService
{
    public const OUVERTURE = '10:00';
    public const FERMETURE = '23:59';

    public function __construct(
        private readonly SalleRepository $salleRepository,
        private readonly SeanceRepository $seanceRepository,
    ) {
    }

    /**
     * @return list<array{salle: Salle, seances: Seance[], libres: list<array{debut: \DateTimeImmutable, fin: \DateTimeImmutable, minutes: int}>}>
     */
    public function pourLaJournee(\DateTimeImmutable $jour): array
    {
        $parSalle = [];
        foreach ($this->seanceRepository->duJour($jour) as $seance) {
            $parSalle[$seance->getSalle()->getId()][] = $seance;
        }

        $resultat = [];
        foreach ($this->salleRepository->toutes() as $salle) {
            $seances = $parSalle[$salle->getId()] ?? [];
            $resultat[] = [
                'salle' => $salle,
                'seances' => $seances,
                'libres' => $this->creneauxLibres($jour, $seances),
            ];
        }

        return $resultat;
    }

    /**
     * Les « trous » entre les séances, de l'ouverture à la fermeture.
     *
     * @param Seance[] $seances triées par heure de début
     *
     * @return list<array{debut: \DateTimeImmutable, fin: \DateTimeImmutable, minutes: int}>
     */
    public function creneauxLibres(\DateTimeImmutable $jour, array $seances): array
    {
        $curseur = new \DateTimeImmutable($jour->format('Y-m-d').' '.self::OUVERTURE);
        $fermeture = new \DateTimeImmutable($jour->format('Y-m-d').' '.self::FERMETURE);
        $libres = [];

        foreach ($seances as $seance) {
            if ($seance->getDateHeureDebut() > $curseur) {
                $libres[] = $this->creneau($curseur, $seance->getDateHeureDebut());
            }
            $curseur = max($curseur, $seance->getDateHeureFin());
        }
        if ($curseur < $fermeture) {
            $libres[] = $this->creneau($curseur, $fermeture);
        }

        return $libres;
    }

    /**
     * Version JSON pour l'API.
     *
     * @return list<array<string, mixed>>
     */
    public function enTableau(\DateTimeImmutable $jour): array
    {
        return array_map(static fn (array $ligne) => [
            'salle' => $ligne['salle']->toArray(),
            'seances' => array_map(static fn (Seance $s) => [
                'id' => $s->getId(),
                'film' => $s->getFilm()->getTitre(),
                'debut' => $s->getDateHeureDebut()->format('H:i'),
                'fin' => $s->getDateHeureFin()->format('H:i'),
            ], $ligne['seances']),
            'creneauxLibres' => array_map(static fn (array $c) => [
                'debut' => $c['debut']->format('H:i'),
                'fin' => $c['fin']->format('H:i'),
                'minutes' => $c['minutes'],
            ], $ligne['libres']),
        ], $this->pourLaJournee($jour));
    }

    /**
     * @return array{debut: \DateTimeImmutable, fin: \DateTimeImmutable, minutes: int}
     */
    private function creneau(\DateTimeImmutable $debut, \DateTimeImmutable $fin): array
    {
        return ['debut' => $debut, 'fin' => $fin, 'minutes' => intdiv($fin->getTimestamp() - $debut->getTimestamp(), 60)];
    }
}
