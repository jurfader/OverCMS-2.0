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
        add_action('admin_enqueue_scripts', [self::class, 'dequeueWpCoreNoise'], 999);
        add_action('admin_head', [self::class, 'injectFullscreenStyles']);
        add_action('admin_head', [self::class, 'injectEmbedStyles']);
    }

    /**
     * Tryb embed: gdy ?overcms_embed=1 ukryj sidebar, topbar i footer WP
     * (strona ładowana w iframie panelu OverCMS — pokazujemy tylko treść Divi).
     */
    public static function injectEmbedStyles(): void
    {
        // Obsługujemy zarówno ?overcms_embed=1 w URL (pierwsze wejście)
        // jak i ciasteczko overcms_embed (przeżywa wewnętrzne redirecty WooCommerce itp.)
        $active = !empty($_GET['overcms_embed']) || !empty($_COOKIE['overcms_embed']);
        if (!$active) {
            return;
        }
        echo '<style>
            #wpadminbar,
            #adminmenuback, #adminmenuwrap, #adminmenu,
            #wpfooter,
            .update-nag, .updated, .notice, div.error, div.updated { display: none !important; }
            html.wp-toolbar { padding-top: 0 !important; }
            #wpcontent { margin-left: 0 !important; padding-top: 0 !important; }
            #wpbody { padding-top: 0 !important; }
            #wpbody-content { padding-bottom: 0 !important; }
        </style>';
    }

    /**
     * Wp-router, wp-core-commands, wp-edit-site itp. ładują się jako globale
     * w każdej stronie wp-admin i oczekują obecności window.React. Nasz panel
     * używa React tylko bundlowanego przez Vite (nie jako global), więc
     * te skrypty rzucają w konsoli błędy typu:
     *   Cannot read properties of undefined (reading 'createContext')
     *   Cannot read properties of undefined (reading 'jsx')
     *   Cannot read properties of undefined (reading 'initializeCommandPalette')
     * Wszystkie te skrypty są zbędne na naszej własnej stronie panelu —
     * wyrejestrowujemy je gdy jesteśmy na toplevel_page_overcms.
     */
    public static function dequeueWpCoreNoise(string $hook): void
    {
        if ($hook !== 'toplevel_page_' . self::SLUG) {
            return;
        }
        $noisyScripts = [
            'wp-router',
            'wp-core-commands',
            'wp-commands',
            'wp-edit-site',
            'wp-edit-post',
            'wp-block-editor',
            'wp-block-library',
            'wp-block-directory',
            'wp-format-library',
            'wp-nux',
            'wp-preferences',
            'wp-plugins',
            'wp-priority-queue',
            'wp-reusable-blocks',
            'wp-rich-text',
            'wp-style-engine',
            'wp-warning',
        ];
        foreach ($noisyScripts as $handle) {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
        }

        // wp-admin color schemes (colors-fresh, colors-modern, ...) wymuszają
        // ciemny kolor #1d2327 na .wrap h1, .wrap h2 itd. — wygrywa specyficznością
        // z naszymi Tailwindowymi klasami. Wycinamy je całkowicie + dodajemy
        // własny reset niżej.
        $noisyStyles = [
            'colors',
            'common',
            'forms',
            'admin-menu',
            'dashboard',
            'list-tables',
            'edit',
            'revisions',
            'media',
            'themes',
            'about',
            'nav-menus',
            'wp-pointer',
            'widgets',
            'site-icon',
            'l10n',
            'install',
            'wp-block-library',
            'wp-block-library-theme',
            'wp-block-editor',
            'wp-edit-blocks',
            'wp-edit-post',
            'wp-edit-site',
            'wp-format-library',
            'wp-nux',
            'wp-components',
        ];
        foreach ($noisyStyles as $handle) {
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
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
        // Inline script ustawia klasę motywu na <html> przed pierwszym paintem
        // żeby uniknąć flash of wrong theme. Czyta z localStorage (klucz overcms-theme)
        // z fallbackiem do opcji WP. Bez tego tło migało dark→light przy odświeżeniu
        // strony w light mode.
        $stored = get_option('overcms_panel_theme', 'dark') === 'light' ? 'light' : 'dark';
        echo '<script>(function(){try{var t=localStorage.getItem("overcms-theme");if(t!=="dark"&&t!=="light"){t=' . json_encode($stored) . ';}var h=document.documentElement;h.classList.remove("dark","light");h.classList.add(t);}catch(e){document.documentElement.classList.add(' . json_encode($stored) . ');}})();</script>';
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
            'logoutUrl'       => wp_logout_url(home_url('/')),
            'hasWooCommerce'  => is_plugin_active('woocommerce/woocommerce.php'),
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
     *
     * Tło jest theme-aware: pierwsza wartość to fallback dla bardzo wczesnego
     * paint przed załadowaniem React/CSS, druga (var) bierze aktualny motyw
     * z OverCMS design tokens.
     */
    public static function injectFullscreenStyles(): void
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'toplevel_page_' . self::SLUG) {
            return;
        }
        $theme = get_option('overcms_panel_theme', 'dark') === 'light' ? 'light' : 'dark';
        $bgFallback = $theme === 'light' ? '#ECEEFF' : '#0A0B14';
        echo <<<CSS
<style>
  html.wp-toolbar { padding-top: 0 !important; }
  #wpadminbar, #adminmenumain, #adminmenuwrap, #adminmenuback, #wpfooter { display: none !important; }
  #wpcontent, #wpbody, #wpbody-content { margin-left: 0 !important; padding: 0 !important; }
  html.{$theme} #wpwrap, html.{$theme} body.toplevel_page_overcms,
  #wpwrap, body.toplevel_page_overcms {
    background: {$bgFallback};
    background: var(--color-background, {$bgFallback});
  }
  .overcms-root { min-height: 100vh; }
  .update-nag, .notice, div.error, div.updated { display: none !important; }

  /* Reset wp-admin h1-h6 \"color: #1d2327\" rules \u2014 nasze nagl\u00f3wki dziedzicz\u0105
     kolor z var(--color-foreground) ustawiany przez React/Tailwind. */
  #overcms-root,
  #overcms-root h1, #overcms-root h2, #overcms-root h3,
  #overcms-root h4, #overcms-root h5, #overcms-root h6,
  #overcms-root p, #overcms-root span, #overcms-root label, #overcms-root a {
    color: inherit;
  }
  #overcms-root h1, #overcms-root h2, #overcms-root h3,
  #overcms-root h4, #overcms-root h5, #overcms-root h6 {
    margin: 0;
    padding: 0;
    font-weight: inherit;
    line-height: inherit;
  }
  /* WP-admin .wrap reguly typu \"a:focus { box-shadow: ... }\" \u2014 nasze fokusy
     s\u0105 obs\u0142ugiwane przez Tailwind/CSS variables. */
  #overcms-root a:focus, #overcms-root button:focus { box-shadow: none; }
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
