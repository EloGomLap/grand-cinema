<?php

namespace App\Controller;

use App\Repository\BilletRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affichage d'un billet à partir de son code (lien reçu par email).
 * Le code est long et aléatoire (128 bits) : impossible à deviner, il sert de clé d'accès.
 */
class BilletController extends AbstractController
{
    public function __construct(private readonly BilletRepository $billetRepository)
    {
    }

    #[Route('/billets/{code}', name: 'billet_voir', requirements: ['code' => '[a-f0-9]{32}'], methods: ['GET'])]
    public function voir(string $code): Response
    {
        $billet = $this->billetRepository->findOneBy(['code' => $code]);
        if (null === $billet || !$billet->getReservation()->estPayee()) {
            throw $this->createNotFoundException('Billet introuvable.');
        }

        return $this->render('billet/voir.html.twig', ['billet' => $billet]);
    }
}
