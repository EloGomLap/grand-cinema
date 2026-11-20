<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Exception\DonneesInvalidesException;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Gestion des comptes : création des comptes du personnel par l'administrateur
 * et changement de rôle. Les mots de passe sont toujours hachés avant l'enregistrement.
 */
class CompteService
{
    public const LONGUEUR_MIN_MOT_DE_PASSE = 8;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    /**
     * Crée un compte pour un membre du personnel (guichetier, contrôleur, programmateur, admin).
     *
     * @param array<string, mixed> $donnees nom, prenom, email, motDePasse, role
     *
     * @throws DonneesInvalidesException
     */
    public function creerPersonnel(array $donnees): Utilisateur
    {
        $role = $this->texte($donnees['role'] ?? null) ?? '';
        $erreurs = [];
        if (!in_array($role, Utilisateur::ROLES_PERSONNEL, true)) {
            $erreurs['role'] = 'Choisissez un rôle du personnel : '.implode(', ', Utilisateur::ROLES_PERSONNEL).'.';
        }

        return $this->creerCompte($donnees, in_array($role, Utilisateur::ROLES_PERSONNEL, true) ? $role : 'GUICHETIER', $erreurs);
    }

    /**
     * Change le rôle d'un compte. Un administrateur ne peut pas retirer ses propres droits,
     * pour éviter de se retrouver sans aucun administrateur.
     *
     * @throws DonneesInvalidesException
     */
    public function changerRole(Utilisateur $compte, string $role, Utilisateur $auteur): void
    {
        if (!in_array($role, Utilisateur::ROLES, true)) {
            throw new DonneesInvalidesException(['role' => 'Rôle inconnu.']);
        }
        if ($compte->getId() === $auteur->getId() && 'ADMIN' !== $role) {
            throw new DonneesInvalidesException(['role' => 'Vous ne pouvez pas retirer vos propres droits d\'administrateur.']);
        }

        $compte->setRole($role);
        $this->entityManager->flush();
    }

    /**
     * Règles communes à tous les comptes : champs obligatoires, email valide et unique,
     * mot de passe d'au moins 8 caractères.
     *
     * @param array<string, mixed>  $donnees
     * @param array<string, string> $erreurs erreurs déjà détectées
     *
     * @throws DonneesInvalidesException
     */
    protected function creerCompte(array $donnees, string $role, array $erreurs = []): Utilisateur
    {
        $nom = $this->texte($donnees['nom'] ?? null);
        $prenom = $this->texte($donnees['prenom'] ?? null);
        $email = mb_strtolower($this->texte($donnees['email'] ?? null) ?? '');
        $motDePasse = is_string($donnees['motDePasse'] ?? null) ? $donnees['motDePasse'] : '';

        if (null === $nom) {
            $erreurs['nom'] = 'Le nom est obligatoire.';
        }
        if (null === $prenom) {
            $erreurs['prenom'] = 'Le prénom est obligatoire.';
        }
        if (false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'L\'adresse email n\'est pas valide.';
        } elseif (null !== $this->utilisateurRepository->findOneBy(['email' => $email])) {
            $erreurs['email'] = 'Un compte existe déjà avec cette adresse email.';
        }
        if (mb_strlen($motDePasse) < self::LONGUEUR_MIN_MOT_DE_PASSE) {
            $erreurs['motDePasse'] = sprintf('Le mot de passe doit contenir au moins %d caractères.', self::LONGUEUR_MIN_MOT_DE_PASSE);
        }

        if ([] !== $erreurs) {
            throw new DonneesInvalidesException($erreurs);
        }

        $utilisateur = (new Utilisateur())->setNom($nom)->setPrenom($prenom)->setEmail($email)->setRole($role);
        $utilisateur->setMotDePasse($this->passwordHasher->hashPassword($utilisateur, $motDePasse));

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();

        return $utilisateur;
    }

    private function texte(mixed $valeur): ?string
    {
        if (!is_scalar($valeur)) {
            return null;
        }
        $valeur = trim((string) $valeur);

        return '' === $valeur ? null : $valeur;
    }
}
