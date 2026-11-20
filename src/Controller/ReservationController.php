<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Security\ReservationVoter;
use App\Service\ReservationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Récapitulatif d'une réservation (client propriétaire, guichet, administrateur).
 */
#[Route('/reservations')]
#[IsGranted('ROLE_USER')]
class ReservationController extends AbstractController
{
    public function __construct(
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationService $reservationService,
    ) {
    }

    #[Route('/{id}', name: 'reservation_voir', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function voir(int $id): Response
    {
        $reservation = $this->trouverReservation($id);

        return $this->render('reservation/voir.html.twig', [
            'reservation' => $reservation,
            'expiree' => $this->reservationService->estExpiree($reservation),
            'minutesBlocage' => ReservationService::MINUTES_BLOCAGE,
        ]);
    }

    protected function trouverReservation(int $id): Reservation
    {
        $reservation = $this->reservationRepository->find($id) ?? throw $this->createNotFoundException('Réservation introuvable.');
        $this->denyAccessUnlessGranted(ReservationVoter::VOIR, $reservation, 'Cette réservation ne vous appartient pas.');

        return $reservation;
    }
}
