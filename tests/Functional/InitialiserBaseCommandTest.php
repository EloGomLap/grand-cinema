<?php

namespace App\Tests\Functional;

use App\Entity\Film;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class InitialiserBaseCommandTest extends KernelTestCase
{
    public function testLaCommandeCreeLesComptesEtLesFilmsDeDemonstration(): void
    {
        $application = new Application(self::bootKernel());
        $commande = new CommandTester($application->find('app:initialiser-base'));

        $commande->execute([]);

        $commande->assertCommandIsSuccessful();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(3, $em->getRepository(Utilisateur::class)->count([]));
        self::assertSame(4, $em->getRepository(Film::class)->count([]));
        self::assertSame('ADMIN', $em->getRepository(Utilisateur::class)->findOneBy(['email' => 'admin@grandcinema.fr'])->getRole());
    }
}
