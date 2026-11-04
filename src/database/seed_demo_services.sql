INSERT INTO Categories (name, description)
SELECT 'Soutien scolaire', 'Cours et accompagnement entre étudiants'
WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE name = 'Soutien scolaire');

INSERT INTO Categories (name, description)
SELECT 'Informatique', 'Aide et services numériques'
WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE name = 'Informatique');

INSERT INTO Categories (name, description)
SELECT 'Langues', 'Échange et pratique des langues'
WHERE NOT EXISTS (SELECT 1 FROM Categories WHERE name = 'Langues');

INSERT INTO Services (title, price, location, status, description, Id_categories, Id_users)
SELECT 'Cours de mathématiques', 10.00, 'Bibliothèque du campus', 'active',
       'Révisions et accompagnement en mathématiques, adaptés à ton niveau.',
       c.Id_categories, 1
FROM Categories c
WHERE c.name = 'Soutien scolaire'
  AND EXISTS (SELECT 1 FROM Users WHERE Id_users = 1)
  AND NOT EXISTS (
      SELECT 1 FROM Services
      WHERE title = 'Cours de mathématiques' AND Id_users = 1
  );

INSERT INTO Services (title, price, location, status, description, Id_categories, Id_users)
SELECT 'Aide en programmation', 15.00, 'En ligne', 'active',
       'Débloque un exercice ou découvre les bases de la programmation avec un étudiant.',
       c.Id_categories, 1
FROM Categories c
WHERE c.name = 'Informatique'
  AND EXISTS (SELECT 1 FROM Users WHERE Id_users = 1)
  AND NOT EXISTS (
      SELECT 1 FROM Services
      WHERE title = 'Aide en programmation' AND Id_users = 1
  );

INSERT INTO Services (title, price, location, status, description, Id_categories, Id_users)
SELECT 'Conversation en anglais', 8.00, 'Campus ESEO', 'active',
       'Pratique l’anglais à l’oral dans une ambiance détendue.',
       c.Id_categories, 1
FROM Categories c
WHERE c.name = 'Langues'
  AND EXISTS (SELECT 1 FROM Users WHERE Id_users = 1)
  AND NOT EXISTS (
      SELECT 1 FROM Services
      WHERE title = 'Conversation en anglais' AND Id_users = 1
  );
