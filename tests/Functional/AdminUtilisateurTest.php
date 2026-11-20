<?php

namespace App\Tests\Functional;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * User story « Accès - Gérer les comptes du personnel ».
 */
class AdminUtilisateurTest extends WebTestCase
{
    use BaseDeTestTrait;

    protected function setUp(): void
    {
        $this->preparerBase();
    }

    public function testSeulLAdministrateurAccedeALaGestionDesComptes(): void
    {
        $this->client->loginUser($this->utilisateur('programmateur@test.fr'));
        $this->client->request('GET', '/admin/utilisateurs');
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->utilisateur('admin@test.fr'));
        $this->client->request('GET', '/admin/utilisateurs');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'controleur@test.fr');
    }

    public function testLAdministrateurCreeUnCompteGuichetier(): void
    {
        $this->client->loginUser($this->utilisateur('admin@test.fr'));
        $this->client->request('GET', '/admin/utilisateurs/nouveau');
        $this->client->submitForm('Créer le compte', [
            'compte[prenom]' => 'Nina',
            'compte[nom]' => 'Lopez',
            'compte[email]' => 'nina@test.fr',
            'compte[motDePasse]' => 'unmotdepasse',
            'compte[role]' => 'GUICHETIER',
        ]);

        self::assertResponseRedirects('/admin/utilisateurs');
        $compte = $this->utilisateur('nina@test.fr');
        self::assertSame('GUICHETIER', $compte->getRole());
        self::assertNotSame('unmotdepasse', $compte->getPassword(), 'le mot de passe est haché');
    }

    public function testUnEmailDejaUtiliseEtUnMotDePasseTropCourtSontRefuses(): void
    {
        $this->client->loginUser($this->utilisateur('admin@test.fr'));
        $this->client->request('GET', '/admin/utilisateurs/nouveau');
        $this->client->submitForm('Créer le compte', [
            'compte[prenom]' => 'X',
            'compte[nom]' => 'Y',
            'compte[email]' => 'client@test.fr',
            'compte[motDePasse]' => 'court',
            'compte[role]' => 'CONTROLEUR',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('form', 'Un compte existe déjà');
        self::assertSelectorTextContains('form', 'au moins 8 caractères');
    }

    public function testLAdministrateurChangeLeRoleDUnCompte(): void
    {
        $this->client->loginUser($this->utilisateur('admin@test.fr'));
        $this->client->request('GET', '/admin/utilisateurs');
        $client = $this->utilisateur('client@test.fr');
        $jeton = $this->client->getCrawler()->filter('form[action="/admin/utilisateurs/'.$client->getId().'/role"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/utilisateurs/'.$client->getId().'/role', ['_token' => $jeton, 'role' => 'CONTROLEUR']);

        self::assertResponseRedirects('/admin/utilisateurs');
        $this->em->clear();
        self::assertSame('CONTROLEUR', $this->em->getRepository(Utilisateur::class)->find($client->getId())->getRole());
    }

    public function testLAdministrateurNePeutPasRetirerSesPropresDroits(): void
    {
        $admin = $this->utilisateur('admin@test.fr');
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/utilisateurs');
        $jeton = $this->client->getCrawler()->filter('form[action="/admin/utilisateurs/'.$admin->getId().'/role"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/admin/utilisateurs/'.$admin->getId().'/role', ['_token' => $jeton, 'role' => 'CLIENT']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash.erreur', 'propres droits');
        $this->em->clear();
        self::assertSame('ADMIN', $this->em->getRepository(Utilisateur::class)->find($admin->getId())->getRole());
    }
}
