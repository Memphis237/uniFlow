# UniFlow — profil utilisateur

## Configuration de la base de données

1. Copiez `.env.exemple` vers `.env`, puis adaptez les paramètres de connexion MySQL. Les valeurs proposées correspondent à une installation MAMP locale classique.
2. Pour une nouvelle base, importez `src/database/schema.sql` dans MySQL.
3. Pour une base UniFlow déjà créée, appliquez une seule fois `src/database/migrations/20261103_add_profile_fields.sql` ; ne lancez pas cette migration après avoir importé le schéma à jour.
4. Réglez `DB_PROFILE_USER_ID` sur l’identifiant du profil à afficher. Si l’application dispose d’une session avec `user_id`, cet identifiant de session est utilisé à la place.

Le formulaire permet de modifier le nom, le prénom, l’e-mail, la formation, l’école, la biographie et l’URL de l’avatar. Les modifications sont validées puis enregistrées dans la table `Users`.

## Favoris

Les services actifs apparaissent dans `?action=services`. Les boutons ajoutent ou retirent un service des favoris ; `?action=favorites` affiche la liste enregistrée pour l’utilisateur courant. Chaque modification est envoyée en POST avec un jeton CSRF. Les favoris sont liés à `DB_PROFILE_USER_ID` jusqu’à ce que l’authentification fournisse `$_SESSION['user_id']`.

La base locale de démonstration a aussi des services exemples. Pour repeupler un environnement de développement vide après avoir importé le schéma et créé l’utilisateur 1, exécute `src/database/seed_demo_services.sql`.

Le formulaire du catalogue permet de publier un service, d’importer une image JPG/PNG/WebP/GIF (5 Mo maximum) ou de coller le lien direct d’une image publique (`http://` ou `https://`). Pour utiliser une image Google, enregistre-la d’abord sur ton appareil puis importe le fichier : les liens des résultats Google renvoient souvent vers une page et ne sont pas des liens d’image partageables. Les services créés par l’utilisateur courant peuvent être supprimés depuis leur carte ; une réservation ou un avis lié empêche la suppression. Pour une base créée avant cette évolution, applique aussi `src/database/migrations/20261103_expand_media_file_path.sql` afin que `Media.file_path` accepte les liens longs.