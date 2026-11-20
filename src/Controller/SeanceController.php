<?php

namespace App\Controller;

use App\Entity\Seance;
use App\Repository\FilmRepository;
use App\Repository\SeanceRepository;
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
    ) {
    }

    #[Route('', name: 'seance_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $date = $request->query->getString('date');
        $jour = '' !== $date ? ProgrammationController::lireJour($date) : null;
        $film = $request->query->getInt('film') > 0 ? $this->filmRepository->find($request->query->getInt('film')) : null;

        $parJour = [];
        foreach ($this->seanceRepository->aVenir($jour, $film) as $seance) {
            $parJour[$seance->getDateHeureDebut()->format('Y-m-d')][] = $seance;
        }

        return $this->render('seance/index.html.twig', [
            'parJour' => $parJour,
            'jour' => $jour,
            'filmChoisi' => $film,
            'films' => $this->filmRepository->rechercher(),
        ]);
    }

    #[Route('/{id}', name: 'seance_voir', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function voir(int $id): Response
    {
        return $this->render('seance/voir.html.twig', ['seance' => $this->trouverSeance($id)]);
    }

    protected function trouverSeance(int $id): Seance
    {
        return $this->seanceRepository->find($id) ?? throw $this->createNotFoundException('Cette séance n\'existe pas.');
    }
}
