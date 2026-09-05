<?php

/**
 * Local password hashing with transparent migration from legacy HMAC-MD5.
 *
 * New hashes: password_hash(PASSWORD_DEFAULT) (bcrypt / Argon2 depending on PHP).
 * Legacy hashes: HMAC-MD5 with the historical fixed salt (still verified).
 * On successful legacy login, callers should re-hash via hash() and persist.
 */
class PasswordHash
{
    private const LEGACY_SALT = 'Zehn zahme Ziegen zogen zehn Zentner Zucker zum Zoo';
    private const LEGACY_ALGO = 'md5';

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify(string $password, string $passwordHash): bool
    {
        if (self::isModernHash($passwordHash)) {
            return password_verify($password, $passwordHash);
        }

        return hash_equals($passwordHash, self::legacyHash($password));
    }

    /**
     * True if stored hash should be upgraded after a successful login.
     */
    public static function needsRehash(string $passwordHash): bool
    {
        if (!self::isModernHash($passwordHash)) {
            return true;
        }

        return password_needs_rehash($passwordHash, PASSWORD_DEFAULT);
    }

    private static function isModernHash(string $passwordHash): bool
    {
        // bcrypt $2y$ / $2a$ / $2b$, argon2i / argon2id
        return str_starts_with($passwordHash, '$2y$')
            || str_starts_with($passwordHash, '$2a$')
            || str_starts_with($passwordHash, '$2b$')
            || str_starts_with($passwordHash, '$argon2');
    }

    /** @deprecated Only for verifying existing DB rows — do not create new hashes this way. */
    private static function legacyHash(string $password): string
    {
        return hash_hmac(self::LEGACY_ALGO, $password, self::LEGACY_SALT);
    }
}
