<?php

namespace App\Service;

use App\Entity\Place;
use App\Entity\Reservation;
use App\Entity\Seance;
use App\Entity\Utilisateur;
use App\Exception\DonneesInvalidesException;
use App\Exception\PlaceDejaPriseException;
use App\Repository\BilletRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * User story « Réservation - Choisir sa place ».
 *
 * Protection contre la double réservation d'une même place (bug identifié au dossier 3) :
 * 1. vérification que les places sont libres ;
 * 2. création dans une transaction ;
 * 3. filet de sécurité : la contrainte UNIQUE (seance_id, place_id) de la table billet.
 *    Si deux clients valident au même instant, la base refuse le second enregistrement
 *    et le client reçoit un message clair (code 409).
 */
class ReservationService
{
    public const MAX_PLACES = 10;

    /** Une réservation non payée bloque ses places pendant ce délai, puis elles sont libérées */
    public const MINUTES_BLOCAGE = 15;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BilletRepository $billetRepository,
    ) {
    }

    /**
     * @param array<mixed> $idsPlaces
     *
     * @throws DonneesInvalidesException si la demande est invalide
     * @throws PlaceDejaPriseException    si une place n'est plus disponible
     */
    public function reserver(Seance $seance, array $idsPlaces, string $canal, ?Utilisateur $client = null, ?\DateTimeImmutable $maintenant = null): Reservation
    {
        $maintenant ??= new \DateTimeImmutable();
        $places = $this->verifierDemande($seance, $idsPlaces, $maintenant);

        $this->libererReservationsExpirees($seance, $maintenant);

        $occupees = array_flip($this->billetRepository->idsPlacesOccupees($seance, $maintenant));
        $dejaPrises = array_values(array_map(
            static fn (Place $p) => $p->getLibelle(),
            array_filter($places, static fn (Place $p) => isset($occupees[$p->getId()])),
        ));
        if ([] !== $dejaPrises) {
            throw new PlaceDejaPriseException($dejaPrises);
        }

        $reservation = new Reservation($seance, $canal, $client, $maintenant);
        foreach ($places as $place) {
            $reservation->ajouterBillet($place, self::genererCode());
        }

        try {
            $this->entityManager->wrapInTransaction(function () use ($reservation): void {
                $this->entityManager->persist($reservation);
                $this->entityManager->flush();
            });
        } catch (UniqueConstraintViolationException) {
            throw new PlaceDejaPriseException();
        }

        return $reservation;
    }

    /**
     * Annule les réservations restées sans paiement au-delà du délai et libère leurs places.
     */
    public function libererReservationsExpirees(Seance $seance, ?\DateTimeImmutable $maintenant = null): int
    {
        $limite = ($maintenant ?? new \DateTimeImmutable())->modify(sprintf('-%d minutes', self::MINUTES_BLOCAGE));
        $expirees = $this->entityManager->createQueryBuilder()
            ->select('r')->from(Reservation::class, 'r')
            ->andWhere('r.seance = :seance')
            ->andWhere('r.statut = :attente')
            ->andWhere('r.dateReservation <= :limite')
            ->setParameter('seance', $seance)
            ->setParameter('attente', Reservation::STATUT_EN_ATTENTE)
            ->setParameter('limite', $limite)
            ->getQuery()->getResult();

        foreach ($expirees as $reservation) {
            $reservation->annuler();
            // Les billets sont supprimés pour libérer la contrainte UNIQUE (seance, place)
            foreach ($reservation->getBillets() as $billet) {
                $reservation->getBillets()->removeElement($billet);
            }
        }
        if ([] !== $expirees) {
            $this->entityManager->flush();
        }

        return count($expirees);
    }

    public function estExpiree(Reservation $reservation, ?\DateTimeImmutable $maintenant = null): bool
    {
        $limite = ($maintenant ?? new \DateTimeImmutable())->modify(sprintf('-%d minutes', self::MINUTES_BLOCAGE));

        return $reservation->estEnAttente() && $reservation->getDateReservation() <= $limite;
    }

    public static function genererCode(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<mixed> $idsPlaces
     *
     * @return Place[]
     *
     * @throws DonneesInvalidesException
     */
    private function verifierDemande(Seance $seance, array $idsPlaces, \DateTimeImmutable $maintenant): array
    {
        if ($seance->estPassee($maintenant)) {
            throw new DonneesInvalidesException(['seance' => 'Cette séance a déjà commencé, elle n\'est plus réservable.']);
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($idsPlaces, 'is_numeric'))));
        if ([] === $ids) {
            throw new DonneesInvalidesException(['places' => 'Choisissez au moins une place.']);
        }
        if (count($ids) > self::MAX_PLACES) {
            throw new DonneesInvalidesException(['places' => sprintf('Vous pouvez réserver %d places au maximum.', self::MAX_PLACES)]);
        }

        $places = [];
        foreach ($seance->getSalle()->getPlaces() as $place) {
            if (in_array($place->getId(), $ids, true)) {
                $places[] = $place;
            }
        }
        if (count($places) !== count($ids)) {
            throw new DonneesInvalidesException(['places' => 'Certaines places n\'existent pas dans cette salle.']);
        }

        return $places;
    }
}
