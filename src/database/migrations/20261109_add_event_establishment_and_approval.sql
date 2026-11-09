ALTER TABLE Events
    ADD COLUMN establishment VARCHAR(255) NULL AFTER location,
    ADD COLUMN requires_approval BOOLEAN NOT NULL DEFAULT FALSE AFTER max_participants;
