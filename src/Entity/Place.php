<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Une place d'une salle, repérée par son rang et son numéro (ex. C7).
 */
#[ORM\Entity]
#[ORM\Table(name: 'place')]
#[ORM\UniqueConstraint(name: 'place_unique', columns: ['salle_id', 'rang', 'numero'])]
class Place
{
    public const TYPES = ['STANDARD', 'PMR', 'PREMIUM'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'places')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Salle $salle,
        #[ORM\Column(length: 2)]
        private string $rang,
        #[ORM\Column]
        private int $numero,
        #[ORM\Column(length: 20)]
        private string $typePlace = 'STANDARD',
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSalle(): Salle
    {
        return $this->salle;
    }

    public function getRang(): string
    {
        return $this->rang;
    }

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function getTypePlace(): string
    {
        return $this->typePlace;
    }

    public function getLibelle(): string
    {
        return $this->rang.$this->numero;
    }
}
