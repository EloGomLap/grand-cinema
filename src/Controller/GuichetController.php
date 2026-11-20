<?php

namespace App\Controller;

use App\Repository\SeanceRepository;
use App\Exception\DonneesInvalidesException;
use App\Exception\PlaceDejaPriseException;
use App\Service\PaiementService;
use App\Service\PlacesService;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interface du guichet : la même application que le site, pour que les places soient à jour en temps réel
 * entre la vente en ligne et la vente sur place.
 */
#[Route('/guichet')]
#[IsGranted('ROLE_GUICHETIER', message: 'Espace réservé au guichet.')]
class GuichetController extends AbstractController
{
    public function __construct(
        private readonly SeanceRepository $seanceRepository,
        private readonly PlacesService $placesService,
        private readonly ReservationService $reservationService,
        private readonly PaiementService $paiementService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * User story « Consultation - Consulter les séances au guichet » : les séances de la journée,
     * dans l'ordre des horaires, y compris celles déjà commencées (signalées).
     */
    #[Route('', name: 'guichet_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $jour = ProgrammationController::lireJour($request->query->getString('date'));
        $seances = $this->seanceRepository->duJour($jour);
        usort($seances, static fn ($a, $b) => $a->getDateHeureDebut() <=> $b->getDateHeureDebut());

        return $this->render('guichet/index.html.twig', [
            'jour' => $jour,
            'seances' => $seances,
            'restantes' => $this->placesService->placesRestantesPour($seances),
            'maintenant' => new \DateTimeImmutable(),
        ]);
    }

    /**
     * User story « Réservation - Voir les places restantes au guichet » : plan de salle à jour,
     * ventes en ligne comprises.
     */
    #[Route('/seances/{id}', name: 'guichet_seance', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function seance(int $id): Response
    {
        $seance = $this->seanceRepository->find($id) ?? throw $this->createNotFoundException('Séance introuvable.');

        return $this->afficherSeance($seance);
    }

    /**
     * User story « Paiement - Plusieurs moyens de paiement au guichet » : vente sur place,
     * places choisies sur le plan ou attribuées automatiquement, paiement en espèces, carte ou chèque.
     */
    #[Route('/seances/{id}/vente', name: 'guichet_vente', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function vente(Request $request, int $id): Response
    {
        $seance = $this->seanceRepository->find($id) ?? throw $this->createNotFoundException('Séance introuvable.');
        if (!$this->isCsrfTokenValid('vente'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
        }

        $places = $request->request->all('places');
        if ([] === $places && $request->request->getInt('nombre') > 0) {
            $places = $this->placesService->meilleuresPlacesLibres($seance, min($request->request->getInt('nombre'), ReservationService::MAX_PLACES));
        }

        $mode = $request->request->getString('mode');
        if (!array_key_exists($mode, PaiementService::MODES_GUICHET)) {
            return $this->afficherSeance($seance, 'Choisissez le moyen de paiement.', 422);
        }

        try {
            $reservation = $this->entityManager->wrapInTransaction(function () use ($seance, $places, $mode, $request) {
                $reservation = $this->reservationService->reserver($seance, $places, 'GUICHET');
                $monnaie = $this->paiementService->payerAuGuichet($reservation, $mode, $request->request->getString('montantRecu'));

                return [$reservation, $monnaie];
            });
        } catch (DonneesInvalidesException $e) {
            return $this->afficherSeance($seance, implode(' ', $e->getErreurs()), 422);
        } catch (PlaceDejaPriseException $e) {
            return $this->afficherSeance($seance, $e->getMessage(), 409);
        }

        [$vente, $monnaie] = $reservation;
        $this->addFlash('succes', sprintf(
            'Vente enregistrée : %d place%s, %s € (%s).%s',
            $vente->getBillets()->count(),
            $vente->getBillets()->count() > 1 ? 's' : '',
            number_format((float) $vente->getMontantTotal(), 2, ',', ' '),
            PaiementService::MODES_GUICHET[$mode],
            (float) $monnaie > 0 ? sprintf(' Monnaie à rendre : %s €.', number_format((float) $monnaie, 2, ',', ' ')) : '',
        ));

        return $this->redirectToRoute('reservation_voir', ['id' => $vente->getId()]);
    }

    private function afficherSeance(\App\Entity\Seance $seance, ?string $erreur = null, int $statut = 200): Response
    {
        return $this->render('guichet/seance.html.twig', [
            'seance' => $seance,
            'plan' => $this->placesService->plan($seance),
            'restantes' => $this->placesService->placesRestantes($seance),
            'modes' => PaiementService::MODES_GUICHET,
            'maxPlaces' => ReservationService::MAX_PLACES,
            'erreur' => $erreur,
        ], new Response(status: $statut));
    }
}
