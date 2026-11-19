<?php

namespace App\Entity;

use App\Repository\FilmRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Un film du catalogue (la « banque de films » du cinéma).
 * Correspond à la table "film" du script database/schema.sql.
 */
#[ORM\Entity(repositoryClass: FilmRepository::class)]
#[ORM\Table(name: 'film')]
class Film
{
    public const CLASSIFICATIONS = ['Tous publics', '-12', '-16', '-18'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 200, maxMessage: 'Le titre ne doit pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $synopsis = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La durée est obligatoire.')]
    #[Assert\Positive(message: 'La durée doit être un nombre de minutes positif.')]
    #[Assert\LessThanOrEqual(value: 600, message: 'La durée ne peut pas dépasser {{ compared_value }} minutes.')]
    private ?int $dureeMinutes = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $realisateur = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $genre = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(choices: self::CLASSIFICATIONS, message: 'Classification invalide : choisissez parmi {{ choices }}.')]
    private ?string $classification = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Url(requireTld: true, message: "L'URL de l'affiche n'est pas valide.")]
    private ?string $afficheUrl = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateSortie = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getSynopsis(): ?string
    {
        return $this->synopsis;
    }

    public function setSynopsis(?string $synopsis): static
    {
        $this->synopsis = $synopsis;

        return $this;
    }

    public function getDureeMinutes(): ?int
    {
        return $this->dureeMinutes;
    }

    public function setDureeMinutes(?int $dureeMinutes): static
    {
        $this->dureeMinutes = $dureeMinutes;

        return $this;
    }

    public function getRealisateur(): ?string
    {
        return $this->realisateur;
    }

    public function setRealisateur(?string $realisateur): static
    {
        $this->realisateur = $realisateur;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): static
    {
        $this->genre = $genre;

        return $this;
    }

    public function getClassification(): ?string
    {
        return $this->classification;
    }

    public function setClassification(?string $classification): static
    {
        $this->classification = $classification;

        return $this;
    }

    public function getAfficheUrl(): ?string
    {
        return $this->afficheUrl;
    }

    public function setAfficheUrl(?string $afficheUrl): static
    {
        $this->afficheUrl = $afficheUrl;

        return $this;
    }

    public function getDateSortie(): ?\DateTimeImmutable
    {
        return $this->dateSortie;
    }

    public function setDateSortie(?\DateTimeImmutable $dateSortie): static
    {
        $this->dateSortie = $dateSortie;

        return $this;
    }

    /**
     * Durée lisible pour l'affichage : 133 minutes devient "2 h 13".
     */
    public function getDureeFormatee(): string
    {
        if (null === $this->dureeMinutes || $this->dureeMinutes <= 0) {
            return '';
        }

        $heures = intdiv($this->dureeMinutes, 60);
        $minutes = $this->dureeMinutes % 60;

        if (0 === $heures) {
            return sprintf('%d min', $minutes);
        }

        return sprintf('%d h %02d', $heures, $minutes);
    }

    /**
     * Représentation renvoyée par l'API REST.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'titre' => $this->titre,
            'synopsis' => $this->synopsis,
            'dureeMinutes' => $this->dureeMinutes,
            'realisateur' => $this->realisateur,
            'genre' => $this->genre,
            'classification' => $this->classification,
            'afficheUrl' => $this->afficheUrl,
            'dateSortie' => $this->dateSortie?->format('Y-m-d'),
        ];
    }
}
