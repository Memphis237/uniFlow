# UniFlow — authentification

## Configuration locale

1. Créez un fichier .env à la racine en copiant .env.exemple, puis renseignez les accès MySQL.
2. Remplacez JWT_SECRET par une clé privée aléatoire d'au moins 32 caractères. Ne partagez pas ce secret.
3. Importez src/database/schema.sql dans MySQL. La table Users doit contenir les colonnes name, email, password, role et status définies dans ce schéma.
4. Servez le projet avec Apache/WampServer et ouvrez public/index.php. La page protégée et son contrôle JWT se trouvent dans src/Views/pages/home.php.

L'inscription stocke les mots de passe avec password_hash; la connexion les valide avec password_verify. Les accès SQL utilisent PDO et des requêtes préparées. Le JWT HS256 est signé côté serveur et placé dans un cookie HttpOnly, SameSite=Lax, valable une heure. En production, utilisez HTTPS pour que le cookie soit également marqué Secure.
