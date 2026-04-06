<?php

namespace OverCMS\Core\Rest;

use OverCMS\Core\LoginAttempts;

/**
 * Statystyki bezpieczeństwa: nieudane logowania, zablokowane IP, status hardening.
 *
 * Endpointy:
 *   GET    /overcms/v1/security/login-attempts        — last 100 failed logins
 *   DELETE /overcms/v1/security/login-attempts        — wyczyść log
 *   GET    /overcms/v1/security/status                — file edit, xml-rpc, FS_METHOD itd.
 */
final class SecurityController
{
    public static function register(): void
    {
        $perm = [RestRouter::class, 'canManage'];
        $ns   = RestRouter::NAMESPACE;

        register_rest_route($ns, '/security/login-attempts', [
            ['methods' => \WP_REST_Server::READABLE,  'callback' => [self::class, 'attempts'],     'permission_callback' => $perm],
            ['methods' => \WP_REST_Server::DELETABLE, 'callback' => [self::class, 'clearAttempts'],'permission_callback' => $perm],
        ]);

        register_rest_route($ns, '/security/status', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'status'],
            'permission_callback' => $perm,
        ]);
    }

    public static function attempts(): \WP_REST_Response
    {
        $attempts = LoginAttempts::all();
        return new \WP_REST_Response([
            'items' => array_map(static fn ($a) => [
                'username'  => $a['username']  ?? '',
                'ip'        => $a['ip']        ?? '',
                'userAgent' => $a['userAgent'] ?? '',
                'timestamp' => $a['timestamp'] ?? null,
            ], $attempts),
            'total' => count($attempts),
        ]);
    }

    public static function clearAttempts(): \WP_REST_Response
    {
        LoginAttempts::clear();
        return new \WP_REST_Response(['success' => true]);
    }

    public static function status(): \WP_REST_Response
    {
        $checks = [
            [
                'id'      => 'file_edit',
                'label'   => 'Edycja plików w wp-admin wyłączona',
                'ok'      => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
                'level'   => 'critical',
            ],
            [
                'id'      => 'xmlrpc',
                'label'   => 'XML-RPC wyłączone',
                'ok'      => defined('XMLRPC_ENABLED') && !XMLRPC_ENABLED,
                'level'   => 'critical',
            ],
            [
                'id'      => 'fs_method',
                'label'   => 'FS_METHOD ustawione na direct',
                'ok'      => defined('FS_METHOD') && FS_METHOD === 'direct',
                'level'   => 'warning',
            ],
            [
                'id'      => 'auto_updates',
                'label'   => 'Auto-aktualizacje WP wyłączone (kontrolujemy ręcznie)',
                'ok'      => defined('AUTOMATIC_UPDATER_DISABLED') && AUTOMATIC_UPDATER_DISABLED,
                'level'   => 'info',
            ],
            [
                'id'      => 'env_perms',
                'label'   => '.env nie jest dostępny przez HTTP',
                'ok'      => self::envProtected(),
                'level'   => 'critical',
            ],
            [
                'id'      => 'debug',
                'label'   => 'WP_DEBUG_DISPLAY wyłączone w produkcji',
                'ok'      => defined('WP_DEBUG_DISPLAY') && !WP_DEBUG_DISPLAY,
                'level'   => 'warning',
            ],
        ];

        $score = 0;
        $max   = 0;
        foreach ($checks as $c) {
            $weight = $c['level'] === 'critical' ? 3 : ($c['level'] === 'warning' ? 2 : 1);
            $max += $weight;
            if ($c['ok']) $score += $weight;
        }

        return new \WP_REST_Response([
            'score'    => $score,
            'maxScore' => $max,
            'percent'  => $max > 0 ? round(($score / $max) * 100) : 0,
            'checks'   => $checks,
        ]);
    }

    private static function envProtected(): bool
    {
        // Heurystyka: spróbuj GET /.env z lokalnego hosta
        $url = home_url('/.env');
        $res = wp_remote_get($url, ['timeout' => 3, 'sslverify' => false]);
        if (is_wp_error($res)) return true;
        $code = wp_remote_retrieve_response_code($res);
        return $code === 403 || $code === 404;
    }
}
