<?php

namespace App\Security;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Qui peut consulter (et payer) une réservation : le client qui l'a faite,
 * ou le personnel du guichet (et l'administrateur).
 *
 * @extends Voter<string, Reservation>
 */
class ReservationVoter extends Voter
{
    public const VOIR = 'RESERVATION_VOIR';

    public function __construct(private readonly AccessDecisionManagerInterface $decisions)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VOIR === $attribute && $subject instanceof Reservation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $utilisateur = $token->getUser();
        if (!$utilisateur instanceof Utilisateur) {
            return false;
        }
        if ($this->decisions->decide($token, ['ROLE_GUICHETIER'])) {
            return true;
        }

        return $subject->getUtilisateur()?->getId() === $utilisateur->getId();
    }
}
