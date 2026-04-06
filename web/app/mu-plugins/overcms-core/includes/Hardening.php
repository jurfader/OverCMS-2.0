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

        // Usuń ?ver= z assetów (lepszy cache)
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

    public static function stripVersion(string $src): string
    {
        if (str_contains($src, 'ver=')) {
            $src = remove_query_arg('ver', $src);
        }
        return $src;
    }
}
