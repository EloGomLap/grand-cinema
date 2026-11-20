<?php

namespace App\Controller;

use App\Service\ControleService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Écran du contrôleur à l'entrée des salles. Un lecteur de QR code se comporte comme un clavier :
 * il « tape » le code puis Entrée, ce qui envoie le formulaire. La caméra du téléphone peut aussi servir.
 */
#[Route('/controle')]
#[IsGranted('ROLE_CONTROLEUR', message: 'Espace réservé aux contrôleurs.')]
class ControleController extends AbstractController
{
    public function __construct(private readonly ControleService $controleService)
    {
    }

    #[Route('', name: 'controle_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $resultat = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('controle', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
            }
            $resultat = $this->controleService->valider($request->request->getString('code'));
        }

        return $this->render('controle/index.html.twig', ['resultat' => $resultat]);
    }
}
