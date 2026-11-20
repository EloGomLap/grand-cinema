<?php

namespace App\Controller\Api;

use App\Entity\Film;
use App\Exception\FilmInvalideException;
use App\Repository\FilmRepository;
use App\Service\FilmService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * API REST de la banque de films, documentée dans public/openapi.yaml (Swagger : /api/doc).
 */
#[Route('/api/films')]
class FilmApiController extends AbstractController
{
    public function __construct(
        private readonly FilmRepository $filmRepository,
        private readonly FilmService $filmService,
    ) {
    }

    #[Route('', name: 'api_film_liste', methods: ['GET'])]
    public function liste(Request $request): JsonResponse
    {
        $films = $this->filmRepository->rechercher($request->query->getString('genre') ?: null);

        return $this->reponse(array_map(static fn (Film $f) => $f->toArray(), $films));
    }

    #[Route('/{id}', name: 'api_film_detail', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        return $this->reponse($this->trouverFilm($id)->toArray());
    }

    #[Route('', name: 'api_film_creer', methods: ['POST'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function creer(Request $request): JsonResponse
    {
        try {
            $film = $this->filmService->creer($this->lireJson($request));
        } catch (FilmInvalideException $e) {
            return $this->erreurValidation($e);
        }

        return $this->reponse($film->toArray(), Response::HTTP_CREATED, [
            'Location' => $this->generateUrl('api_film_detail', ['id' => $film->getId()]),
        ]);
    }

    #[Route('/{id}', name: 'api_film_modifier', requirements: ['id' => '\d+'], methods: ['PUT', 'PATCH'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function modifier(Request $request, int $id): JsonResponse
    {
        $film = $this->trouverFilm($id);

        try {
            $this->filmService->modifier($film, $this->lireJson($request));
        } catch (FilmInvalideException $e) {
            return $this->erreurValidation($e);
        }

        return $this->reponse($film->toArray());
    }

    #[Route('/{id}', name: 'api_film_supprimer', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function supprimer(int $id): Response
    {
        try {
            $this->filmService->supprimer($this->trouverFilm($id));
        } catch (FilmInvalideException $e) {
            return $this->reponse(['erreur' => implode(' ', $e->getErreurs())], Response::HTTP_CONFLICT);
        }

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Réponse JSON lisible (accents non échappés).
     *
     * @param array<string, string> $entetes
     */
    private function reponse(mixed $donnees, int $statut = Response::HTTP_OK, array $entetes = []): JsonResponse
    {
        return (new JsonResponse($donnees, $statut, $entetes))
            ->setEncodingOptions(JsonResponse::DEFAULT_ENCODING_OPTIONS | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function trouverFilm(int $id): Film
    {
        return $this->filmRepository->find($id)
            ?? throw new NotFoundHttpException(sprintf('Aucun film avec l\'identifiant %d.', $id));
    }

    /**
     * @return array<string, mixed>
     */
    private function lireJson(Request $request): array
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

    private function erreurValidation(FilmInvalideException $e): JsonResponse
    {
        return $this->reponse([
            'erreur' => $e->getMessage(),
            'details' => $e->getErreurs(),
        ], Response::HTTP_BAD_REQUEST);
    }
}
