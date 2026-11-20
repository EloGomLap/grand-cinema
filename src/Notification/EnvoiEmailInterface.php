<?php

namespace App\Notification;

/**
 * Envoi d'emails transactionnels (Brevo en production, cf. solution hybride du Bloc 1).
 */
interface EnvoiEmailInterface
{
    public function envoyer(string $destinataire, string $sujet, string $texte, string $html): void;
}
