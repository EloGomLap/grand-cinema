<?php

namespace App\Paiement;

/**
 * Passerelle de paiement simulée, utilisée tant que le compte Stripe du cinéma n'est pas ouvert.
 * Elle reprend les cartes de test de Stripe :
 * - 4242 4242 4242 4242 : paiement accepté ;
 * - 4000 0000 0000 0002 : carte refusée par la banque.
 * Pour passer en production, il suffit d'écrire une classe PasserellePaiementStripe
 * qui implémente la même interface et de la déclarer dans config/services.yaml.
 */
class PasserellePaiementSimulee implements PasserellePaiementInterface
{
    public const CARTE_REFUSEE = '4000000000000002';

    public function debiter(string $montant, string $numeroCarte, string $expiration, string $cvc, string $reference): string
    {
        if (self::CARTE_REFUSEE === $numeroCarte) {
            throw new PaiementRefuseException('Paiement refusé par votre banque. Essayez une autre carte.');
        }

        return 'sim_'.substr(hash('sha256', $reference.$montant.microtime()), 0, 24);
    }
}
