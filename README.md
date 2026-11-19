# Grand Cinéma - Application de réservation

Application web de réservation de places pour le cinéma « Le Grand Cinéma ».
Objectif métier : augmenter la fréquentation de 30 % en 12 mois et absorber les pics du festival de Jazz.

## Stack technique

| Couche | Technologie |
|---|---|
| Back-end / API REST | PHP 8.3 - Symfony 7.4 LTS |
| Base de données | SQLite en développement et en test, PostgreSQL 16 en production cible (ORM Doctrine) |
| Documentation API | OpenAPI 3 (`public/openapi.yaml`) affichée avec Swagger UI sur `/api/doc` |
| Front-end | Twig + JavaScript (interface client et interface guichet) |
| Tests | PHPUnit (unitaires), WebTestCase (fonctionnels) |
| Qualité | SonarCloud |
| CI | GitHub Actions (`.github/workflows/ci.yml`) : tests à chaque push et pull request |

## Organisation du dépôt

```
grand-cinema/
├── bin/console          Commandes Symfony
├── config/              Configuration (sécurité, base de données...)
├── database/schema.sql  Script SQL complet de la base (PostgreSQL)
├── public/              Point d'entrée web + openapi.yaml (documentation de l'API)
├── src/
│   ├── Command/         Commande d'initialisation de la base
│   ├── Controller/      Contrôleurs web et API (Controller/Api)
│   ├── Entity/          Entités Doctrine (Film, Utilisateur)
│   ├── EventSubscriber/ Gestion centralisée des erreurs de l'API
│   ├── Exception/       Exceptions métier
│   ├── Repository/      Accès aux données
│   └── Service/         Logique métier (FilmService)
├── templates/           Vues Twig
├── tests/               Tests unitaires (Unit) et fonctionnels (Functional)
└── README.md
```

## Stratégie de branches (Git Flow simplifié)

| Branche | Rôle | Créée depuis | Fusionnée dans |
|---|---|---|---|
| `main` | Code en production, toujours stable. Chaque mise en production est taguée (`v1.0.0`). | - | - |
| `develop` | Branche d'intégration : contient les fonctionnalités terminées et testées du sprint en cours. | `main` | `main` (via `release/*`) |
| `feature/<epic>-<description>` | Développement d'une user story. | `develop` | `develop` (merge request) |
| `fix/<description>` | Correction d'un bug détecté en recette. | `develop` | `develop` |
| `hotfix/<description>` | Correction urgente d'un bug en production. | `main` | `main` **et** `develop` |
| `release/<x.y.z>` | Préparation d'une mise en production (recette, numéro de version). | `develop` | `main` et `develop` |

### Convention de nommage des branches

- Tout en **minuscules**, mots séparés par des **tirets** `-`, sans accents ni espaces.
- Préfixe obligatoire : `feature/`, `fix/`, `hotfix/` ou `release/`.
- Pour une feature : `feature/<epic>-<action>` où l'EPIC reprend celui de la carte Trello.
- Exemples : `feature/reservation-choisir-place`, `fix/calcul-places-restantes`, `hotfix/paiement-timeout`, `release/1.0.0`.

### Règles

- **Aucun commit direct** sur `main` ni sur `develop` : tout passe par une merge request (pull request).
- Une merge request doit : passer la CI (tests + analyse Sonar), être relue par au moins une autre personne, respecter la Definition of Done.
- Une branche feature = une user story du backlog Trello.

### Convention de commits

Format : `<type>: <description courte au présent>`
Types : `feat`, `fix`, `test`, `docs`, `refactor`, `chore`.
Exemple : `feat: ajout du choix de la place lors de la réservation`

## Correspondance user stories / branches

