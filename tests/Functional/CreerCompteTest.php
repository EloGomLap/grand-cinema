<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Compte client - Créer un compte client ».
 */
class CreerCompteTest extends WebTestCase
{
    use BaseDeTestTrait;

    protected function setUp(): void
    {
        $this->preparerBase();
    }

    public function testUnVisiteurCreeSonCompteEtEstConnecte(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'compte[prenom]' => 'Camille',
            'compte[nom]' => 'Moreau',
            'compte[email]' => 'Camille.Moreau@Exemple.fr',
            'compte[motDePasse]' => 'cinema2026',
            'compte[confirmation]' => 'cinema2026',
        ]);

        self::assertResponseRedirects('/seances');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.succes', 'Bienvenue Camille');
        self::assertSelectorTextContains('header', 'Camille Moreau (client)');
        self::assertSame('CLIENT', $this->utilisateur('camille.moreau@exemple.fr')->getRole());
    }

    public function testLesErreursDeSaisieSontSignalees(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Créer mon compte', [
            'compte[prenom]' => '',
            'compte[nom]' => 'Moreau',
            'compte[email]' => 'client@test.fr',
            'compte[motDePasse]' => 'cinema2026',
            'compte[confirmation]' => 'autre',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Le prénom est obligatoire.');
        self::assertSelectorTextContains('form', 'Un compte existe déjà');
        self::assertSelectorTextContains('form', 'ne correspondent pas');
    }

    public function testLInscriptionParLApiCreeToujoursUnClient(): void
    {
        $this->client->request('POST', '/api/comptes', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'prenom' => 'Malik', 'nom' => 'Diallo', 'email' => 'malik@exemple.fr', 'motDePasse' => 'cinema2026', 'role' => 'ADMIN',
        ]));

        self::assertResponseStatusCodeSame(201);
        self::assertSame('CLIENT', $this->utilisateur('malik@exemple.fr')->getRole(), 'impossible de s\'attribuer un autre rôle');
    }
}
