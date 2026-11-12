# UniFlow — authentification

## Configuration locale

Installez les dépendances front-end avec `npm install`, puis générez `public/assets/css/app.css` avec `npm run css:build`. Pendant le développement, utilisez `npm run css:watch` pour reconstruire le CSS à chaque modification des vues.

1. Créez un fichier .env à la racine en copiant .env.exemple, puis renseignez les accès MySQL.
2. Remplacez JWT_SECRET par une clé privée aléatoire d'au moins 32 caractères. Ne partagez pas ce secret.
3. Importez src/database/schema.sql dans MySQL. La table Users doit contenir les colonnes name, email, password, role et status définies dans ce schéma.
4. Servez le projet avec Apache/WampServer et ouvrez public/index.php. La page protégée et son contrôle JWT se trouvent dans src/Views/pages/home.php.

Si la base existe déjà, exécutez une seule fois `src/database/migrations/20261028_normalize_first_name.sql` avant d'utiliser le formulaire mis à jour. Les nouveaux comptes reçoivent le rôle `student`; pour donner l'accès admin à un compte, changez son rôle en `admin` dans la table `Users`.

L'inscription stocke les mots de passe avec password_hash; la connexion les valide avec password_verify. Les accès SQL utilisent PDO et des requêtes préparées. Le JWT HS256 est signé côté serveur et placé uniquement dans un cookie HttpOnly, SameSite=Lax, valable une heure. En production, utilisez HTTPS pour que le cookie soit également marqué Secure.


## Chat temps reel

Install the Node dependencies with `npm install`, then start the WebSocket relay alongside WampServer using `npm run chat:ws`. It listens on `127.0.0.1:8081` by default. The app keeps a short polling fallback if the relay is stopped. Import the updated `src/database/schema.sql`; existing databases need `Messages.delivered_at` and the `Message_attachments` table.

For an existing database, run once (skip the `ALTER TABLE` if the column already exists):

```sql
ALTER TABLE Messages ENGINE=InnoDB;
ALTER TABLE Messages ADD COLUMN delivered_at DATETIME NULL AFTER read_at;
CREATE TABLE Message_attachments (
    Id_attachments INT AUTO_INCREMENT PRIMARY KEY,
    Id_messages INT NOT NULL,
    name_original VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    file_size BIGINT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (Id_messages) REFERENCES Messages(Id_messages)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

For attachments up to 8 MB, set `upload_max_filesize=8M` and `post_max_size=24M` in the PHP configuration used by WampServer, then restart Apache.
