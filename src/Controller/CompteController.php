<?php

namespace App\Controller;

use App\Exception\DonneesInvalidesException;
use App\Service\CompteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace client : création de compte.
 */
class CompteController extends AbstractController
{
    public function __construct(private readonly CompteService $compteService)
    {
    }

    #[Route('/inscription', name: 'compte_inscription', methods: ['GET', 'POST'])]
    public function inscription(Request $request, Security $security): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('seance_index');
        }

        $donnees = [];
        $erreurs = [];
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('inscription', $request->request->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
            }
            $donnees = $request->request->all('compte');
            try {
                $client = $this->compteService->inscrireClient($donnees);
                // Connexion automatique après l'inscription
                $security->login($client, 'form_login', 'main');
                $this->addFlash('succes', sprintf('Bienvenue %s, votre compte est créé.', $client->getPrenom()));

                return $this->redirectToRoute('seance_index');
            } catch (DonneesInvalidesException $e) {
                $erreurs = $e->getErreurs();
                unset($donnees['motDePasse'], $donnees['confirmation']);
            }
        }

        return $this->render('compte/inscription.html.twig', [
            'donnees' => $donnees,
            'erreurs' => $erreurs,
        ], new Response(status: [] === $erreurs ? 200 : 422));
    }
}
