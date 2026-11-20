<?php

namespace App\Controller;

use App\Repository\SeanceRepository;
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
    public function __construct(private readonly SeanceRepository $seanceRepository)
    {
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
            'maintenant' => new \DateTimeImmutable(),
        ]);
    }
}
