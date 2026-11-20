<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une réservation (une commande) : une ou plusieurs places pour une séance,
 * en ligne ou au guichet. Correspond à la table "reservation" de database/schema.sql.
 */
#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    public const CANAUX = ['EN_LIGNE', 'GUICHET'];
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';
    public const STATUT_PAYEE = 'PAYEE';
    public const STATUT_ANNULEE = 'ANNULEE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** Compte du client : vide pour une vente au guichet à un client sans compte */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Seance $seance;

    #[ORM\Column(length: 10)]
    private string $canal;

    #[ORM\Column(length: 15)]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2)]
    private string $montantTotal = '0.00';

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $dateReservation;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datePaiement = null;

    /** @var Collection<int, Billet> */
    #[ORM\OneToMany(targetEntity: Billet::class, mappedBy: 'reservation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $billets;

    public function __construct(Seance $seance, string $canal, ?Utilisateur $utilisateur = null, ?\DateTimeImmutable $date = null)
    {
        if (!in_array($canal, self::CANAUX, true)) {
            throw new \InvalidArgumentException(sprintf('Canal inconnu : %s', $canal));
        }
        $this->seance = $seance;
        $this->canal = $canal;
        $this->utilisateur = $utilisateur;
        $this->dateReservation = $date ?? new \DateTimeImmutable();
        $this->billets = new ArrayCollection();
    }

    /**
     * Ajoute un billet pour une place et recalcule le montant total.
     */
    public function ajouterBillet(Place $place, string $code): Billet
    {
        $billet = new Billet($this, $place, $code);
        $this->billets->add($billet);
        $this->montantTotal = number_format((float) $this->seance->getPrix() * $this->billets->count(), 2, '.', '');

        return $billet;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function getSeance(): Seance
    {
        return $this->seance;
    }

    public function getCanal(): string
    {
        return $this->canal;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function estPayee(): bool
    {
        return self::STATUT_PAYEE === $this->statut;
    }

    public function estEnAttente(): bool
    {
        return self::STATUT_EN_ATTENTE === $this->statut;
    }

    public function marquerPayee(string $modePaiement, ?\DateTimeImmutable $date = null): void
    {
        $this->statut = self::STATUT_PAYEE;
        $this->modePaiement = $modePaiement;
        $this->datePaiement = $date ?? new \DateTimeImmutable();
    }

    public function annuler(): void
    {
        $this->statut = self::STATUT_ANNULEE;
    }

    public function getMontantTotal(): string
    {
        return number_format((float) $this->montantTotal, 2, '.', '');
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function getDateReservation(): \DateTimeImmutable
    {
        return $this->dateReservation;
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }

    /**
     * @return Collection<int, Billet>
     */
    public function getBillets(): Collection
    {
        return $this->billets;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'seance' => $this->seance->toArray(),
            'canal' => $this->canal,
            'statut' => $this->statut,
            'montantTotal' => (float) $this->getMontantTotal(),
            'modePaiement' => $this->modePaiement,
            'dateReservation' => $this->dateReservation->format(\DateTimeInterface::ATOM),
            'places' => array_map(static fn (Billet $b) => $b->getPlace()->getLibelle(), $this->billets->toArray()),
            'billets' => $this->estPayee() ? array_map(static fn (Billet $b) => $b->toArray(), $this->billets->toArray()) : [],
        ];
    }
}
