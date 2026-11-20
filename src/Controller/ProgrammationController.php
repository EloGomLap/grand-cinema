<?php

namespace App\Controller;

use App\Entity\Seance;
use App\Exception\DonneesInvalidesException;
use App\Repository\FilmRepository;
use App\Repository\SalleRepository;
use App\Repository\SeanceRepository;
use App\Service\SeanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Espace programmation (programmateur / gestionnaire et administrateur).
 */
#[Route('/programmation')]
#[IsGranted('ROLE_PROGRAMMATEUR', message: 'Espace réservé à la programmation.')]
class ProgrammationController extends AbstractController
{
    public function __construct(
        private readonly SeanceRepository $seanceRepository,
        private readonly FilmRepository $filmRepository,
        private readonly SalleRepository $salleRepository,
        private readonly SeanceService $seanceService,
    ) {
    }

    #[Route('/seances', name: 'programmation_seances', methods: ['GET', 'POST'])]
    public function seances(Request $request): Response
    {
        $donnees = ['langue' => 'VF', 'prix' => '8.50'];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('seance', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
            }
            $donnees = $request->request->all('seance');
            try {
                $seance = $this->seanceService->programmer($donnees);
                $this->addFlash('succes', sprintf(
                    'Séance programmée : « %s », %s, %s.',
                    $seance->getFilm()->getTitre(),
                    $seance->getSalle()->getNom(),
                    $seance->getDateHeureDebut()->format('d/m/Y à H:i'),
                ));

                return $this->redirectToRoute('programmation_seances');
            } catch (DonneesInvalidesException $e) {
                $erreurs = $e->getErreurs();
            }
        }

        return $this->render('programmation/seances.html.twig', [
            'seances' => $this->seanceRepository->aVenir(),
            'films' => $this->filmRepository->rechercher(),
            'salles' => $this->salleRepository->toutes(),
            'langues' => Seance::LANGUES,
            'donnees' => $donnees,
            'erreurs' => $erreurs,
        ], new Response(status: [] === $erreurs ? 200 : 422));
    }
}
