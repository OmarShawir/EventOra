-- EventOra: Add email verification & password reset token columns
-- Run against the existing `eventora` database:

ALTER TABLE users
  ADD COLUMN email_verified      TINYINT(1)  NOT NULL DEFAULT 0         COMMENT '0 = unverified, 1 = verified',
  ADD COLUMN verify_token        VARCHAR(64) NULL                        COMMENT 'Hex token sent in the verification email',
  ADD COLUMN verify_token_expiry DATETIME    NULL                        COMMENT 'Token expires 24 h after issue',
  ADD COLUMN reset_token         VARCHAR(64) NULL                        COMMENT 'Hex token sent in the password-reset email',
  ADD COLUMN reset_token_expiry  DATETIME    NULL                        COMMENT 'Token expires 1 h after issue';

ALTER TABLE users
  ADD UNIQUE INDEX uq_verify_token (verify_token),
  ADD UNIQUE INDEX uq_reset_token  (reset_token);
