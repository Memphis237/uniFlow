ALTER TABLE Services
    MODIFY description TEXT NULL,
    ADD COLUMN unit VARCHAR(80) NOT NULL DEFAULT 'la prestation' AFTER description;
