<?php

namespace App\Controller\Api;

use App\Entity\Reservation;
use App\Entity\Utilisateur;
use App\Exception\DonneesInvalidesException;
use App\Exception\PlaceDejaPriseException;
use App\Repository\ReservationRepository;
use App\Repository\SeanceRepository;
use App\Security\ReservationVoter;
use App\Service\ReservationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API des réservations (documentée dans public/openapi.yaml).
 */
#[Route('/api/reservations')]
#[IsGranted('ROLE_USER', message: 'Connectez-vous pour réserver.')]
class ReservationApiController extends AbstractApiController
{
    public function __construct(
        protected readonly ReservationRepository $reservationRepository,
        private readonly SeanceRepository $seanceRepository,
        protected readonly ReservationService $reservationService,
    ) {
    }

    #[Route('', name: 'api_reservation_creer', methods: ['POST'])]
    public function creer(Request $request): JsonResponse
    {
        $donnees = $this->lireJson($request);
        $seance = is_numeric($donnees['seanceId'] ?? null) ? $this->seanceRepository->find((int) $donnees['seanceId']) : null;
        if (null === $seance) {
            return $this->erreurValidation(new DonneesInvalidesException(['seanceId' => 'Séance introuvable.']));
        }

        /** @var Utilisateur $client */
        $client = $this->getUser();
        try {
            $reservation = $this->reservationService->reserver($seance, (array) ($donnees['places'] ?? []), 'EN_LIGNE', $client);
        } catch (DonneesInvalidesException $e) {
            return $this->erreurValidation($e);
        } catch (PlaceDejaPriseException $e) {
            return $this->reponse(['erreur' => $e->getMessage(), 'placesDejaPrises' => $e->getPlaces()], Response::HTTP_CONFLICT);
        }

        return $this->reponse($reservation->toArray(), Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_reservation_detail', ['id' => $reservation->getId()]),
        ]);
    }

    #[Route('/{id}', name: 'api_reservation_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        return $this->reponse($this->trouverReservation($id)->toArray());
    }

    protected function trouverReservation(int $id): Reservation
    {
        $reservation = $this->reservationRepository->find($id) ?? throw new NotFoundHttpException('Réservation introuvable.');
        $this->denyAccessUnlessGranted(ReservationVoter::VOIR, $reservation, 'Cette réservation ne vous appartient pas.');

        return $reservation;
    }
}
