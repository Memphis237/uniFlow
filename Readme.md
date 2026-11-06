# UniFlow

## Configuration locale

1. Copiez `.env.exemple` vers `.env`, puis renseignez les accès MySQL et un `JWT_SECRET` aléatoire d’au moins 32 caractères. Ne partagez jamais `.env`.
2. Pour une nouvelle base, importez `src/database/schema.sql`.
3. Pour une base existante, appliquez les migrations pertinentes de `src/database/migrations/` une seule fois ; certaines migrations de profil/favoris peuvent précéder celles de `develop`.
4. Servez `public/` avec Apache/MAMP. Pour les messages en temps réel, installez les dépendances Node (`npm install`) puis démarrez le relais avec `npm run chat:ws`.

L’application comprend l’inscription et la connexion avec mots de passe hachés, une session JWT en cookie HttpOnly, le profil utilisateur, le catalogue de services, les favoris, les réservations, les avis et les messages. En production, utilisez HTTPS.

## Profil, services et favoris

Le profil se trouve sur `index.php?page=profile`. Le catalogue (`index.php?page=services`) permet de publier des services et des images. Les pages de favoris sont accessibles depuis la navigation ; les anciennes routes `?action=favorites` et `?action=services` restent disponibles.

Les services créés par l’utilisateur peuvent être supprimés tant qu’ils ne sont pas liés à une réservation ou à un avis. Les images téléversées sont limitées aux formats vérifiés côté serveur et sont stockées sous `public/uploads/`.

Les migrations présentes dans `src/database/migrations/` ajoutent les champs de profil et élargissent le chemin des médias. Appliquez-les uniquement si ces changements ne sont pas déjà dans votre base.

## Messagerie en temps réel

Installez les dépendances Node puis lancez `npm run chat:ws`. Le relais écoute `127.0.0.1:8081` par défaut. Le chat conserve un mode de secours par interrogation périodique.

Pour une base antérieure à la messagerie, vérifiez `Messages.delivered_at` et `Message_attachments` dans `src/database/schema.sql` et appliquez les adaptations manquantes. La configuration PHP doit autoriser les pièces jointes selon les limites configurées (`upload_max_filesize` et `post_max_size`).
