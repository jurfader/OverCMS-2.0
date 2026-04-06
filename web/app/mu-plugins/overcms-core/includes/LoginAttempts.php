<?php

namespace OverCMS\Core;

/**
 * Loguje nieudane próby logowania do wp_options (max 100 ostatnich).
 *
 * Hook: wp_login_failed (akcja standardowa WP).
 * Storage: option 'overcms_login_attempts' jako serializowany array.
 */
final class LoginAttempts
{
    private const OPTION = 'overcms_login_attempts';
    private const MAX    = 100;

    public static function register(): void
    {
        add_action('wp_login_failed', [self::class, 'record']);
    }

    public static function record(string $username): void
    {
        $attempts = self::all();
        array_unshift($attempts, [
            'username'  => substr($username, 0, 64),
            'ip'        => self::clientIp(),
            'userAgent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200),
            'timestamp' => current_time('mysql', true),
        ]);
        if (count($attempts) > self::MAX) {
            $attempts = array_slice($attempts, 0, self::MAX);
        }
        update_option(self::OPTION, $attempts, false);
    }

    public static function all(): array
    {
        $stored = get_option(self::OPTION, []);
        return is_array($stored) ? $stored : [];
    }

    public static function clear(): void
    {
        update_option(self::OPTION, [], false);
    }

    private static function clientIp(): string
    {
        // Cloudflare / proxy aware
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', (string) $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
