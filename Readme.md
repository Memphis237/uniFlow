# UniFlow

UniFlow est une application destinée à la communauté étudiante. Elle permet de proposer et de rechercher des services, d’échanger des messages et de participer à la vie du campus.

## Prérequis

- PHP avec les extensions PDO et PDO MySQL activées.
- MySQL ou MariaDB.
- Apache, par exemple avec WampServer.
- Node.js et npm pour Tailwind CSS, les outils de formatage et le serveur de messagerie.
- Composer pour installer les outils de formatage PHP.

## Installation

1. Clone le projet dans le dossier servi par Apache.
2. Crée un fichier `.env` à la racine du projet et renseigne les paramètres suivants :

   ```dotenv
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=uniflow
   DB_USER=root
   DB_PASSWORD=
   JWT_SECRET=remplacer-par-une-cle-aleatoire-de-32-caracteres-minimum
   ```

   Utilise une clé privée aléatoire d’au moins 32 caractères pour `JWT_SECRET`. Ne partage pas ce secret.

3. Crée la base de données, puis importe `src/database/schema.sql`. Ce schéma contient déjà les tables et colonnes utilisées par l’application, notamment `Message_attachments` et `Messages.delivered_at`. Il n’est pas nécessaire de recréer ces éléments avec du SQL séparé.
4. Installe les dépendances JavaScript et génère le CSS Tailwind :

   ```bash
   npm install
   npm run css:build
   ```

5. Installe les outils de développement PHP :

   ```bash
   composer install
   ```

6. Démarre Apache et ouvre `public/index.php` depuis ton navigateur. Avec WampServer, l’adresse ressemble généralement à `http://localhost/uniFlow/public/`.

## Développement

Pour reconstruire automatiquement le CSS Tailwind à chaque changement des vues, lance :

```bash
npm run css:watch
```

Le fichier généré est `public/assets/css/app.css`. Les vues PHP et les fichiers JavaScript du dossier `public/assets/js` sont analysés pour inclure les classes Tailwind nécessaires.

Le serveur WebSocket fonctionne séparément d’Apache. Dans un autre terminal, lance :

```bash
npm run chat:ws
```

Il écoute par défaut sur `127.0.0.1:8081`. Si le relais est arrêté, l’application conserve une actualisation périodique des messages.

Pour autoriser les pièces jointes de 8 Mo, configure `upload_max_filesize=8M` et `post_max_size=24M` dans le fichier `php.ini` utilisé par Apache, puis redémarre Apache.

## Formatage du code

Le fichier `.editorconfig` définit les règles d’indentation partagées. Le code PHP suit PSR-12 et le JavaScript est formaté avec Prettier.

```bash
composer format:php
composer format:php:check
npm run format:js
npm run format:js:check
```

## Sécurité et comptes

Les mots de passe sont enregistrés avec `password_hash()` et vérifiés avec `password_verify()`. Les requêtes SQL passent par PDO et des requêtes préparées. Le jeton JWT est signé côté serveur et stocké dans un cookie `HttpOnly` avec `SameSite=Lax` pendant une heure. En production, utilise HTTPS afin que le cookie soit également marqué `Secure`.

Les nouveaux comptes reçoivent le rôle `student`. Pour attribuer les droits d’administration, modifie le rôle du compte concerné dans la table `Users`.