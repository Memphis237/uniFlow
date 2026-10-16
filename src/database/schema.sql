
CREATE DATABASE IF NOT EXISTS uniflow
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE uniflow;

-- 1. Users
CREATE TABLE Users (
    Id_users INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    prénom VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    adresse VARCHAR(255),
    role VARCHAR(50) NOT NULL DEFAULT 'student',
    biographie VARCHAR(255),
    avatar VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 2. Categories
CREATE TABLE Categories (
    Id_categories INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description VARCHAR(255)
);

-- 3. Services
CREATE TABLE Services (
    Id_services INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    price DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    location VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    description VARCHAR(255),
    Id_categories INT NOT NULL,
    Id_users INT NOT NULL,

    FOREIGN KEY (Id_categories)
        REFERENCES Categories(Id_categories)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- 4. Bookings
CREATE TABLE Bookings (
    Id_bookings INT AUTO_INCREMENT PRIMARY KEY,
    scheduled_at DATETIME NOT NULL,
    message VARCHAR(255),
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    Id_services INT NOT NULL,
    Id_users INT NOT NULL,

    FOREIGN KEY (Id_services)
        REFERENCES Services(Id_services)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- 5. Conversations
CREATE TABLE Conversations (
    Id_conversations INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 6. Messages
CREATE TABLE Messages (
    Id_messages INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    read_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    Id_users INT NOT NULL,
    Id_conversations INT NOT NULL,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (Id_conversations)
        REFERENCES Conversations(Id_conversations)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- 7. Association Users / Conversations
CREATE TABLE lier (
    Id_users INT NOT NULL,
    Id_conversations INT NOT NULL,

    PRIMARY KEY (Id_users, Id_conversations),

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (Id_conversations)
        REFERENCES Conversations(Id_conversations)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- 8. Reviews
CREATE TABLE Reviews (
    Id_reviews INT AUTO_INCREMENT PRIMARY KEY,
    rating INT NOT NULL,
    comment VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    Id_bookings INT NOT NULL,
    Id_users INT NOT NULL,

    CONSTRAINT chk_rating CHECK (rating BETWEEN 1 AND 5),

    FOREIGN KEY (Id_bookings)
        REFERENCES Bookings(Id_bookings)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- 9. Favorites
CREATE TABLE Favorites (
    Id_favorites INT AUTO_INCREMENT PRIMARY KEY,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    Id_services INT NOT NULL,
    Id_users INT NOT NULL,

    UNIQUE (Id_services, Id_users),

    FOREIGN KEY (Id_services)
        REFERENCES Services(Id_services)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- 10. Notifications
CREATE TABLE Notifications (
    Id_notifications INT AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    read_at DATETIME NULL,
    link VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    Id_users INT NOT NULL,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- 11. Events
CREATE TABLE Events (
    Id_events INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    location VARCHAR(255),
    starts_at DATETIME NOT NULL,
    ends_at DATETIME,
    max_participants INT,
    status VARCHAR(255) NOT NULL DEFAULT 'draft',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    Id_users INT NOT NULL,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
);

-- 12. Events_participants
-- Une ligne par utilisateur inscrit à un événement
CREATE TABLE Events_participants (
    Id_events INT NOT NULL,
    Id_users INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'registered',
    registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (Id_events, Id_users),

    FOREIGN KEY (Id_events)
        REFERENCES Events(Id_events)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (Id_users)
        REFERENCES Users(Id_users)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);

-- 13. Media
CREATE TABLE Media (
    Id_media INT AUTO_INCREMENT PRIMARY KEY,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    name_media VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    file_size BIGINT,
    Id_services INT NULL,
    Id_events INT NULL,

    CONSTRAINT chk_media_parent CHECK (
        (Id_services IS NOT NULL AND Id_events IS NULL)
        OR
        (Id_services IS NULL AND Id_events IS NOT NULL)
    ),

    FOREIGN KEY (Id_services)
        REFERENCES Services(Id_services)
        ON UPDATE CASCADE
        ON DELETE CASCADE,

    FOREIGN KEY (Id_events)
        REFERENCES Events(Id_events)
        ON UPDATE CASCADE
        ON DELETE CASCADE
);
