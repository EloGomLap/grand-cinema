<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\ReservationRepository;
use App\Security\ReservationVoter;
use App\Exception\DonneesInvalidesException;
use App\Paiement\PaiementRefuseException;
use App\Service\PaiementService;
use App\Service\ReservationService;
use Symfony\Component\HttpFoundation\Request;
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
        private readonly PaiementService $paiementService,
    ) {
    }

    /**
     * User story « Paiement - Payer en ligne ».
     */
    #[Route('/{id}/paiement', name: 'reservation_paiement', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function paiement(Request $request, int $id): Response
    {
        $reservation = $this->trouverReservation($id);
        $erreurs = [];
        $statut = 200;

        try {
            $this->paiementService->verifierPayable($reservation, new \DateTimeImmutable());
        } catch (DonneesInvalidesException $e) {
            $this->addFlash('erreur', implode(' ', $e->getErreurs()));

            return $this->redirectToRoute('reservation_voir', ['id' => $id]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('paiement'.$id, $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
            }
            try {
                $this->paiementService->payerEnLigne($reservation, $request->request->all('carte'));
                $this->addFlash('succes', 'Paiement accepté, merci ! Vos billets sont prêts.');

                return $this->redirectToRoute('reservation_voir', ['id' => $id]);
            } catch (DonneesInvalidesException $e) {
                $erreurs = $e->getErreurs();
                $statut = 422;
            } catch (PaiementRefuseException $e) {
                $erreurs = ['banque' => $e->getMessage()];
                $statut = 402;
            }
        }

        return $this->render('reservation/paiement.html.twig', [
            'reservation' => $reservation,
            'erreurs' => $erreurs,
        ], new Response(status: $statut));
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
