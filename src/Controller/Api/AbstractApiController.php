<?php

namespace App\Controller\Api;

use App\Exception\DonneesInvalidesException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Outils communs aux contrôleurs de l'API : réponses JSON lisibles, lecture du corps JSON,
 * format unique des erreurs de validation.
 */
abstract class AbstractApiController extends AbstractController
{
    /**
     * @param array<string, string> $entetes
     */
    protected function reponse(mixed $donnees, int $statut = Response::HTTP_OK, array $entetes = []): JsonResponse
    {
        return (new JsonResponse($donnees, $statut, $entetes))
            ->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    protected function lireJson(Request $request): array
    {
        try {
            $donnees = json_decode($request->getContent(), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Le corps de la requête doit être un JSON valide.');
        }

        if (!is_array($donnees)) {
            throw new BadRequestHttpException('Le corps de la requête doit être un objet JSON.');
        }

        return $donnees;
    }

    protected function erreurValidation(DonneesInvalidesException $e, int $statut = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->reponse(['erreur' => $e->getMessage(), 'details' => $e->getErreurs()], $statut);
    }
}
