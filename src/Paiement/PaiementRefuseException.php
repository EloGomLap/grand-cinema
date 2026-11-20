<?php

namespace App\Paiement;

/**
 * Le prestataire ou la banque a refusé le paiement (code HTTP 402).
 */
class PaiementRefuseException extends \RuntimeException
{
}
