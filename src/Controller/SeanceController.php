<?php

namespace App\Controller;

use App\Entity\Seance;
use App\Repository\FilmRepository;
use App\Repository\SeanceRepository;
use App\Entity\Utilisateur;
use App\Exception\DonneesInvalidesException;
use App\Exception\PlaceDejaPriseException;
use App\Service\PlacesService;
use App\Service\ReservationService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * User story « Consultation - Consulter les séances » : accessible à tous, sans compte.
 */
#[Route('/seances')]
class SeanceController extends AbstractController
{
    public function __construct(
        private readonly SeanceRepository $seanceRepository,
        private readonly FilmRepository $filmRepository,
        private readonly PlacesService $placesService,
        private readonly ReservationService $reservationService,
    ) {
    }

    #[Route('', name: 'seance_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $date = $request->query->getString('date');
        $jour = '' !== $date ? ProgrammationController::lireJour($date) : null;
        $film = $request->query->getInt('film') > 0 ? $this->filmRepository->find($request->query->getInt('film')) : null;

        $seances = $this->seanceRepository->aVenir($jour, $film);
        $parJour = [];
        foreach ($seances as $seance) {
            $parJour[$seance->getDateHeureDebut()->format('Y-m-d')][] = $seance;
        }

        return $this->render('seance/index.html.twig', [
            'parJour' => $parJour,
            'restantes' => $this->placesService->placesRestantesPour($seances),
            'jour' => $jour,
            'filmChoisi' => $film,
            'films' => $this->filmRepository->rechercher(),
        ]);
    }

    #[Route('/{id}', name: 'seance_voir', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function voir(int $id): Response
    {
        return $this->afficherSeance($this->trouverSeance($id));
    }

    /**
     * User story « Réservation - Choisir sa place » : le client coche ses places sur le plan.
     */
    #[Route('/{id}/reserver', name: 'seance_reserver', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_USER', message: 'Connectez-vous pour réserver.')]
    public function reserver(Request $request, int $id): Response
    {
        $seance = $this->trouverSeance($id);
        if (!$this->isCsrfTokenValid('reserver'.$id, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
        }

        /** @var Utilisateur $client */
        $client = $this->getUser();
        try {
            $reservation = $this->reservationService->reserver($seance, $request->request->all('places'), 'EN_LIGNE', $client);
        } catch (DonneesInvalidesException $e) {
            return $this->afficherSeance($seance, implode(' ', $e->getErreurs()), 422);
        } catch (PlaceDejaPriseException $e) {
            return $this->afficherSeance($seance, $e->getMessage(), 409);
        }

        return $this->redirectToRoute('reservation_voir', ['id' => $reservation->getId()]);
    }

    private function afficherSeance(Seance $seance, ?string $erreur = null, int $statut = 200): Response
    {
        return $this->render('seance/voir.html.twig', [
            'seance' => $seance,
            'plan' => $this->placesService->plan($seance),
            'restantes' => $this->placesService->placesRestantes($seance),
            'maxPlaces' => ReservationService::MAX_PLACES,
            'erreur' => $erreur,
        ], new Response(status: $statut));
    }

    protected function trouverSeance(int $id): Seance
    {
        return $this->seanceRepository->find($id) ?? throw $this->createNotFoundException('Cette séance n\'existe pas.');
    }
}
