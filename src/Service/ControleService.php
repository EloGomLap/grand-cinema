<?php

namespace App\Service;

use App\Entity\Billet;
use App\Repository\BilletRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * User story « Contrôle - Scanner les billets ».
 * Un billet n'est accepté qu'une seule fois, pour une séance qui commence bientôt ou est en cours.
 */
class ControleService
{
    public const VALIDE = 'VALIDE';
    public const INCONNU = 'INCONNU';
    public const NON_PAYE = 'NON_PAYE';
    public const DEJA_UTILISE = 'DEJA_UTILISE';
    public const TROP_TOT = 'TROP_TOT';
    public const SEANCE_TERMINEE = 'SEANCE_TERMINEE';

    /** On peut entrer en salle à partir d'une heure avant le début de la séance */
    public const MINUTES_AVANT_OUVERTURE = 60;

    public function __construct(
        private readonly BilletRepository $billetRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{resultat: string, accepte: bool, message: string, billet: ?Billet}
     */
    public function valider(string $code, ?\DateTimeImmutable $maintenant = null): array
    {
        $maintenant ??= new \DateTimeImmutable();
        $code = strtolower(trim($code));
        $billet = '' === $code ? null : $this->billetRepository->findOneBy(['code' => $code]);

        if (null === $billet) {
            return $this->resultat(self::INCONNU, 'Billet inconnu : refusez l\'entrée.', null);
        }
        if (!$billet->getReservation()->estPayee()) {
            return $this->resultat(self::NON_PAYE, 'Billet non payé : refusez l\'entrée.', $billet);
        }
        if (null !== $billet->getDateValidation()) {
            return $this->resultat(self::DEJA_UTILISE, sprintf('Billet déjà utilisé à %s : refusez l\'entrée.', $billet->getDateValidation()->format('H:i')), $billet);
        }

        $seance = $billet->getSeance();
        if ($maintenant < $seance->getDateHeureDebut()->modify(sprintf('-%d minutes', self::MINUTES_AVANT_OUVERTURE))) {
            return $this->resultat(self::TROP_TOT, sprintf('Billet pour la séance du %s : trop tôt pour entrer.', $seance->getDateHeureDebut()->format('d/m à H:i')), $billet);
        }
        if ($maintenant > $seance->getDateHeureFin()) {
            return $this->resultat(self::SEANCE_TERMINEE, 'Cette séance est terminée.', $billet);
        }

        $billet->valider($maintenant);
        $this->entityManager->flush();

        return $this->resultat(self::VALIDE, sprintf('Billet valide - %s, place %s.', $seance->getSalle()->getNom(), $billet->getPlace()->getLibelle()), $billet);
    }

    /**
     * @return array{resultat: string, accepte: bool, message: string, billet: ?Billet}
     */
    private function resultat(string $resultat, string $message, ?Billet $billet): array
    {
        return ['resultat' => $resultat, 'accepte' => self::VALIDE === $resultat, 'message' => $message, 'billet' => $billet];
    }
}
