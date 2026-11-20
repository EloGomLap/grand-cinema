<?php

namespace App\Entity;

use App\Repository\BilletRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un billet : une place réservée pour une séance, avec son code unique (affiché en QR code).
 * La contrainte UNIQUE (seance, place) empêche, au niveau de la base, de vendre deux fois la même place.
 */
#[ORM\Entity(repositoryClass: BilletRepository::class)]
#[ORM\Table(name: 'billet')]
#[ORM\UniqueConstraint(name: 'billet_place_unique', columns: ['seance_id', 'place_id'])]
class Billet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Seance $seance;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'billets')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Reservation $reservation,
        #[ORM\ManyToOne]
        #[ORM\JoinColumn(nullable: false)]
        private Place $place,
        #[ORM\Column(length: 64, unique: true)]
        private string $code,
    ) {
        $this->seance = $reservation->getSeance();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReservation(): Reservation
    {
        return $this->reservation;
    }

    public function getSeance(): Seance
    {
        return $this->seance;
    }

    public function getPlace(): Place
    {
        return $this->place;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDateValidation(): ?\DateTimeImmutable
    {
        return $this->dateValidation;
    }

    public function valider(?\DateTimeImmutable $date = null): void
    {
        $this->dateValidation = $date ?? new \DateTimeImmutable();
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'place' => $this->place->getLibelle(),
            'utilise' => null !== $this->dateValidation,
        ];
    }
}
