<?php

namespace App\Entity;

use App\Repository\SeanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une séance : un film projeté dans une salle à une date et une heure.
 * Correspond à la table "seance" du script database/schema.sql.
 */
#[ORM\Entity(repositoryClass: SeanceRepository::class)]
#[ORM\Table(name: 'seance')]
#[ORM\UniqueConstraint(name: 'seance_salle_horaire', columns: ['salle_id', 'date_heure_debut'])]
#[ORM\Index(name: 'seance_debut', columns: ['date_heure_debut'])]
class Seance
{
    public const LANGUES = ['VF', 'VOSTFR'];

    /** Temps entre deux séances : bandes-annonces, sortie du public et nettoyage */
    public const MINUTES_ENTRE_SEANCES = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Film $film;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Salle $salle;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateHeureDebut;

    #[ORM\Column(length: 10)]
    private string $langue = 'VF';

    /** Prix d'une place, en euros (NUMERIC(6,2) : jamais de nombre flottant pour de l'argent) */
    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2)]
    private string $prix = '0.00';

    public function __construct(Film $film, Salle $salle, \DateTimeImmutable $dateHeureDebut, string $prix, string $langue = 'VF')
    {
        $this->film = $film;
        $this->salle = $salle;
        $this->dateHeureDebut = $dateHeureDebut;
        $this->prix = $prix;
        $this->langue = $langue;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFilm(): Film
    {
        return $this->film;
    }

    public function getSalle(): Salle
    {
        return $this->salle;
    }

    public function getDateHeureDebut(): \DateTimeImmutable
    {
        return $this->dateHeureDebut;
    }

    /**
     * Heure à laquelle la salle est de nouveau libre : fin du film + temps entre deux séances.
     */
    public function getDateHeureFin(): \DateTimeImmutable
    {
        $minutes = ($this->film->getDureeMinutes() ?? 0) + self::MINUTES_ENTRE_SEANCES;

        return $this->dateHeureDebut->modify(sprintf('+%d minutes', $minutes));
    }

    public function chevauche(\DateTimeImmutable $debut, \DateTimeImmutable $fin): bool
    {
        return $debut < $this->getDateHeureFin() && $fin > $this->dateHeureDebut;
    }

    public function estPassee(?\DateTimeImmutable $maintenant = null): bool
    {
        return $this->dateHeureDebut <= ($maintenant ?? new \DateTimeImmutable());
    }

    public function getLangue(): string
    {
        return $this->langue;
    }

    /**
     * Prix normalisé à deux décimales (SQLite ne conserve pas les zéros de fin).
     */
    public function getPrix(): string
    {
        return number_format((float) $this->prix, 2, '.', '');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'film' => ['id' => $this->film->getId(), 'titre' => $this->film->getTitre(), 'dureeMinutes' => $this->film->getDureeMinutes()],
            'salle' => $this->salle->toArray(),
            'debut' => $this->dateHeureDebut->format(\DateTimeInterface::ATOM),
            'fin' => $this->getDateHeureFin()->format(\DateTimeInterface::ATOM),
            'langue' => $this->langue,
            'prix' => (float) $this->prix,
        ];
    }
}
