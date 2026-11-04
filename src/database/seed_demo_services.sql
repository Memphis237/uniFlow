USE uniflow;

-- Données de démonstration facultatives, rattachées au premier compte étudiant actif.
-- Le script peut être relancé : catégories, services et médias existants sont conservés.
INSERT INTO Categories (name, description)
SELECT demo.name, 'Services proposés par la communauté étudiante'
FROM (
    SELECT 'Coiffure & beauté' AS name UNION ALL
    SELECT 'Cours & soutien' UNION ALL
    SELECT 'Déménagement' UNION ALL
    SELECT 'Informatique' UNION ALL
    SELECT 'Photo & créa' UNION ALL
    SELECT 'Autres'
) AS demo
WHERE NOT EXISTS (SELECT 1 FROM Categories c WHERE c.name = demo.name);

INSERT INTO Services (title, price, location, status, description, unit, Id_categories, Id_users)
SELECT d.title, d.price, d.location, 'active', d.description, d.unit, c.Id_categories, u.Id_users
FROM (
    SELECT 'Une coupe fraîche, un petit prix' title, 12.00 price, 'À 500 m du campus' location, 'Coupe personnalisée, conseils et finitions. Contacte-moi pour convenir d’un créneau.' description, 'la coupe' unit, 'Coiffure & beauté' category
    UNION ALL SELECT 'Les maths, sans prise de tête', 15.00, 'Sur le campus ou en visio', 'Cours particuliers adaptés à ton niveau, avec exercices et méthodes de révision.', 'l’heure', 'Cours & soutien'
    UNION ALL SELECT 'Un coup de main pour tes cartons', 10.00, 'Lyon et ses alentours', 'Aide pour porter, emballer et organiser ton déménagement étudiant.', 'l’heure', 'Déménagement'
    UNION ALL SELECT 'Ton ordinateur comme neuf', 18.00, 'Sur le campus', 'Diagnostic et aide au dépannage logiciel pour ton ordinateur.', 'l’intervention', 'Informatique'
    UNION ALL SELECT 'Des portraits qui te ressemblent', 20.00, 'Lyon, campus et centre-ville', 'Séance photo portrait avec sélection et retouche des images.', 'la séance', 'Photo & créa'
    UNION ALL SELECT 'Un CV qui fait la différence', 8.00, 'En visio', 'Relecture et mise en forme de ton CV avec des conseils personnalisés.', 'le CV', 'Autres'
) AS d
JOIN Categories c ON c.name = d.category
JOIN Users u ON u.Id_users = (SELECT MIN(active.Id_users) FROM Users active WHERE active.status = 'active')
WHERE NOT EXISTS (
    SELECT 1 FROM Services existing
    WHERE existing.title = d.title AND existing.Id_users = u.Id_users
);

INSERT INTO Media (name_media, file_path, file_size, Id_services)
SELECT CONCAT('demo-service-', s.Id_services, '.jpg'), d.image_url, NULL, s.Id_services
FROM (
    SELECT 'Une coupe fraîche, un petit prix' title, 'https://images.unsplash.com/photo-1501727575897-8c9b8f6b3d0b?auto=format&fit=crop&w=900&q=80' image_url
    UNION ALL SELECT 'Les maths, sans prise de tête', 'https://images.unsplash.com/photo-1523240795612-9a054b0db644?auto=format&fit=crop&w=900&q=80'
    UNION ALL SELECT 'Un coup de main pour tes cartons', 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80'
    UNION ALL SELECT 'Ton ordinateur comme neuf', 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=900&q=80'
    UNION ALL SELECT 'Des portraits qui te ressemblent', 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=900&q=80'
    UNION ALL SELECT 'Un CV qui fait la différence', 'https://images.unsplash.com/photo-1521737711867-e3b97375f902?auto=format&fit=crop&w=900&q=80'
) AS d
JOIN Services s ON s.title = d.title
JOIN Users u ON u.Id_users = s.Id_users
WHERE u.Id_users = (SELECT MIN(active.Id_users) FROM Users active WHERE active.status = 'active')
  AND NOT EXISTS (SELECT 1 FROM Media m WHERE m.Id_services = s.Id_services);
