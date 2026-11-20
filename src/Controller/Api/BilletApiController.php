<?php

namespace App\Controller\Api;

use App\Service\ControleService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/billets')]
class BilletApiController extends AbstractApiController
{
    public function __construct(private readonly ControleService $controleService)
    {
    }

    /**
     * Validation d'un billet à l'entrée de la salle (application de scan des contrôleurs).
     */
    #[Route('/{code}/validation', name: 'api_billet_valider', methods: ['POST'])]
    #[IsGranted('ROLE_CONTROLEUR', message: 'Action réservée aux contrôleurs.')]
    public function valider(string $code): JsonResponse
    {
        $resultat = $this->controleService->valider($code);
        $billet = $resultat['billet'];

        return $this->reponse([
            'resultat' => $resultat['resultat'],
            'accepte' => $resultat['accepte'],
            'message' => $resultat['message'],
            'billet' => null === $billet ? null : $billet->toArray() + [
                'film' => $billet->getSeance()->getFilm()->getTitre(),
                'salle' => $billet->getSeance()->getSalle()->getNom(),
                'debut' => $billet->getSeance()->getDateHeureDebut()->format(\DateTimeInterface::ATOM),
            ],
        ], match ($resultat['resultat']) {
            ControleService::VALIDE => Response::HTTP_OK,
            ControleService::INCONNU => Response::HTTP_NOT_FOUND,
            default => Response::HTTP_CONFLICT,
        });
    }
}
