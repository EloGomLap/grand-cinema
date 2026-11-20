<?php

namespace App\Controller;

use App\Service\PilotageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Tableau de bord du gestionnaire (programmateur) et de l'administrateur.
 */
#[Route('/pilotage')]
#[IsGranted('ROLE_PROGRAMMATEUR', message: 'Espace réservé au pilotage.')]
class PilotageController extends AbstractController
{
    public function __construct(private readonly PilotageService $pilotageService)
    {
    }

    #[Route('', name: 'pilotage_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        [$du, $au] = self::lirePeriode($request);

        return $this->render('pilotage/index.html.twig', [
            'du' => $du,
            'au' => $au,
            'remplissage' => $this->pilotageService->tauxRemplissage($du, $au),
        ]);
    }

    /**
     * Période demandée (du, au au format AAAA-MM-JJ). Par défaut : les 7 derniers jours et les 7 prochains.
     *
     * @return array{\DateTimeImmutable, \DateTimeImmutable}
     */
    public static function lirePeriode(Request $request): array
    {
        $lire = static function (string $valeur, string $defaut): \DateTimeImmutable {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

            return false !== $date && $date->format('Y-m-d') === $valeur ? $date : new \DateTimeImmutable($defaut);
        };
        $du = $lire($request->query->getString('du'), 'today -7 days');
        $au = $lire($request->query->getString('au'), 'today +7 days');

        return $du <= $au ? [$du, $au] : [$au, $du];
    }
}
