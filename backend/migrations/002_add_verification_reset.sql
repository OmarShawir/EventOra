-- EventOra: Add email verification & password reset token columns
-- Run against the existing `eventora` database:

ALTER TABLE users
  ADD COLUMN IF NOT EXISTS email_verified      TINYINT(1)  NOT NULL DEFAULT 0         COMMENT '0 = unverified, 1 = verified',
  ADD COLUMN IF NOT EXISTS verify_token        VARCHAR(64) NULL                        COMMENT 'Hex token sent in the verification email',
  ADD COLUMN IF NOT EXISTS verify_token_expiry DATETIME    NULL                        COMMENT 'Token expires 24 h after issue',
  ADD COLUMN IF NOT EXISTS reset_token         VARCHAR(64) NULL                        COMMENT 'Hex token sent in the password-reset email',
  ADD COLUMN IF NOT EXISTS reset_token_expiry  DATETIME    NULL                        COMMENT 'Token expires 1 h after issue';

ALTER TABLE users
  ADD UNIQUE INDEX IF NOT EXISTS uq_verify_token (verify_token),
  ADD UNIQUE INDEX IF NOT EXISTS uq_reset_token  (reset_token);
