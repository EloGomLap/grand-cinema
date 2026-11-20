<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Exception\DonneesInvalidesException;
use App\Repository\ReservationRepository;
use App\Service\ReservationService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
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
    public function __construct(
        private readonly CompteService $compteService,
        private readonly ReservationRepository $reservationRepository,
        private readonly ReservationService $reservationService,
    ) {
    }

    /**
     * User story « Compte client - Retrouver ses billets dans son compte ».
     */
    #[Route('/mon-compte/billets', name: 'compte_billets', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mesBillets(): Response
    {
        /** @var Utilisateur $client */
        $client = $this->getUser();
        $maintenant = new \DateTimeImmutable();
        $aVenir = $enAttente = $passees = [];
        foreach ($this->reservationRepository->duClient($client) as $reservation) {
            if ($reservation->estEnAttente()) {
                if (!$this->reservationService->estExpiree($reservation, $maintenant) && !$reservation->getSeance()->estPassee($maintenant)) {
                    $enAttente[] = $reservation;
                }
            } elseif ($reservation->getSeance()->getDateHeureFin() < $maintenant) {
                $passees[] = $reservation;
            } else {
                $aVenir[] = $reservation;
            }
        }

        return $this->render('compte/billets.html.twig', [
            'aVenir' => $aVenir,
            'enAttente' => $enAttente,
            'passees' => array_reverse($passees),
        ]);
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
