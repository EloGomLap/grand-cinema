<?php

namespace App\Tests\Functional;

use App\Entity\Film;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Recrée une base SQLite de test vide avant chaque test :
 * les tests sont indépendants et ne touchent jamais la base de développement.
 */
trait BaseDeTestTrait
{
    protected KernelBrowser $client;
    protected EntityManagerInterface $em;

    protected function preparerBase(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $metadonnees = $this->em->getMetadataFactory()->getAllMetadata();
        $outil = new SchemaTool($this->em);
        $outil->dropSchema($metadonnees);
        $outil->createSchema($metadonnees);

        $hasher = static::getContainer()->get('security.user_password_hasher');
        foreach (['programmateur@test.fr' => 'PROGRAMMATEUR', 'client@test.fr' => 'CLIENT'] as $email => $role) {
            $u = (new Utilisateur())->setNom('Test')->setPrenom($role)->setEmail($email)->setRole($role);
            $u->setMotDePasse($hasher->hashPassword($u, 'motdepasse'));
            $this->em->persist($u);
        }

        $this->em->persist((new Film())->setTitre('Bird')->setDureeMinutes(161)->setGenre('Biopic'));
        $this->em->persist((new Film())->setTitre('Whiplash')->setDureeMinutes(107)->setGenre('Drame musical'));
        $this->em->flush();
    }

    protected function utilisateur(string $email): Utilisateur
    {
        return $this->em->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
    }

    protected function film(string $titre): ?Film
    {
        return $this->em->getRepository(Film::class)->findOneBy(['titre' => $titre]);
    }
}
