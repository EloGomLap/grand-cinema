<?php

namespace App\Entity;

use App\Repository\SalleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une salle de projection et son plan (ses places).
 * Correspond à la table "salle" du script database/schema.sql.
 */
#[ORM\Entity(repositoryClass: SalleRepository::class)]
#[ORM\Table(name: 'salle')]
class Salle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private string $nom = '';

    #[ORM\Column]
    private int $capacite = 0;

    /** @var Collection<int, Place> */
    #[ORM\OneToMany(targetEntity: Place::class, mappedBy: 'salle', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['rang' => 'ASC', 'numero' => 'ASC'])]
    private Collection $places;

    public function __construct(string $nom = '')
    {
        $this->nom = $nom;
        $this->places = new ArrayCollection();
    }

    /**
     * Crée le plan de la salle : des rangs (A, B, C...) de N places numérotées.
     * Le premier rang accueille les places PMR (accès facilité).
     */
    public function genererPlan(int $nombreRangs, int $placesParRang): static
    {
        foreach (range(0, $nombreRangs - 1) as $i) {
            $rang = chr(ord('A') + $i);
            foreach (range(1, $placesParRang) as $numero) {
                $type = 0 === $i && $numero <= 2 ? 'PMR' : 'STANDARD';
                $this->places->add(new Place($this, $rang, $numero, $type));
            }
        }
        $this->capacite = $this->places->count();

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function getCapacite(): int
    {
        return $this->capacite;
    }

    /**
     * @return Collection<int, Place>
     */
    public function getPlaces(): Collection
    {
        return $this->places;
    }

    /**
     * Places regroupées par rang, pour dessiner le plan de salle.
     *
     * @return array<string, Place[]>
     */
    public function getPlanParRang(): array
    {
        $plan = [];
        foreach ($this->places as $place) {
            $plan[$place->getRang()][] = $place;
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'nom' => $this->nom, 'capacite' => $this->capacite];
    }
}
