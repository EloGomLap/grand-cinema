<?php

namespace App\Notification;

use App\Entity\Reservation;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * User story « Réservation - Recevoir ses billets » : après le paiement,
 * le client reçoit un email avec un lien vers chacun de ses billets (QR code à présenter à l'entrée).
 */
class BilletNotifier
{
    public function __construct(
        private readonly EnvoiEmailInterface $envoiEmail,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @return bool vrai si un email a été envoyé (une vente au guichet sans compte n'a pas d'adresse)
     */
    public function envoyerBillets(Reservation $reservation): bool
    {
        $client = $reservation->getUtilisateur();
        if (null === $client || !$reservation->estPayee()) {
            return false;
        }

        $seance = $reservation->getSeance();
        $liens = [];
        foreach ($reservation->getBillets() as $billet) {
            $liens[$billet->getPlace()->getLibelle()] = $this->urlGenerator->generate('billet_voir', ['code' => $billet->getCode()], UrlGeneratorInterface::ABSOLUTE_URL);
        }

        $sujet = sprintf('Vos billets pour %s le %s', $seance->getFilm()->getTitre(), $seance->getDateHeureDebut()->format('d/m/Y à H:i'));
        $texte = sprintf("Bonjour %s,\n\nMerci pour votre réservation n°%d au Grand Cinéma.\n%s - %s - %s\n\n",
            $client->getPrenom(), $reservation->getId(), $seance->getFilm()->getTitre(), $seance->getDateHeureDebut()->format('d/m/Y à H:i'), $seance->getSalle()->getNom());
        foreach ($liens as $place => $lien) {
            $texte .= sprintf("Place %s : %s\n", $place, $lien);
        }
        $texte .= "\nPrésentez le QR code de chaque billet à l'entrée de la salle.\n";

        $html = $this->twig->render('email/billets.html.twig', ['reservation' => $reservation, 'liens' => $liens]);
        $this->envoiEmail->envoyer($client->getEmail(), $sujet, $texte, $html);

        return true;
    }
}
