<?php

namespace OverCMS\Core;

/**
 * Bezpieczeństwo i wydajność: wyłącza XML-RPC, emoji, oEmbed, REST users dla
 * niezalogowanych, throttling Heartbeat, czyści zbędne tagi z <head>.
 */
final class Hardening
{
    public static function register(): void
    {
        // XML-RPC off
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('wp_xmlrpc_server_class', static fn () => 'stdClass');

        // Usuń wp_generator i inne zbędne meta z <head>
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'feed_links_extra', 3);

        // Usuń emoji
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        // Usuń oEmbed discovery + REST endpointy
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');

        // Throttle Heartbeat (60s zamiast 15s)
        add_filter('heartbeat_settings', static function (array $settings): array {
            $settings['interval'] = 60;
            return $settings;
        });

        // Wyłącz REST endpointy users dla niezalogowanych
        add_filter('rest_endpoints', static function (array $endpoints): array {
            if (is_user_logged_in()) {
                return $endpoints;
            }
            unset($endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)']);
            return $endpoints;
        });

        // Pingbacks/trackbacks off
        add_filter('xmlrpc_methods', static function (array $methods): array {
            unset($methods['pingback.ping'], $methods['pingback.extensions.getPingbacks']);
            return $methods;
        });

        // Zamień ?ver=<wersja> na nieujawniający skrót (patrz stripVersion)
        add_filter('style_loader_src', [self::class, 'stripVersion'], 10, 1);
        add_filter('script_loader_src', [self::class, 'stripVersion'], 10, 1);

        // Usuń jquery-migrate w produkcji
        add_action('wp_default_scripts', static function ($scripts): void {
            if (is_admin() || empty($scripts->registered['jquery'])) {
                return;
            }
            $jquery = $scripts->registered['jquery'];
            if (is_array($jquery->deps)) {
                $jquery->deps = array_diff($jquery->deps, ['jquery-migrate']);
            }
        });
    }

    /**
     * Ukrywa numer wersji w URL-ach assetów, ale ZACHOWUJE możliwość unieważnienia cache.
     *
     * Wcześniej ta metoda po prostu kasowała `?ver=`. Zamysł był słuszny (nie zdradzać
     * wersji WP i wtyczek), ale skutek uboczny był poważny: nginx podaje statyki
     * z `expires 30d`, więc bez zmiennego fragmentu w URL przeglądarki i Cloudflare
     * trzymały STARY plik nawet miesiąc po wdrożeniu. Każda poprawka w CSS/JS była
     * dla odwiedzających niewidoczna.
     *
     * Teraz zamiast usuwać wersję podmieniamy ją na 10-znakowy skrót z czasu
     * modyfikacji pliku: nadal nie zdradza numeru wersji, a zmienia się przy każdym
     * realnym wdrożeniu. Dla plików spoza tej instalacji (CDN) zostaje samo usunięcie.
     */
    public static function stripVersion(string $src): string
    {
        if (!str_contains($src, 'ver=')) {
            return $src;
        }

        $clean = remove_query_arg('ver', $src);
        $path  = self::localAssetPath($src);
        if ($path === null) {
            return $clean;
        }

        $mtime = @filemtime($path);
        if (!$mtime) {
            return $clean;
        }

        return add_query_arg('v', substr(md5($path . '|' . $mtime), 0, 10), $clean);
    }

    /** Mapuje URL assetu na ścieżkę w systemie plików. null = plik spoza instalacji. */
    private static function localAssetPath(string $src): ?string
    {
        $srcPath = strtok($src, '?');
        if ($srcPath === false) {
            return null;
        }

        // Kolejność ma znaczenie — site_url() jest najszerszym prefiksem, więc na końcu.
        $roots = [
            [content_url(),   WP_CONTENT_DIR],
            [includes_url(),  ABSPATH . WPINC],
            [site_url('/'),   ABSPATH],
        ];

        foreach ($roots as [$url, $dir]) {
            if (!$url || !str_starts_with($srcPath, $url)) {
                continue;
            }
            $rel  = ltrim(substr($srcPath, strlen($url)), '/');
            $file = rtrim((string) $dir, '/') . '/' . $rel;
            return is_file($file) ? $file : null;
        }

        return null;
    }
}
