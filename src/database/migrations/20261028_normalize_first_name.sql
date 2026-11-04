-- Run once on databases created from the previous schema to rename the first-name column.
ALTER TABLE Users
    CHANGE COLUMN `prénom` first_name VARCHAR(255) NOT NULL,
    MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'student';
