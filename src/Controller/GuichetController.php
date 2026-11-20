<?php

namespace App\Controller;

use App\Repository\SeanceRepository;
use App\Service\PlacesService;
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

        return $this->render('guichet/seance.html.twig', [
            'seance' => $seance,
            'plan' => $this->placesService->plan($seance),
            'restantes' => $this->placesService->placesRestantes($seance),
        ]);
    }
}
