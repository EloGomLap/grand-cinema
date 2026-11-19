<?php

namespace App\Exception;

/**
 * Levée quand les données d'un film ne respectent pas les règles métier.
 * Elle transporte la liste des erreurs, champ par champ, pour l'affichage.
 */
class FilmInvalideException extends \DomainException
{
    /**
     * @param array<string, string> $erreurs champ => message
     */
    public function __construct(private readonly array $erreurs)
    {
        parent::__construct('Les données du film sont invalides.');
    }

    /**
     * @return array<string, string>
     */
    public function getErreurs(): array
    {
        return $this->erreurs;
    }
}
