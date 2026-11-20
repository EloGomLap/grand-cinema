<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AccueilController extends AbstractController
{
    #[Route('/', name: 'accueil')]
    public function index(): Response
    {
        return $this->redirectToRoute('seance_index');
    }

    /**
     * Documentation interactive de l'API (Swagger UI), à partir de public/openapi.yaml.
     */
    #[Route('/api/doc', name: 'api_doc', methods: ['GET'])]
    public function documentationApi(): Response
    {
        return $this->render('api/doc.html.twig');
    }
}
