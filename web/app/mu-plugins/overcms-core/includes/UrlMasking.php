<?php

namespace OverCMS\Core;

/**
 * Maskuje fakt że pod spodem jest WordPress + Bedrock.
 *
 * Zamiast wystawiać:
 *   https://site/wp/wp-admin/admin.php?page=overcms
 *   https://site/wp/wp-login.php
 *
 * Wystawiamy:
 *   https://site/admin/admin.php?page=overcms
 *   https://site/login
 *
 * Działa to przez kombinację dwóch warstw:
 *
 *   1. **Nginx rewrite** (vhost OVERPANEL): faktyczne żądanie do
 *      `/admin/foo.php` jest przepisywane na `/wp/wp-admin/foo.php`,
 *      a `/login` na `/wp/wp-login.php`. Bez tego URL-e zwracałyby 404.
 *
 *   2. **PHP filters** (ten plik): wszystkie WordPress'owe `admin_url()`,
 *      `site_url('wp-login.php')`, `wp_login_url()`, `home_url()` etc.
 *      są przepisywane tak żeby zwracały zmaskowane wersje. Dzięki temu
 *      wszystkie linki w HTML, mailach, redirectach używają nowych ścieżek.
 *
 * Bonus: poprawia również cookie path bug — bo COOKIEPATH=/ jest spójny
 * z faktem że adminem jest /admin/ a nie /wp/wp-admin/.
 */
final class UrlMasking
{
    public const ADMIN_SLUG = 'admin';
    public const LOGIN_SLUG = 'login';

    public static function register(): void
    {
        // 1. URL filters — wszystkie funkcje generujące admin URL-e
        add_filter('admin_url',    [self::class, 'maskAdminUrl'],   10, 4);
        add_filter('site_url',     [self::class, 'maskSiteUrl'],    10, 4);
        add_filter('login_url',    [self::class, 'maskLoginUrl'],   10, 3);
        add_filter('logout_url',   [self::class, 'maskLogoutUrl'],  10, 2);
        add_filter('lostpassword_url', [self::class, 'maskLoginUrl'], 10, 2);
        add_filter('register_url', [self::class, 'maskLoginUrl'],   10);

        // 2. Network site URL (multisite-safe)
        add_filter('network_site_url',  [self::class, 'maskSiteUrl'],  10, 3);
        add_filter('network_admin_url', [self::class, 'maskAdminUrl'], 10, 3);

        // 3. Po stronie REST API ścieżki też powinny używać czystych URL-i
        add_filter('rest_url', [self::class, 'maskRestUrl'], 10, 4);

        // 4. Filter HTML output — wp_loginout, wp_login_form etc. używają site_url('wp-login.php')
        // już naszym filtrem, więc nie ma potrzeby double-filterowania.

        // 5. Set HTTP header X-Robots-Tag żeby Google nie indeksował fałszywych ścieżek
        add_action('admin_init', static function (): void {
            if (!headers_sent()) {
                header('X-Robots-Tag: noindex, nofollow');
            }
        });
    }

    /**
     * Zamienia /wp/wp-admin/ na /admin/.
     *
     * Sygnatura: admin_url($url, $path, $blog_id, $scheme)
     */
    public static function maskAdminUrl(string $url, string $path = '', $blog_id = null, ?string $scheme = null): string
    {
        return str_replace('/wp/wp-admin/', '/' . self::ADMIN_SLUG . '/', $url);
    }

    /**
     * Zamienia /wp/wp-login.php na /login.
     *
     * Sygnatura: site_url($url, $path, $scheme, $blog_id)
     */
    public static function maskSiteUrl(string $url, string $path = '', ?string $scheme = null, $blog_id = null): string
    {
        // wp-login.php → /login (z lub bez query string)
        $url = preg_replace('#/wp/wp-login\.php#', '/' . self::LOGIN_SLUG, $url);
        // wp-admin/* → /admin/*
        $url = str_replace('/wp/wp-admin/', '/' . self::ADMIN_SLUG . '/', $url);
        return $url;
    }

    /**
     * wp_login_url() — sygnatura: ($login_url, $redirect, $force_reauth)
     */
    public static function maskLoginUrl(string $login_url, string $redirect = '', bool $force_reauth = false): string
    {
        return self::maskSiteUrl($login_url);
    }

    public static function maskLogoutUrl(string $logout_url, string $redirect = ''): string
    {
        return self::maskSiteUrl($logout_url);
    }

    /**
     * REST URL: /wp/wp-json/ → /api/  (opcjonalnie — póki co zostawiamy /wp-json/)
     *
     * Tutaj NIE maskujemy bo nasz panel React używa boot.restRoot który czyta
     * to z OVERCMS_BOOT i jest spójny z oryginalnym URL. Maskowanie REST
     * wymagałoby też nginx rewrite + zmiany w PanelLoader.php — zostawiamy
     * jako TODO.
     */
    public static function maskRestUrl(string $url, string $path = '', $blog_id = null, ?string $scheme = null): string
    {
        return $url;
    }
}
