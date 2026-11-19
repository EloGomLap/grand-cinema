<?php

namespace App\Tests\Unit;

use App\Entity\Utilisateur;
use PHPUnit\Framework\TestCase;

class UtilisateurTest extends TestCase
{
    public function testLeRoleMetierDevientUnRoleSymfony(): void
    {
        $utilisateur = (new Utilisateur())->setRole('PROGRAMMATEUR');

        self::assertSame(['ROLE_PROGRAMMATEUR', 'ROLE_USER'], $utilisateur->getRoles());
    }

    public function testUnRoleInconnuEstRefuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new Utilisateur())->setRole('SUPER_HEROS');
    }

    public function testLEmailEstNormalisePourLaConnexion(): void
    {
        $utilisateur = (new Utilisateur())->setEmail('  Admin@GrandCinema.FR ');

        self::assertSame('admin@grandcinema.fr', $utilisateur->getUserIdentifier());
    }
}
