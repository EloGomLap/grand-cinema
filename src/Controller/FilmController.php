<?php

namespace App\Controller;

use App\Entity\Film;
use App\Exception\FilmInvalideException;
use App\Repository\FilmRepository;
use App\Service\FilmService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Interface web de la banque de films.
 * Consultation : tout le monde. Ajout, modification, suppression : programmateur ou administrateur.
 */
#[Route('/films')]
class FilmController extends AbstractController
{
    public function __construct(
        private readonly FilmRepository $filmRepository,
        private readonly FilmService $filmService,
    ) {
    }

    #[Route('', name: 'film_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $genre = $request->query->getString('genre') ?: null;

        return $this->render('film/index.html.twig', [
            'films' => $this->filmRepository->rechercher($genre),
            'genre' => $genre,
        ]);
    }

    #[Route('/nouveau', name: 'film_nouveau', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function nouveau(Request $request): Response
    {
        $donnees = [];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            $this->verifierJetonCsrf($request, 'film');
            $donnees = $request->request->all('film');
            try {
                $film = $this->filmService->creer($donnees);
                $this->addFlash('succes', sprintf('Le film « %s » a été ajouté.', $film->getTitre()));

                return $this->redirectToRoute('film_voir', ['id' => $film->getId()]);
            } catch (FilmInvalideException $e) {
                $erreurs = $e->getErreurs();
            }
        }

        return $this->render('film/formulaire.html.twig', [
            'titre_page' => 'Ajouter un film',
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'classifications' => Film::CLASSIFICATIONS,
        ], new Response(status: [] === $erreurs ? 200 : 422));
    }

    #[Route('/{id}', name: 'film_voir', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function voir(int $id): Response
    {
        return $this->render('film/voir.html.twig', ['film' => $this->trouverFilm($id)]);
    }

    #[Route('/{id}/modifier', name: 'film_modifier', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function modifier(Request $request, int $id): Response
    {
        $film = $this->trouverFilm($id);
        $donnees = $film->toArray();
        $erreurs = [];

        if ($request->isMethod('POST')) {
            $this->verifierJetonCsrf($request, 'film');
            $donnees = $request->request->all('film');
            try {
                $this->filmService->modifier($film, $donnees);
                $this->addFlash('succes', 'Le film a été modifié.');

                return $this->redirectToRoute('film_voir', ['id' => $film->getId()]);
            } catch (FilmInvalideException $e) {
                $erreurs = $e->getErreurs();
            }
        }

        return $this->render('film/formulaire.html.twig', [
            'titre_page' => 'Modifier « '.$film->getTitre().' »',
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'classifications' => Film::CLASSIFICATIONS,
        ], new Response(status: [] === $erreurs ? 200 : 422));
    }

    #[Route('/{id}/supprimer', name: 'film_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_PROGRAMMATEUR', message: 'Action réservée aux programmateurs et administrateurs.')]
    public function supprimer(Request $request, int $id): Response
    {
        $film = $this->trouverFilm($id);
        $this->verifierJetonCsrf($request, 'supprimer'.$film->getId());

        try {
            $this->filmService->supprimer($film);
        } catch (FilmInvalideException $e) {
            $this->addFlash('erreur', implode(' ', $e->getErreurs()));

            return $this->redirectToRoute('film_voir', ['id' => $film->getId()]);
        }
        $this->addFlash('succes', 'Le film a été supprimé.');

        return $this->redirectToRoute('film_index');
    }

    private function trouverFilm(int $id): Film
    {
        return $this->filmRepository->find($id)
            ?? throw $this->createNotFoundException('Ce film n\'existe pas.');
    }

    /**
     * Protection CSRF : le formulaire doit contenir le jeton généré par le serveur.
     */
    private function verifierJetonCsrf(Request $request, string $intention): void
    {
        if (!$this->isCsrfTokenValid($intention, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
        }
    }
}
