<?php

namespace App\Service;

use App\Entity\Film;
use App\Entity\Salle;
use App\Entity\Seance;
use App\Exception\DonneesInvalidesException;
use App\Repository\FilmRepository;
use App\Repository\SalleRepository;
use App\Repository\SeanceRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * User story « Programmation - Programmer une séance ».
 * Règles : film et salle existants, séance dans le futur, prix positif,
 * et surtout pas deux séances en même temps dans la même salle.
 */
class SeanceService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FilmRepository $filmRepository,
        private readonly SalleRepository $salleRepository,
        private readonly SeanceRepository $seanceRepository,
    ) {
    }

    /**
     * @param array<string, mixed> $donnees filmId, salleId, debut (AAAA-MM-JJTHH:MM), langue, prix
     *
     * @throws DonneesInvalidesException
     */
    public function programmer(array $donnees, ?\DateTimeImmutable $maintenant = null): Seance
    {
        $erreurs = [];
        $maintenant ??= new \DateTimeImmutable();

        $film = is_numeric($donnees['filmId'] ?? null) ? $this->filmRepository->find((int) $donnees['filmId']) : null;
        if (!$film instanceof Film) {
            $erreurs['filmId'] = 'Choisissez un film de la banque de films.';
        }

        $salle = is_numeric($donnees['salleId'] ?? null) ? $this->salleRepository->find((int) $donnees['salleId']) : null;
        if (!$salle instanceof Salle) {
            $erreurs['salleId'] = 'Choisissez une salle.';
        }

        $debut = $this->lireDate($donnees['debut'] ?? null);
        if (null === $debut) {
            $erreurs['debut'] = 'La date et l\'heure doivent être au format AAAA-MM-JJ HH:MM.';
        } elseif ($debut <= $maintenant) {
            $erreurs['debut'] = 'Une séance ne peut pas être programmée dans le passé.';
        }

        $langue = is_string($donnees['langue'] ?? null) && '' !== $donnees['langue'] ? $donnees['langue'] : 'VF';
        if (!in_array($langue, Seance::LANGUES, true)) {
            $erreurs['langue'] = 'Langue possible : '.implode(' ou ', Seance::LANGUES).'.';
        }

        $prix = str_replace(',', '.', trim((string) (is_scalar($donnees['prix'] ?? null) ? $donnees['prix'] : '')));
        if (!is_numeric($prix) || (float) $prix < 0 || (float) $prix > 100) {
            $erreurs['prix'] = 'Le prix doit être un montant entre 0 et 100 €.';
        }

        if ([] === $erreurs) {
            $seance = new Seance($film, $salle, $debut, number_format((float) $prix, 2, '.', ''), $langue);
            $conflit = $this->trouverConflit($seance);
            if (null !== $conflit) {
                $erreurs['debut'] = sprintf(
                    'La salle %s est déjà occupée par « %s » de %s à %s.',
                    $salle->getNom(),
                    $conflit->getFilm()->getTitre(),
                    $conflit->getDateHeureDebut()->format('H:i'),
                    $conflit->getDateHeureFin()->format('H:i'),
                );
            }
        }

        if ([] !== $erreurs) {
            throw new DonneesInvalidesException($erreurs);
        }

        $this->entityManager->persist($seance);
        $this->entityManager->flush();

        return $seance;
    }

    /**
     * Cherche une séance de la même salle dont l'horaire chevauche celui de la nouvelle séance.
     */
    public function trouverConflit(Seance $nouvelle): ?Seance
    {
        $debut = $nouvelle->getDateHeureDebut();
        $fin = $nouvelle->getDateHeureFin();

        foreach ($this->seanceRepository->autourDe($nouvelle->getSalle(), $debut, $fin) as $existante) {
            if ($existante !== $nouvelle && $existante->chevauche($debut, $fin)) {
                return $existante;
            }
        }

        return null;
    }

    private function lireDate(mixed $valeur): ?\DateTimeImmutable
    {
        if (!is_string($valeur)) {
            return null;
        }
        $valeur = trim(str_replace('T', ' ', $valeur));
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $valeur);

        return false !== $date && $date->format('Y-m-d H:i') === $valeur ? $date : null;
    }
}
