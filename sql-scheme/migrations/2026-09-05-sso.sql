-- SSO + related schema updates for existing databases (run once).
-- Fresh installs already include User SSO columns via webappdb-V6-1.sql;
-- apply the CollectionFormToken widen there too (or run this migration).

USE webappdb;

-- User: nullable modern password hashes + OIDC binding
ALTER TABLE `User`
    MODIFY `passwordHash` varchar(255) DEFAULT NULL,
    ADD COLUMN `oidc_sub` varchar(128) DEFAULT NULL AFTER `station_ID`,
    ADD COLUMN `sso_email` varchar(255) DEFAULT NULL AFTER `oidc_sub`,
    ADD UNIQUE KEY `uk_user_oidc_sub` (`oidc_sub`),
    ADD UNIQUE KEY `uk_user_sso_email` (`sso_email`);

-- Stronger QR / collection form tokens
ALTER TABLE `CollectionFormToken`
    MODIFY `token` varchar(64) NOT NULL COMMENT 'Cryptographically random token';
