-- EventOra database schema
-- Mirrors the ER diagram in the PR1 proposal (section 6) exactly:
-- USER, SOCIETY, EVENT, TICKET, CHECKIN, FEEDBACK + their FK relationships.
--
-- Run this against an empty `eventora` database:
--   mysql -u eventora_user -p eventora < migrations/001_create_schema.sql

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────────
-- USER
-- role: attendee | organiser | admin  (one user holds exactly one role,
-- per the PR1 proposal's entity description)
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(150)        NOT NULL,
    email         VARCHAR(190)        NOT NULL UNIQUE,
    password_hash VARCHAR(255)        NOT NULL,
    role          ENUM('attendee', 'organiser', 'admin') NOT NULL DEFAULT 'attendee',
    matric_no     VARCHAR(20)         NULL,
    society       VARCHAR(150)        NULL COMMENT 'Set for organiser accounts — which society they represent',
    created_at    TIMESTAMP           NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- SOCIETY
-- advisor_id links to a USER with the admin role (per PR1 proposal,
-- section 6.1)
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS societies (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(150) NOT NULL UNIQUE,
    faculty     VARCHAR(150) NOT NULL,
    advisor_id  INT UNSIGNED NULL,
    description TEXT         NULL,
    members     INT UNSIGNED NOT NULL DEFAULT 0,
    founded     VARCHAR(10)  NULL,
    cover_url   VARCHAR(500) NULL,
    logo_color  VARCHAR(7)   NULL DEFAULT '#520000',
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_society_advisor FOREIGN KEY (advisor_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- EVENT
-- status flow: pending_approval > approved > completed / cancelled
-- (the PR1 proposal's full flow is draft > pending_approval > approved >
-- ongoing > completed / cancelled; "draft" and "ongoing" are left out of
-- the enum for now since the frontend doesn't use them yet — add them
-- back here if a later feature needs them)
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS events (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    society_id    INT UNSIGNED NOT NULL,
    title         VARCHAR(200) NOT NULL,
    description   TEXT         NOT NULL,
    category      ENUM('Academic', 'Sports', 'Cultural', 'Religious', 'Workshop', 'Career') NOT NULL,
    venue         VARCHAR(200) NOT NULL,
    starts_at     DATETIME     NOT NULL,
    ends_at       DATETIME     NOT NULL,
    capacity      INT UNSIGNED NOT NULL,
    price         DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    status        ENUM('pending_approval', 'approved', 'completed', 'cancelled') NOT NULL DEFAULT 'pending_approval',
    rejection_reason VARCHAR(500) NULL,
    image_url     VARCHAR(500) NULL,
    organiser_id  INT UNSIGNED NOT NULL COMMENT 'USER who created the event (must have role=organiser)',
    created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_society   FOREIGN KEY (society_id)   REFERENCES societies(id) ON DELETE CASCADE,
    CONSTRAINT fk_event_organiser FOREIGN KEY (organiser_id) REFERENCES users(id)     ON DELETE RESTRICT,
    INDEX idx_events_status (status),
    INDEX idx_events_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- TICKET
-- status: confirmed | checked_in | cancelled | waitlisted
-- qr_code is generated server-side (see TicketController::register)
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS tickets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id    INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    qr_code     VARCHAR(100) NOT NULL UNIQUE,
    status      ENUM('confirmed', 'checked_in', 'cancelled', 'waitlisted') NOT NULL DEFAULT 'confirmed',
    issued_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    -- A user can only hold one active ticket per event (prevents
    -- duplicate registrations from double-clicking "Register").
    UNIQUE KEY uniq_ticket_event_user (event_id, user_id),
    INDEX idx_tickets_qr (qr_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- CHECKIN
-- checked_in_by is the organiser's user ID — creates an audit trail of
-- who scanned which ticket (per PR1 proposal, section 6.1)
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS checkins (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id      INT UNSIGNED NOT NULL UNIQUE COMMENT 'One check-in per ticket',
    checked_in_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_in_by  INT UNSIGNED NOT NULL,
    CONSTRAINT fk_checkin_ticket FOREIGN KEY (ticket_id)     REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_checkin_user   FOREIGN KEY (checked_in_by) REFERENCES users(id)   ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────
-- FEEDBACK
-- Only users with a checked_in ticket for the event may submit feedback —
-- enforced in FeedbackController, not at the schema level.
-- ─────────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS feedback (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id     INT UNSIGNED NOT NULL,
    user_id      INT UNSIGNED NOT NULL,
    rating       TINYINT UNSIGNED NOT NULL COMMENT '1-5',
    comment      TEXT NULL,
    created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_feedback_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    CONSTRAINT fk_feedback_user  FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
    UNIQUE KEY uniq_feedback_event_user (event_id, user_id),
    CONSTRAINT chk_feedback_rating CHECK (rating BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
