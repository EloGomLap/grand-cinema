<?php

namespace App\Controller\Api;

use App\Entity\Salle;
use App\Entity\Seance;
use App\Exception\DonneesInvalidesException;
use App\Repository\FilmRepository;
use App\Repository\SalleRepository;
use App\Repository\SeanceRepository;
use App\Controller\ProgrammationController;
use App\Service\DisponibiliteService;
use App\Service\SeanceService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API des séances et des salles (documentée dans public/openapi.yaml).
 */
#[Route('/api')]
class SeanceApiController extends AbstractApiController
{
    public function __construct(
        private readonly SeanceRepository $seanceRepository,
        private readonly SalleRepository $salleRepository,
        private readonly SeanceService $seanceService,
        private readonly DisponibiliteService $disponibiliteService,
        private readonly FilmRepository $filmRepository,
    ) {
    }

    #[Route('/seances', name: 'api_seance_liste', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        $date = $request->query->getString('date');
        $jour = '' !== $date ? ProgrammationController::lireJour($date) : null;
        $film = $request->query->getInt('filmId') > 0 ? $this->filmRepository->find($request->query->getInt('filmId')) : null;
        if ($request->query->getInt('filmId') > 0 && null === $film) {
            throw new NotFoundHttpException('Film introuvable.');
        }

        return $this->reponse(array_map(static fn (Seance $s) => $s->toArray(), $this->seanceRepository->aVenir($jour, $film)));
    }

    #[Route('/salles/disponibilite', name: 'api_salle_disponibilite', methods: ['GET'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function disponibilite(Request $request): JsonResponse
    {
        $jour = ProgrammationController::lireJour($request->query->getString('date'));

        return $this->reponse(['date' => $jour->format('Y-m-d'), 'salles' => $this->disponibiliteService->enTableau($jour)]);
    }

    #[Route('/salles', name: 'api_salle_liste', methods: ['GET'])]
    public function salles(): JsonResponse
    {
        return $this->reponse(array_map(static fn (Salle $s) => $s->toArray(), $this->salleRepository->toutes()));
    }

    #[Route('/seances/{id}', name: 'api_seance_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        return $this->reponse($this->trouverSeance($id)->toArray());
    }

    #[Route('/seances', name: 'api_seance_programmer', methods: ['POST'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function programmer(Request $request): JsonResponse
    {
        try {
            $seance = $this->seanceService->programmer($this->lireJson($request));
        } catch (DonneesInvalidesException $e) {
            return $this->erreurValidation($e);
        }

        return $this->reponse($seance->toArray(), Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_seance_detail', ['id' => $seance->getId()]),
        ]);
    }

    protected function trouverSeance(int $id): Seance
    {
        return $this->seanceRepository->find($id)
            ?? throw new NotFoundHttpException(sprintf('Aucune séance avec l\'identifiant %d.', $id));
    }
}