| EPIC | User story (Trello) | Branche |
|---|---|---|
| Accès | Gérer les comptes du personnel | `feature/acces-gerer-comptes-personnel` |
| Programmation | Voir la disponibilité des salles | `feature/programmation-disponibilite-salles` |
| Programmation | Ajouter un film | `feature/programmation-ajouter-film` |
| Programmation | Programmer une séance | `feature/programmation-programmer-seance` |
| Programmation | Renseigner la fiche d'un film | `feature/programmation-fiche-film` |
| Consultation | Consulter les séances | `feature/consultation-seances` |
| Consultation | Consulter les séances au guichet | `feature/consultation-seances-guichet` |
| Réservation | Voir les places restantes | `feature/reservation-places-restantes` |
| Réservation | Voir les places restantes au guichet | `feature/reservation-places-restantes-guichet` |
| Réservation | Recevoir ses billets | `feature/reservation-recevoir-billets` |
| Réservation | Choisir sa place | `feature/reservation-choisir-place` |
| Paiement | Payer en ligne | `feature/paiement-en-ligne` |
| Paiement | Plusieurs moyens de paiement au guichet | `feature/paiement-guichet` |
| Compte client | Créer un compte client | `feature/compte-creer-compte` |
| Compte client | Retrouver ses billets dans son compte | `feature/compte-mes-billets` |
| Pilotage | Suivre le taux de remplissage | `feature/pilotage-taux-remplissage` |
| Pilotage | Suivre la fréquentation | `feature/pilotage-frequentation` |
| Contrôle | Présenter son billet | `feature/controle-presenter-billet` |
| Contrôle | Scanner les billets | `feature/controle-scanner-billets` |

## Fonctionnalité livrée : la banque de films

User stories « Programmation - Ajouter un film » et « Programmation - Renseigner la fiche d'un film », développées sur la branche `feature/programmation-ajouter-film`.

- Consultation du catalogue (tout le monde), avec filtre par genre.
- Ajout, modification et suppression d'un film (programmateur et administrateur uniquement).
- Mêmes règles métier pour le site et pour l'API, grâce au service `FilmService` : titre obligatoire, durée entre 1 et 600 minutes, classification parmi une liste, date au format AAAA-MM-JJ, URL d'affiche valide.
- Sécurité : connexion par formulaire (site) ou HTTP Basic (API), mots de passe hachés, contrôle des rôles, jetons CSRF sur les formulaires.

## Comptes de démonstration

Créés par la commande `php bin/console app:initialiser-base` :

| Rôle | Email | Mot de passe | Droits |
|---|---|---|---|
| Administrateur | admin@grandcinema.fr | Admin-Cinema-2026 | Tous les droits |
| Programmateur | programmateur@grandcinema.fr | Prog-Cinema-2026 | Gestion de la banque de films |
| Client | client@grandcinema.fr | Client-Cinema-2026 | Consultation |

En production, les mots de passe se changent avec les options `--mdp-admin`, `--mdp-programmateur` et `--mdp-client`.

## API REST

Documentation interactive : `/api/doc` (Swagger UI). Bouton **Authorize** pour s'identifier.

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| GET | /api/films?genre=... | Public | Liste des films |
| GET | /api/films/{id} | Public | Détail d'un film |
| POST | /api/films | Programmateur | Ajout (201) |
| PUT | /api/films/{id} | Programmateur | Modification |
| DELETE | /api/films/{id} | Programmateur | Suppression (204) |

Codes d'erreur : 400 (données invalides, avec le détail par champ), 401 (non authentifié), 403 (rôle insuffisant), 404 (film introuvable).

## Installation (développement)

Prérequis : PHP 8.2 ou plus avec l'extension `pdo_sqlite`, et Composer.

```bash
git clone https://github.com/EloGomLap/grand-cinema.git
cd grand-cinema
composer install
php bin/console app:initialiser-base     # crée var/data.db et les comptes de démonstration
php -S localhost:8000 -t public          # puis ouvrir http://localhost:8000
```

## Tests

```bash
vendor/bin/phpunit                                   # 37 tests unitaires et fonctionnels
vendor/bin/phpunit --coverage-html coverage          # rapport de couverture (extension pcov ou xdebug)
```

Les tests fonctionnels utilisent une base SQLite dédiée (`var/test.db`), recréée avant chaque test : la base de développement n'est jamais modifiée.
