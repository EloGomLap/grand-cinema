<?php

namespace App\Tests\Functional;

use App\Entity\Film;
use App\Entity\Salle;
use App\Entity\Seance;
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
        $comptes = [
            'admin@test.fr' => 'ADMIN',
            'programmateur@test.fr' => 'PROGRAMMATEUR',
            'guichetier@test.fr' => 'GUICHETIER',
            'controleur@test.fr' => 'CONTROLEUR',
            'client@test.fr' => 'CLIENT',
        ];
        foreach ($comptes as $email => $role) {
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

    protected function creerSalle(string $nom, int $rangs, int $placesParRang): Salle
    {
        $salle = (new Salle($nom))->genererPlan($rangs, $placesParRang);
        $this->em->persist($salle);
        $this->em->flush();

        return $salle;
    }

    protected function creerSeance(string $titreFilm, Salle $salle, string $debut, string $prix = '8.50'): Seance
    {
        $seance = new Seance($this->film($titreFilm), $salle, new \DateTimeImmutable($debut), $prix);
        $this->em->persist($seance);
        $this->em->flush();

        return $seance;
    }
}
