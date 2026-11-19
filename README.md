# Grand Cinéma - Application de réservation

Application web de réservation de places pour le cinéma « Le Grand Cinéma ».
Objectif métier : augmenter la fréquentation de 30 % en 12 mois et absorber les pics du festival de Jazz.

## Stack technique

| Couche | Technologie |
|---|---|
| Back-end / API REST | PHP 8.3 - Symfony 7 (API Platform / NelmioApiDocBundle pour Swagger) |
| Base de données | PostgreSQL 16 (ORM Doctrine) |
| Front-end | Twig + JavaScript (interface client et interface guichet) |
| Tests | PHPUnit (unitaires), WebTestCase (fonctionnels) |
| Qualité | SonarCloud |
| CI | GitHub Actions |

## Organisation du dépôt

```
grand-cinema/
├── database/        Script SQL de création de la base
├── docs/            Documentation (MCD, Swagger exporté)
├── src/             Code Symfony (Controller, Entity, Repository, Service)
├── tests/           Tests unitaires et fonctionnels
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

## Installation (développement)

```bash
git clone <url-du-depot>
cd grand-cinema
composer install
psql -U postgres -f database/schema.sql
symfony server:start
```
