<?php

namespace App\Controller\Api;

use App\Exception\DonneesInvalidesException;
use App\Service\CompteService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/comptes')]
class CompteApiController extends AbstractApiController
{
    public function __construct(private readonly CompteService $compteService)
    {
    }

    #[Route('', name: 'api_compte_creer', methods: ['POST'])]
    public function creer(Request $request): JsonResponse
    {
        try {
            $client = $this->compteService->inscrireClient($this->lireJson($request));
        } catch (DonneesInvalidesException $e) {
            return $this->erreurValidation($e);
        }

        return $this->reponse([
            'id' => $client->getId(),
            'email' => $client->getEmail(),
            'prenom' => $client->getPrenom(),
            'nom' => $client->getNom(),
            'role' => $client->getRole(),
        ], Response::HTTP_CREATED);
    }
}
