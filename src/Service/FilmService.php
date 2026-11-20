<?php

namespace App\Service;

use App\Entity\Film;
use App\Entity\Seance;
use App\Exception\FilmInvalideException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Logique métier de la banque de films.
 * Utilisée à la fois par l'interface web et par l'API REST :
 * les règles sont écrites une seule fois, au même endroit.
 */
class FilmService
{
    private const CHAMPS_TEXTE = ['titre', 'synopsis', 'realisateur', 'genre', 'classification', 'afficheUrl'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $donnees
     *
     * @throws FilmInvalideException
     */
    public function creer(array $donnees): Film
    {
        $film = new Film();
        $this->valider($film, $this->hydrater($film, $donnees, true));

        $this->entityManager->persist($film);
        $this->entityManager->flush();

        return $film;
    }

    /**
     * Modification : seuls les champs envoyés sont mis à jour.
     *
     * @param array<string, mixed> $donnees
     *
     * @throws FilmInvalideException
     */
    public function modifier(Film $film, array $donnees): Film
    {
        $this->valider($film, $this->hydrater($film, $donnees, false));

        $this->entityManager->flush();

        return $film;
    }

    /**
     * Un film déjà programmé ne peut pas être supprimé : ses séances (et billets) en dépendent.
     *
     * @throws FilmInvalideException
     */
    public function supprimer(Film $film): void
    {
        if ($this->entityManager->getRepository(Seance::class)->count(['film' => $film]) > 0) {
            throw new FilmInvalideException(['film' => 'Ce film a des séances programmées : il ne peut pas être supprimé.']);
        }

        $this->entityManager->remove($film);
        $this->entityManager->flush();
    }

    /**
     * Copie les données reçues dans le film, en les nettoyant :
     * espaces superflus retirés, champs vides convertis en null.
     *
     * @param array<string, mixed> $donnees
     *
     * @return array<string, string> erreurs de format détectées pendant la copie
     */
    private function hydrater(Film $film, array $donnees, bool $creation): array
    {
        $erreurs = [];

        foreach (self::CHAMPS_TEXTE as $champ) {
            if (!$creation && !array_key_exists($champ, $donnees)) {
                continue;
            }
            $film->{'set'.ucfirst($champ)}($this->nettoyerTexte($donnees[$champ] ?? null));
        }

        if ($creation || array_key_exists('dureeMinutes', $donnees)) {
            $duree = $donnees['dureeMinutes'] ?? null;
            if (null === $duree || '' === $duree) {
                $film->setDureeMinutes(null);
            } elseif (is_numeric($duree) && (int) $duree == $duree) {
                $film->setDureeMinutes((int) $duree);
            } else {
                $erreurs['dureeMinutes'] = 'La durée doit être un nombre entier de minutes.';
            }
        }

        if ($creation || array_key_exists('dateSortie', $donnees)) {
            $date = $this->nettoyerTexte($donnees['dateSortie'] ?? null);
            if (null === $date) {
                $film->setDateSortie(null);
            } else {
                $dateSortie = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (false === $dateSortie || $dateSortie->format('Y-m-d') !== $date) {
                    $erreurs['dateSortie'] = 'La date de sortie doit être au format AAAA-MM-JJ.';
                } else {
                    $film->setDateSortie($dateSortie);
                }
            }
        }

        return $erreurs;
    }

    /**
     * Applique les règles déclarées sur l'entité (titre obligatoire, durée positive...)
     * et regroupe toutes les erreurs pour les afficher en une seule fois.
     *
     * @param array<string, string> $erreurs erreurs déjà détectées
     *
     * @throws FilmInvalideException
     */
    private function valider(Film $film, array $erreurs = []): void
    {
        foreach ($this->validator->validate($film) as $violation) {
            $erreurs[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        if ([] !== $erreurs) {
            throw new FilmInvalideException($erreurs);
        }
    }

    private function nettoyerTexte(mixed $valeur): ?string
    {
        if (null === $valeur || !is_scalar($valeur)) {
            return null;
        }

        $valeur = trim((string) $valeur);

        return '' === $valeur ? null : $valeur;
    }
}
