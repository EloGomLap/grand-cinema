<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un utilisateur de l'application : client ou membre du personnel.
 * Correspond à la table "utilisateur" du script database/schema.sql.
 */
#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const ROLES = ['CLIENT', 'GUICHETIER', 'CONTROLEUR', 'PROGRAMMATEUR', 'ADMIN'];

    /** Rôles attribuables au personnel du cinéma par un administrateur */
    public const ROLES_PERSONNEL = ['GUICHETIER', 'CONTROLEUR', 'PROGRAMMATEUR', 'ADMIN'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nom = '';

    #[ORM\Column(length: 100)]
    private string $prenom = '';

    #[ORM\Column(length: 180, unique: true)]
    private string $email = '';

    /** Mot de passe haché, jamais en clair */
    #[ORM\Column(length: 255)]
    private string $motDePasse = '';

    #[ORM\Column(length: 20)]
    private string $role = 'CLIENT';

    #[ORM\Column]
    private \DateTimeImmutable $dateCreation;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = mb_strtolower(trim($email));

        return $this;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        if (!in_array($role, self::ROLES, true)) {
            throw new \InvalidArgumentException(sprintf('Rôle inconnu : %s', $role));
        }
        $this->role = $role;

        return $this;
    }

    public function estPersonnel(): bool
    {
        return in_array($this->role, self::ROLES_PERSONNEL, true);
    }

    public function getNomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * Le rôle métier (ex. PROGRAMMATEUR) devient le rôle Symfony ROLE_PROGRAMMATEUR.
     */
    public function getRoles(): array
    {
        return ['ROLE_'.$this->role, 'ROLE_USER'];
    }

    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasseHache): static
    {
        $this->motDePasse = $motDePasseHache;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }
}
