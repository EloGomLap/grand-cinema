<?php

namespace App\Command;

use App\Entity\Film;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée les tables et un jeu de données de démonstration :
 * php bin/console app:initialiser-base
 */
#[AsCommand(name: 'app:initialiser-base', description: 'Crée la base de données et les comptes de démonstration')]
class InitialiserBaseCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('mdp-admin', null, InputOption::VALUE_REQUIRED, 'Mot de passe du compte administrateur', 'Admin-Cinema-2026')
            ->addOption('mdp-programmateur', null, InputOption::VALUE_REQUIRED, 'Mot de passe du compte programmateur', 'Prog-Cinema-2026')
            ->addOption('mdp-client', null, InputOption::VALUE_REQUIRED, 'Mot de passe du compte client', 'Client-Cinema-2026')
            ->addOption('mdp-guichetier', null, InputOption::VALUE_REQUIRED, 'Mot de passe du compte guichetier', 'Guichet-Cinema-2026')
            ->addOption('mdp-controleur', null, InputOption::VALUE_REQUIRED, 'Mot de passe du compte contrôleur', 'Controle-Cinema-2026')
            ->addOption('sans-films', null, InputOption::VALUE_NONE, 'Ne pas créer les films de démonstration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $metadonnees = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($this->entityManager);
        $schema->dropSchema($metadonnees);
        $schema->createSchema($metadonnees);
        $io->text('Tables créées.');

        $comptes = [
            ['Admin', 'Cinema', 'admin@grandcinema.fr', 'ADMIN', $input->getOption('mdp-admin')],
            ['Martin', 'Paul', 'programmateur@grandcinema.fr', 'PROGRAMMATEUR', $input->getOption('mdp-programmateur')],
            ['Durand', 'Lea', 'client@grandcinema.fr', 'CLIENT', $input->getOption('mdp-client')],
            ['Bernard', 'Sophie', 'guichetier@grandcinema.fr', 'GUICHETIER', $input->getOption('mdp-guichetier')],
            ['Petit', 'Hugo', 'controleur@grandcinema.fr', 'CONTROLEUR', $input->getOption('mdp-controleur')],
        ];

        foreach ($comptes as [$nom, $prenom, $email, $role, $motDePasse]) {
            $utilisateur = (new Utilisateur())->setNom($nom)->setPrenom($prenom)->setEmail($email)->setRole($role);
            $utilisateur->setMotDePasse($this->passwordHasher->hashPassword($utilisateur, $motDePasse));
            $this->entityManager->persist($utilisateur);
        }

        if (!$input->getOption('sans-films')) {
            $films = [
                ['Round Midnight', 133, 'Bertrand Tavernier', 'Drame musical', 'Tous publics', '1986-11-05'],
                ['Bird', 161, 'Clint Eastwood', 'Biopic', 'Tous publics', '1988-09-28'],
                ['Whiplash', 107, 'Damien Chazelle', 'Drame musical', 'Tous publics', '2014-12-24'],
                ['La La Land', 128, 'Damien Chazelle', 'Comédie musicale', 'Tous publics', '2017-01-25'],
            ];
            foreach ($films as [$titre, $duree, $realisateur, $genre, $classification, $sortie]) {
                $this->entityManager->persist((new Film())
                    ->setTitre($titre)
                    ->setDureeMinutes($duree)
                    ->setRealisateur($realisateur)
                    ->setGenre($genre)
                    ->setClassification($classification)
                    ->setDateSortie(new \DateTimeImmutable($sortie)));
            }
        }

        $this->entityManager->flush();
        $io->success('Base initialisée avec les comptes de démonstration (admin, programmateur, guichetier, contrôleur, client).');

        return Command::SUCCESS;
    }
}
