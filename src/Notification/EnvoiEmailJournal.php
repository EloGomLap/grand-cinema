<?php

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Envoi d'emails en mode démonstration : les messages sont écrits dans var/log/emails.log
 * au lieu d'être envoyés. En production, une classe EnvoiEmailBrevo (API Brevo ou Symfony Mailer)
 * implémente la même interface : aucune autre partie du code ne change.
 */
class EnvoiEmailJournal implements EnvoiEmailInterface
{
    /** @var list<array{destinataire: string, sujet: string, texte: string, html: string}> */
    private array $messages = [];

    public function __construct(
        #[Autowire('%kernel.logs_dir%/emails.log')]
        private readonly string $fichier,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function envoyer(string $destinataire, string $sujet, string $texte, string $html): void
    {
        $this->messages[] = ['destinataire' => $destinataire, 'sujet' => $sujet, 'texte' => $texte, 'html' => $html];

        $dossier = dirname($this->fichier);
        if (!is_dir($dossier)) {
            @mkdir($dossier, 0775, true);
        }
        @file_put_contents($this->fichier, sprintf("==== %s\nÀ : %s\nSujet : %s\n\n%s\n\n", date('c'), $destinataire, $sujet, $texte), FILE_APPEND);
        $this->logger?->info('Email envoyé', ['destinataire' => $destinataire, 'sujet' => $sujet]);
    }

    /**
     * @return list<array{destinataire: string, sujet: string, texte: string, html: string}>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
