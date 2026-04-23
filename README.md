# BookHub — Backend API

Backend de la plateforme BookHub, une application de gestion de bibliothèque communautaire.

Stack : PHP 8.2 + Symfony 7.4 + API Platform + MySQL + JWT

## Prérequis

- PHP >= 8.2
- Composer
- Symfony CLI
- Accès à la base de données MySQL (Hostinger)

## Installation

### 1. Cloner le projet

```bash
git clone <url-du-repo>
cd bookhub-backend
```

### 2. Installer les dépendances

```bash
composer install
```

### 3. Configurer les variables d'environnement

Créer un fichier `.env.local` à la racine avec les valeurs suivantes :

```env
DATABASE_URL="mysql://USER:PASSWORD@HOST:3306/NOM_BASE?serverVersion=8.0&charset=utf8mb4"
JWT_PASSPHRASE=ta_passphrase
```

> Ne jamais commiter `.env.local` — il est dans le `.gitignore`.

### 4. Générer les clés JWT

```bash
php bin/console lexik:jwt:generate-keypair
```

Cela crée `config/jwt/private.pem` et `config/jwt/public.pem`.

### 5. Vérifier la connexion BDD

```bash
php bin/console doctrine:schema:validate
```

### 6. Lancer le serveur de développement

```bash
symfony server:start
```

L'API est accessible sur `http://localhost:8000`.

La documentation interactive Swagger est disponible sur `http://localhost:8000/api/doc`.

## Endpoints API

> Toutes les routes (sauf `POST /api/auth/register` et `POST /api/auth/login`) requièrent un token JWT dans le header `Authorization: Bearer <token>`.

### Authentification

| Méthode | Route | Rôle | Description |
|---------|-------|------|-------------|
| POST | `/api/auth/register` | Public | Créer un compte |
| POST | `/api/auth/login` | Public | Se connecter, retourne un JWT |

### Livres

| Méthode | Route | Rôle | Description |
|---------|-------|------|-------------|
| GET | `/api/books` | USER | Liste paginée avec filtres (titre, auteur, catégorie, dispo, dates, tri) |
| GET | `/api/books/{id}` | USER | Détail d'un livre |
| POST | `/api/books` | LIBRARIAN | Créer un livre |
| PATCH | `/api/books/{id}` | LIBRARIAN | Modifier un livre |
| DELETE | `/api/books/{id}` | LIBRARIAN | Supprimer un livre |

### Emprunts

| Méthode | Route | Rôle | Description |
|---------|-------|------|-------------|
| POST | `/api/loans` | USER | Emprunter un livre |
| GET | `/api/loans/me` | USER | Mes emprunts |
| GET | `/api/loans` | LIBRARIAN | Tous les emprunts actifs (filtre `?is_late=true`) |
| PATCH | `/api/loans/{id}/return` | USER | Demander le retour d'un livre |
| PATCH | `/api/loans/{id}/validate-return` | LIBRARIAN | Valider le retour |

### Réservations

| Méthode | Route | Rôle | Description |
|---------|-------|------|-------------|
| POST | `/api/reservations` | USER | Créer une réservation |
| GET | `/api/reservations/me` | USER | Mes réservations |
| GET | `/api/reservations` | LIBRARIAN | Toutes les réservations (filtres status, bookId, userName) |
| PATCH | `/api/reservations/{id}/ready` | LIBRARIAN | Marquer comme prête |
| PATCH | `/api/reservations/{id}/validate` | LIBRARIAN | Valider et créer l'emprunt |
| PATCH | `/api/reservations/{id}/cancel` | USER | Annuler une réservation |

### Avis

| Méthode | Route | Rôle | Description |
|---------|-------|------|-------------|
| GET | `/api/books/{id}/reviews` | USER | Avis d'un livre |
| POST | `/api/books/{id}/reviews` | USER | Créer un avis (nécessite d'avoir emprunté le livre) |
| GET | `/api/reviews/me` | USER | Mes avis |
| GET | `/api/reviews` | LIBRARIAN | Tous les avis avec stats (filtre `?status=pending\|confirmed\|all`) |
| PATCH | `/api/reviews/{id}` | USER | Modifier un avis |
| PATCH | `/api/reviews/{id}/moderate` | LIBRARIAN | Modérer un avis |
| DELETE | `/api/reviews/{id}` | USER | Supprimer un avis |

### Profil utilisateur

| Méthode | Route | Rôle | Description |
|---------|-------|------|-------------|
| GET | `/api/users/me` | USER | Voir son profil |
| PATCH | `/api/users/me` | USER | Modifier nom, prénom, email, téléphone |
| PATCH | `/api/users/me/password` | USER | Changer le mot de passe |
| DELETE | `/api/users/me` | USER | Anonymiser et supprimer son compte |

### Statistiques

| Méthode | Route | Rôle | Description |
|---------|-------|------|-------------|
| GET | `/api/stats/loans` | LIBRARIAN | Emprunts actifs, en retard, liste des retardataires |
| GET | `/api/stats/catalogue` | LIBRARIAN | Total livres, réservations, top 5 livres empruntés |

## Tests

Le projet utilise PHPUnit. Les tests couvrent les services métier et les contrôleurs principaux.

```bash
# Lancer tous les tests
php bin/phpunit

# Avec rapport de couverture (texte)
php bin/phpunit --coverage-text

# Un fichier en particulier
php bin/phpunit tests/Service/LoanServiceTest.php
```

Suites de tests existantes :

| Fichier | Couverture |
|---------|-----------|
| `tests/Service/LoanServiceTest.php` | LoanService (emprunt, retour, retard) |
| `tests/Service/ReservationServiceTest.php` | ReservationService |
| `tests/Service/AuthServiceTest.php` | AuthService |
| `tests/Controller/LoanControllerTest.php` | Endpoints emprunts |
| `tests/Controller/UserControllerTest.php` | Endpoints profil |
| `tests/Controller/BookControllerTest.php` | Endpoints livres |
| `tests/Controller/StatsControllerTest.php` | Endpoints stats |

> L'objectif de couverture du projet est de **20% minimum**.

## Fixtures — Données de test

Les fixtures permettent de pré-remplir la base avec des données cohérentes.

```bash
# Charger les fixtures (réinitialise les données existantes)
php bin/console doctrine:fixtures:load
```

### Comptes de test

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Utilisateur | `user@bookhub.fr` | `user1234` |
| Bibliothécaire | `librarian@bookhub.fr` | `librarian1234` |
| Administrateur | `admin@bookhub.fr` | `admin1234` |

### Catalogue

Les fixtures chargent **50+ livres** répartis dans ~20 catégories (Roman, Classique, Science-fiction, Fantastique, Policier, Horreur, Aventure, Historique, Philosophie, Développement personnel, Finance, Science, etc.).

Les couvertures sont récupérées automatiquement depuis Open Library (`covers.openlibrary.org`) à partir de l'ISBN.

## Structure

```
src/
├── Controller/     # Contrôleurs API
├── Entity/         # Entités Doctrine
├── Repository/     # Repositories
└── ApiResource/    # Ressources API Platform
```

## Équipe

- Romain — back + front
- Youssef — back + front
