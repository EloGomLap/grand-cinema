<?php

namespace App\Controller\Admin;

use App\Entity\Utilisateur;
use App\Exception\DonneesInvalidesException;
use App\Repository\UtilisateurRepository;
use App\Service\CompteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * User story « Accès - Gérer les comptes du personnel » : réservé à l'administrateur.
 */
#[Route('/admin/utilisateurs')]
#[IsGranted('ROLE_ADMIN', message: 'Espace réservé aux administrateurs.')]
class UtilisateurController extends AbstractController
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly CompteService $compteService,
    ) {
    }

    #[Route('', name: 'admin_utilisateurs', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/utilisateurs.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findBy([], ['role' => 'ASC', 'nom' => 'ASC']),
            'roles' => Utilisateur::ROLES,
        ]);
    }

    #[Route('/nouveau', name: 'admin_utilisateur_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(Request $request): Response
    {
        $donnees = ['role' => 'GUICHETIER'];
        $erreurs = [];

        if ($request->isMethod('POST')) {
            $this->verifierJeton($request, 'compte_personnel');
            $donnees = $request->request->all('compte');
            try {
                $compte = $this->compteService->creerPersonnel($donnees);
                $this->addFlash('succes', sprintf('Le compte de %s (%s) a été créé.', $compte->getNomComplet(), strtolower($compte->getRole())));

                return $this->redirectToRoute('admin_utilisateurs');
            } catch (DonneesInvalidesException $e) {
                $erreurs = $e->getErreurs();
                unset($donnees['motDePasse']);
            }
        }

        return $this->render('admin/utilisateur_formulaire.html.twig', [
            'donnees' => $donnees,
            'erreurs' => $erreurs,
            'roles' => Utilisateur::ROLES_PERSONNEL,
        ], new Response(status: [] === $erreurs ? 200 : 422));
    }

    #[Route('/{id}/role', name: 'admin_utilisateur_role', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function changerRole(Request $request, int $id): Response
    {
        $compte = $this->utilisateurRepository->find($id) ?? throw $this->createNotFoundException('Compte introuvable.');
        $this->verifierJeton($request, 'role'.$id);

        /** @var Utilisateur $admin */
        $admin = $this->getUser();
        try {
            $this->compteService->changerRole($compte, $request->request->getString('role'), $admin);
            $this->addFlash('succes', sprintf('%s est maintenant %s.', $compte->getNomComplet(), strtolower($compte->getRole())));
        } catch (DonneesInvalidesException $e) {
            $this->addFlash('erreur', implode(' ', $e->getErreurs()));
        }

        return $this->redirectToRoute('admin_utilisateurs');
    }

    private function verifierJeton(Request $request, string $intention): void
    {
        if (!$this->isCsrfTokenValid($intention, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide, merci de recharger la page.');
        }
    }
}
