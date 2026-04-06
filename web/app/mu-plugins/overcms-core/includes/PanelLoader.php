<?php

namespace OverCMS\Core;

/**
 * Rejestruje stronę admina /wp-admin/admin.php?page=overcms i ładuje
 * skompilowany React panel z panel/dist (Vite manifest aware).
 */
final class PanelLoader
{
    public const SLUG = 'overcms';
    public const CAPABILITY = 'edit_posts';

    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'addMenuPage']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueAssets']);
        add_action('admin_head', [self::class, 'injectFullscreenStyles']);
    }

    public static function addMenuPage(): void
    {
        $iconSvg = self::menuIconSvg();

        add_menu_page(
            'OverCMS',
            'OverCMS',
            self::CAPABILITY,
            self::SLUG,
            [self::class, 'render'],
            'data:image/svg+xml;base64,' . base64_encode($iconSvg),
            2
        );
    }

    public static function render(): void
    {
        echo '<div id="overcms-root" class="overcms-root"></div>';
    }

    public static function enqueueAssets(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }

        $manifest = self::loadManifest();
        if (!$manifest) {
            // Dev fallback message
            wp_register_script('overcms-missing', '', [], OVERCMS_VERSION, true);
            wp_enqueue_script('overcms-missing');
            wp_add_inline_script(
                'overcms-missing',
                "document.getElementById('overcms-root')?.insertAdjacentHTML('beforeend', '<div style=\"padding:40px;font-family:system-ui;color:#fff\">Panel OverCMS nie jest jeszcze zbudowany. Uruchom: <code>cd overcms-panel && npm install && npm run build</code></div>');"
            );
            return;
        }

        $entry = $manifest['src/main.tsx'] ?? null;
        if (!$entry) {
            return;
        }

        // CSS
        foreach (($entry['css'] ?? []) as $cssFile) {
            wp_enqueue_style(
                'overcms-' . md5($cssFile),
                OVERCMS_PANEL_DIST_URL . '/' . $cssFile,
                [],
                OVERCMS_VERSION
            );
        }

        // JS
        wp_enqueue_script(
            'overcms-panel',
            OVERCMS_PANEL_DIST_URL . '/' . $entry['file'],
            [],
            OVERCMS_VERSION,
            true
        );

        // Bootstrap data dla React
        $current_user = wp_get_current_user();
        wp_localize_script('overcms-panel', 'OVERCMS_BOOT', [
            'version'    => OVERCMS_VERSION,
            'restRoot'   => esc_url_raw(rest_url()),
            'restNonce'  => wp_create_nonce('wp_rest'),
            'adminUrl'   => admin_url(),
            'siteUrl'    => home_url('/'),
            'siteTitle'  => get_bloginfo('name'),
            'currentUser' => [
                'id'          => $current_user->ID,
                'name'        => $current_user->display_name,
                'email'       => $current_user->user_email,
                'roles'       => $current_user->roles,
                'avatarUrl'   => get_avatar_url($current_user->ID, ['size' => 96]),
            ],
            'capabilities' => [
                'manageOptions' => current_user_can('manage_options'),
                'editPages'     => current_user_can('edit_pages'),
                'editPosts'     => current_user_can('edit_posts'),
                'uploadFiles'   => current_user_can('upload_files'),
                'listUsers'     => current_user_can('list_users'),
            ],
            'logoutUrl'  => wp_logout_url(home_url('/')),
        ]);

        // ESM type dla głównego skryptu
        add_filter('script_loader_tag', static function (string $tag, string $handle): string {
            if ($handle === 'overcms-panel') {
                return str_replace('<script ', '<script type="module" ', $tag);
            }
            return $tag;
        }, 10, 2);
    }

    /**
     * Ukrywa standardowy nagłówek wp-admin gdy jesteśmy na stronie panelu
     * (panel renderuje własny topbar).
     */
    public static function injectFullscreenStyles(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'toplevel_page_' . self::SLUG) {
            return;
        }
        echo <<<'CSS'
<style>
  html.wp-toolbar { padding-top: 0 !important; }
  #wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenuback, #wpfooter { display: none !important; }
  #wpcontent, #wpbody, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
  #wpwrap { background: #0A0B14; }
  .overcms-root { min-height: 100vh; }
  .update-nag, .notice, div.error, div.updated { display: none !important; }
  body.toplevel_page_overcms { background: #0A0B14; }
</style>
CSS;
    }

    private static function loadManifest(): ?array
    {
        $path = OVERCMS_PANEL_DIST . '/.vite/manifest.json';
        if (!file_exists($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);
        return is_array($json) ? $json : null;
    }

    private static function menuIconSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 12l3 3 5-6"/></svg>';
    }
}
