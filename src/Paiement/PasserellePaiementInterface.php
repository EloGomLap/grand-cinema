<?php

namespace App\Paiement;

/**
 * Prestataire de paiement par carte (Stripe en production, cf. solution hybride du Bloc 1).
 * L'application ne stocke jamais le numéro de carte : il est transmis au prestataire puis oublié.
 */
interface PasserellePaiementInterface
{
    /**
     * @param string $montant   montant en euros, ex. "19.00"
     * @param string $reference référence de la commande chez le cinéma
     *
     * @return string identifiant de la transaction chez le prestataire
     *
     * @throws PaiementRefuseException si la banque refuse le paiement
     */
    public function debiter(string $montant, string $numeroCarte, string $expiration, string $cvc, string $reference): string;
}
