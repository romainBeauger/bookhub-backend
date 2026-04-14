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
