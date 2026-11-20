<?php

namespace App\Exception;

/**
 * Levée par les services métier quand les données reçues ne respectent pas les règles.
 * Elle transporte toutes les erreurs, champ par champ, pour les afficher en une seule fois
 * (formulaire web) ou les renvoyer dans une réponse 400 (API).
 */
class DonneesInvalidesException extends \RuntimeException
{
    /**
     * @param array<string, string> $erreurs
     */
    public function __construct(private readonly array $erreurs, string $message = 'Les données envoyées sont invalides.')
    {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function getErreurs(): array
    {
        return $this->erreurs;
    }
}
