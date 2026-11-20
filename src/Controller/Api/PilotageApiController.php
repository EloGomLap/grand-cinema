<?php

namespace App\Controller\Api;

use App\Controller\PilotageController;
use App\Service\PilotageService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/pilotage')]
#[IsGranted('ROLE_PROGRAMMATEUR', message: 'Indicateurs réservés au gestionnaire et à l\'administrateur.')]
class PilotageApiController extends AbstractApiController
{
    public function __construct(private readonly PilotageService $pilotageService)
    {
    }

    #[Route('/remplissage', name: 'api_pilotage_remplissage', methods: ['GET'])]
    public function remplissage(Request $request): JsonResponse
    {
        [$du, $au] = PilotageController::lirePeriode($request);
        $donnees = $this->pilotageService->tauxRemplissage($du, $au);
        $donnees['seances'] = array_map(static fn (array $l) => [
            'seanceId' => $l['seance']->getId(),
            'film' => $l['seance']->getFilm()->getTitre(),
            'salle' => $l['seance']->getSalle()->getNom(),
            'debut' => $l['seance']->getDateHeureDebut()->format(\DateTimeInterface::ATOM),
            'vendues' => $l['vendues'],
            'capacite' => $l['capacite'],
            'taux' => $l['taux'],
        ], $donnees['seances']);

        return $this->reponse(['du' => $du->format('Y-m-d'), 'au' => $au->format('Y-m-d')] + $donnees);
    }
}
