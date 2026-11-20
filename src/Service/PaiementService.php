<?php

namespace App\Service;

use App\Entity\Reservation;
use App\Exception\DonneesInvalidesException;
use App\Paiement\PaiementRefuseException;
use App\Paiement\PasserellePaiementInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * User story « Paiement - Payer en ligne ».
 * Les données de carte sont vérifiées (format, clé de Luhn, date d'expiration) puis transmises
 * au prestataire de paiement. Elles ne sont jamais enregistrées en base.
 */
class PaiementService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PasserellePaiementInterface $passerelle,
        private readonly ReservationService $reservationService,
    ) {
    }

    /**
     * @param array<string, mixed> $carte numeroCarte, expiration (MM/AA), cvc
     *
     * @throws DonneesInvalidesException si la carte est mal saisie ou la réservation n'est plus payable
     * @throws PaiementRefuseException   si la banque refuse
     */
    public function payerEnLigne(Reservation $reservation, array $carte, ?\DateTimeImmutable $maintenant = null): void
    {
        $maintenant ??= new \DateTimeImmutable();
        $this->verifierPayable($reservation, $maintenant);

        $numero = preg_replace('/\D/', '', (string) ($carte['numeroCarte'] ?? ''));
        $expiration = trim((string) ($carte['expiration'] ?? ''));
        $cvc = trim((string) ($carte['cvc'] ?? ''));

        $erreurs = [];
        if (!self::numeroCarteValide($numero)) {
            $erreurs['numeroCarte'] = 'Numéro de carte invalide.';
        }
        if (!self::expirationValide($expiration, $maintenant)) {
            $erreurs['expiration'] = 'Date d\'expiration invalide ou dépassée (format MM/AA).';
        }
        if (1 !== preg_match('/^\d{3,4}$/', $cvc)) {
            $erreurs['cvc'] = 'Le cryptogramme comporte 3 chiffres.';
        }
        if ([] !== $erreurs) {
            throw new DonneesInvalidesException($erreurs);
        }

        $this->passerelle->debiter($reservation->getMontantTotal(), $numero, $expiration, $cvc, 'RES-'.$reservation->getId());

        $reservation->marquerPayee('CB_EN_LIGNE', $maintenant);
        $this->entityManager->flush();
    }

    /**
     * @throws DonneesInvalidesException
     */
    public function verifierPayable(Reservation $reservation, \DateTimeImmutable $maintenant): void
    {
        if ($reservation->estPayee()) {
            throw new DonneesInvalidesException(['reservation' => 'Cette réservation est déjà payée.']);
        }
        if (!$reservation->estEnAttente() || $this->reservationService->estExpiree($reservation, $maintenant)) {
            throw new DonneesInvalidesException(['reservation' => 'Le délai de paiement est dépassé : les places ont été libérées. Merci de refaire votre réservation.']);
        }
    }

    /**
     * Contrôle de la clé de Luhn : détecte les fautes de frappe avant d'appeler la banque.
     */
    public static function numeroCarteValide(string $numero): bool
    {
        if (1 !== preg_match('/^\d{13,19}$/', $numero)) {
            return false;
        }
        $somme = 0;
        foreach (array_reverse(str_split($numero)) as $i => $chiffre) {
            $valeur = (int) $chiffre * (1 === $i % 2 ? 2 : 1);
            $somme += $valeur > 9 ? $valeur - 9 : $valeur;
        }

        return 0 === $somme % 10;
    }

    public static function expirationValide(string $expiration, \DateTimeImmutable $maintenant): bool
    {
        if (1 !== preg_match('#^(0[1-9]|1[0-2])/(\d{2})$#', $expiration, $m)) {
            return false;
        }
        $finDeMois = (new \DateTimeImmutable(sprintf('20%s-%s-01', $m[2], $m[1])))->modify('last day of this month 23:59:59');

        return $finDeMois >= $maintenant;
    }
}
