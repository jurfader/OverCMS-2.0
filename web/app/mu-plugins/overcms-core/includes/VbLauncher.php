<?php

namespace OverCMS\Core;

/**
 * Server-side launcher dla Divi Visual Builder.
 *
 * Cel: otwarcie VB z panelu OverCMS bez pokazywania WP admin UI uzytkownikowi.
 *
 * Flow:
 *   1. Panel React otwiera w nowej karcie URL: /?overcms_launch_vb=1&post=<ID>
 *   2. Ten handler weryfikuje sesje + uprawnienia, generuje permalink strony
 *   3. Server-side redirect (302) do /?p=<ID>&et_fb=1 — Divi widzi et_fb=1
 *      + zalogowanego usera z capability i odpala VB na frontendzie
 *   4. Browser ladowal jedna strone (sam launcher) → instant redirect → VB
 *
 * User nie widzi WP admin chrome bo przeskakujemy bezposrednio do frontendu z VB.
 */
final class VbLauncher
{
    public const QUERY_VAR = 'overcms_launch_vb';

    public static function register(): void
    {
        // Hook na 'init' — przed routingiem WP, mozemy zrobic redirect od razu
        add_action('init', [self::class, 'handleLaunch'], 1);
    }

    public static function handleLaunch(): void
    {
        if (empty($_GET[self::QUERY_VAR])) {
            return;
        }

        // Wymagaj zalogowanego usera
        if (!is_user_logged_in()) {
            $loginUrl = wp_login_url(self::buildSelfUrl());
            wp_safe_redirect($loginUrl);
            exit;
        }

        $postId = isset($_GET['post']) ? (int) $_GET['post'] : 0;
        $layoutType = isset($_GET['tb_layout']) ? (string) $_GET['tb_layout'] : '';

        // Theme Builder layout (header/body/footer)
        if ($layoutType !== '' && $postId > 0) {
            self::launchThemeBuilderLayout($postId, $layoutType);
            return;
        }

        // Zwykla strona / post
        if ($postId <= 0) {
            wp_die('Brak ID strony do edycji', 'OverCMS', ['response' => 400]);
        }

        $post = get_post($postId);
        if (!$post) {
            wp_die('Strona nie istnieje', 'OverCMS', ['response' => 404]);
        }

        if (!current_user_can('edit_post', $postId)) {
            wp_die('Brak uprawnien do edycji', 'OverCMS', ['response' => 403]);
        }

        // Wymus _et_pb_use_builder=on jesli nie ustawione (Divi inaczej nie odpali VB)
        $useBuilder = get_post_meta($postId, '_et_pb_use_builder', true);
        if ($useBuilder !== 'on') {
            update_post_meta($postId, '_et_pb_use_builder', 'on');
        }

        $vbUrl = add_query_arg([
            'et_fb'     => 1,
            'PageSpeed' => 'off',
        ], get_permalink($postId));

        wp_safe_redirect($vbUrl);
        exit;
    }

    /**
     * Theme Builder layout (CPT et_header_layout, et_body_layout, et_footer_layout).
     * Te layouty maja public=false, ale nasz mu-plugin overcms-divi-tb-fix.php juz to override'uje.
     */
    private static function launchThemeBuilderLayout(int $postId, string $layoutType): void
    {
        $allowed = ['header', 'body', 'footer'];
        if (!in_array($layoutType, $allowed, true)) {
            wp_die('Nieznany typ layoutu Theme Builder', 'OverCMS', ['response' => 400]);
        }

        if (!current_user_can('edit_post', $postId)) {
            wp_die('Brak uprawnien do edycji layoutu', 'OverCMS', ['response' => 403]);
        }

        $post = get_post($postId);
        if (!$post) {
            wp_die('Layout nie istnieje', 'OverCMS', ['response' => 404]);
        }

        $vbUrl = add_query_arg([
            'et_fb'      => 1,
            'PageSpeed'  => 'off',
            'et_tb'      => 1,
            'app_window' => 1,
        ], get_permalink($postId));

        wp_safe_redirect($vbUrl);
        exit;
    }

    private static function buildSelfUrl(): string
    {
        $url = home_url(add_query_arg([], $_SERVER['REQUEST_URI'] ?? '/'));
        return esc_url_raw($url);
    }

    /**
     * Helper: zbuduj URL launchera dla danej strony (uzywany w PHP, np. menu links).
     */
    public static function buildLaunchUrl(int $postId): string
    {
        return add_query_arg([
            self::QUERY_VAR => 1,
            'post'          => $postId,
        ], home_url('/'));
    }

    public static function buildThemeBuilderLaunchUrl(int $postId, string $layoutType): string
    {
        return add_query_arg([
            self::QUERY_VAR => 1,
            'post'          => $postId,
            'tb_layout'     => $layoutType,
        ], home_url('/'));
    }
}
