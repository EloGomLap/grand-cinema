# Grand Cinéma - Application de réservation

[![Quality Gate](https://sonarcloud.io/api/project_badges/measure?project=EloGomLap_grand-cinema&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=EloGomLap_grand-cinema)
[![Sécurité](https://sonarcloud.io/api/project_badges/measure?project=EloGomLap_grand-cinema&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=EloGomLap_grand-cinema)
[![Fiabilité](https://sonarcloud.io/api/project_badges/measure?project=EloGomLap_grand-cinema&metric=reliability_rating)](https://sonarcloud.io/summary/new_code?id=EloGomLap_grand-cinema)
[![Maintenabilité](https://sonarcloud.io/api/project_badges/measure?project=EloGomLap_grand-cinema&metric=sqale_rating)](https://sonarcloud.io/summary/new_code?id=EloGomLap_grand-cinema)
[![Duplication](https://sonarcloud.io/api/project_badges/measure?project=EloGomLap_grand-cinema&metric=duplicated_lines_density)](https://sonarcloud.io/summary/new_code?id=EloGomLap_grand-cinema)

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
| Qualité | SonarCloud (analyse automatique de chaque push et de chaque merge request) |
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

## Fonctionnalités livrées (MVP)

Les 19 user stories du backlog sont implémentées, chacune sur sa branche `feature/...` fusionnée dans `develop` par merge request (PR #2 et #4 à #21), puis livrées sur `main`.

| EPIC | Ce que l'application permet | Où |
|---|---|---|
| Accès | L'administrateur crée les comptes du personnel et change leur rôle | `/admin/utilisateurs` |
| Programmation | Banque de films (ajout, fiche, modification, suppression), programmation des séances sans chevauchement (durée du film + 20 min de ménage), disponibilité des salles sur une journée | `/films`, `/programmation/seances`, `/programmation/salles` |
| Consultation | Séances à venir par jour et par film (en ligne), séances du jour (guichet) | `/seances`, `/guichet` |
| Réservation | Places restantes en temps réel, plan de salle et choix des places (10 max.), place bloquée 15 minutes le temps du paiement, impossible de vendre deux fois la même place | `/seances/{id}`, `/guichet/seances/{id}` |
| Paiement | Paiement en ligne par carte, au guichet en espèces (monnaie à rendre), carte ou chèque | `/reservations/{id}/paiement`, `/guichet/seances/{id}/vente` |
| Billets et compte client | Création de compte, billets avec QR code envoyés par email, historique « Mes billets » | `/inscription`, `/mon-compte/billets`, `/billets/{code}` |
| Contrôle | Présentation du billet (QR code) et scan à l'entrée : valide, inconnu, non payé, déjà utilisé, trop tôt, séance terminée | `/controle` |
| Pilotage | Taux de remplissage (par séance, par film, moyen) et fréquentation (entrées par jour et par film, recettes, part de la vente en ligne) | `/pilotage` |

Mêmes règles métier pour le site et pour l'API grâce aux services (`FilmService`, `SeanceService`, `ReservationService`, `PaiementService`, `ControleService`, `PilotageService`...).

Sécurité : connexion par formulaire (site) ou HTTP Basic (API), mots de passe hachés, contrôle des rôles (hiérarchie : l'administrateur hérite des rôles programmateur, guichetier et contrôleur), un client ne voit que ses propres réservations (voter), jetons CSRF sur les formulaires.

Prestataires simulés en attendant les comptes de production : le paiement passe par une passerelle simulée (cartes de test au format Stripe : `4242 4242 4242 4242` acceptée, `4000 0000 0000 0002` refusée) et les emails sont écrits dans `var/log/emails.log`. Les deux sont derrière une interface (`PasserellePaiementInterface`, `EnvoiEmailInterface`) : brancher Stripe ou Brevo ne touche qu'une ligne de `config/services.yaml`.

## Comptes de démonstration

Créés par la commande `php bin/console app:initialiser-base` (avec 3 salles, des films et 70 séances sur 14 jours) :

| Rôle | Email | Mot de passe | Droits |
|---|---|---|---|
| Administrateur | admin@grandcinema.fr | Admin-Cinema-2026 | Tous les droits, gestion du personnel |
| Programmateur | programmateur@grandcinema.fr | Prog-Cinema-2026 | Films, séances, salles, pilotage |
| Guichetier | guichetier@grandcinema.fr | Guichet-Cinema-2026 | Vente au guichet |
| Contrôleur | controleur@grandcinema.fr | Controle-Cinema-2026 | Contrôle des billets |
| Client | client@grandcinema.fr | Client-Cinema-2026 | Réservation et paiement en ligne, mes billets |

En production, les mots de passe se changent avec les options `--mdp-admin`, `--mdp-programmateur`, `--mdp-guichetier`, `--mdp-controleur` et `--mdp-client`.

## API REST

Documentation interactive : `/api/doc` (Swagger UI). Bouton **Authorize** pour s'identifier.

| Méthode | Route | Accès | Rôle |
|---|---|---|---|
| GET | /api/films?genre=... | Public | Liste des films |
| GET | /api/films/{id} | Public | Détail d'un film |
| POST | /api/films | Programmateur | Ajout (201) |
| PUT | /api/films/{id} | Programmateur | Modification |
| DELETE | /api/films/{id} | Programmateur | Suppression (204, 409 si le film a des séances) |
| GET | /api/seances?date=...&film=... | Public | Séances à venir |
| GET | /api/seances/{id} | Public | Détail d'une séance |
| GET | /api/seances/{id}/places | Public | Plan de salle et places restantes |
| POST | /api/seances | Programmateur | Programmer une séance (201, 400 si la salle est déjà occupée) |
| GET | /api/salles | Public | Liste des salles |
| GET | /api/salles/disponibilite?date=... | Programmateur | Disponibilité des salles |
| POST | /api/comptes | Public | Créer un compte client (201) |
| POST | /api/reservations | Connecté | Réserver des places (201, 409 si une place est déjà prise) |
| GET | /api/reservations/{id} | Propriétaire | Détail d'une réservation |
| POST | /api/reservations/{id}/paiement | Propriétaire | Payer par carte (402 si refusé) |
| GET | /api/reservations/mes-billets | Connecté | Mes billets |
| POST | /api/billets/{code}/validation | Contrôleur | Scanner un billet (404 inconnu, 409 refusé) |
| GET | /api/pilotage/remplissage | Programmateur | Taux de remplissage |
| GET | /api/pilotage/frequentation | Programmateur | Fréquentation et recettes |

Codes d'erreur : 400 (données invalides, avec le détail par champ), 401 (non authentifié), 403 (rôle insuffisant), 404 (ressource introuvable), 409 (conflit).

## Qualité (SonarCloud)

Tableau de bord : https://sonarcloud.io/project/overview?id=EloGomLap_grand-cinema

- **Analyse automatique** : SonarCloud analyse la branche `main`, la branche `develop` et chaque merge request, et publie le résultat dans la merge request sur GitHub.
- **Quality gate** (sur le nouveau code) : note A en fiabilité, sécurité et maintenabilité, 100 % des points sensibles (security hotspots) relus, moins de 3 % de duplication. Une merge request qui ne passe pas la quality gate n'est pas fusionnée.
- **Branches durables** : `main` et `develop` (réglage `sonar.branch.longLivedBranches.regex`), pour que les merge requests vers `develop` soient comparées à `develop`.
- **Couverture** : mesurée par PHPUnit dans l'intégration continue (`--coverage-text`), l'analyse automatique de SonarCloud ne l'importe pas.
- Un problème signalé à tort ou assumé est marqué « accepté » dans SonarCloud avec sa justification, plutôt que de modifier le code sans raison.

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
vendor/bin/phpunit                                   # 114 tests unitaires et fonctionnels
vendor/bin/phpunit --coverage-html coverage          # rapport de couverture (extension pcov ou xdebug)
```

Les tests fonctionnels utilisent une base SQLite dédiée (`var/test.db`), recréée avant chaque test : la base de développement n'est jamais modifiée.
