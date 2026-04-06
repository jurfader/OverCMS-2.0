<?php

namespace OverCMS\Core\Rest;

/**
 * Most do oficjalnego repo wordpress.org/plugins.
 * Pozwala panelowi React wyświetlać marketplace bez przekierowania
 * do wp-admin/plugin-install.php.
 *
 * Endpointy:
 *   GET  /overcms/v1/marketplace?browse=popular|featured|new&page=1
 *   GET  /overcms/v1/marketplace/search?q=...&page=1
 *   POST /overcms/v1/marketplace/install   { slug }
 */
final class MarketplaceController
{
    public static function register(): void
    {
        register_rest_route(RestRouter::NAMESPACE, '/marketplace', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'browse'],
            'permission_callback' => [RestRouter::class, 'canManage'],
            'args'                => [
                'browse'   => ['type' => 'string', 'default' => 'popular'],
                'page'     => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 24],
            ],
        ]);

        register_rest_route(RestRouter::NAMESPACE, '/marketplace/search', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'search'],
            'permission_callback' => [RestRouter::class, 'canManage'],
            'args'                => [
                'q'        => ['type' => 'string', 'required' => true],
                'page'     => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 24],
            ],
        ]);

        register_rest_route(RestRouter::NAMESPACE, '/marketplace/install', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'install'],
            'permission_callback' => [RestRouter::class, 'canManage'],
            'args'                => [
                'slug'     => ['type' => 'string', 'required' => true],
                'activate' => ['type' => 'boolean', 'default' => true],
            ],
        ]);
    }

    public static function browse(\WP_REST_Request $req): \WP_REST_Response
    {
        $browse = in_array($req['browse'], ['popular', 'featured', 'new', 'updated'], true)
            ? $req['browse'] : 'popular';

        return self::queryPlugins([
            'browse'   => $browse,
            'page'     => max(1, (int) $req['page']),
            'per_page' => max(1, min(48, (int) $req['per_page'])),
        ]);
    }

    public static function search(\WP_REST_Request $req): \WP_REST_Response
    {
        $q = trim((string) $req['q']);
        if ($q === '') {
            return new \WP_REST_Response(['error' => 'Query is required'], 400);
        }
        return self::queryPlugins([
            'search'   => $q,
            'page'     => max(1, (int) $req['page']),
            'per_page' => max(1, min(48, (int) $req['per_page'])),
        ]);
    }

    /**
     * Wywołuje plugins_api() — natywny WP wrapper na wordpress.org API
     * (cached przez WP transients).
     */
    private static function queryPlugins(array $args): \WP_REST_Response
    {
        if (!function_exists('plugins_api')) {
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        }

        $api = plugins_api('query_plugins', array_merge([
            'fields' => [
                'icons'              => true,
                'short_description'  => true,
                'rating'             => true,
                'num_ratings'        => true,
                'downloaded'         => true,
                'active_installs'    => true,
                'last_updated'       => true,
                'requires'           => true,
                'tested'             => true,
                'requires_php'       => true,
                'sections'           => false,
                'description'        => false,
                'screenshots'        => false,
                'versions'           => false,
                'compatibility'      => false,
                'donate_link'        => false,
                'reviews'            => false,
                'banners'            => false,
            ],
        ], $args));

        if (is_wp_error($api)) {
            return new \WP_REST_Response(['error' => $api->get_error_message()], 502);
        }

        // Normalizacja na potrzeby UI
        $installed = self::installedSlugs();
        $items = array_map(static function ($p) use ($installed) {
            $slug = $p->slug ?? '';
            return [
                'slug'             => $slug,
                'name'             => wp_strip_all_tags((string) ($p->name ?? '')),
                'shortDescription' => wp_strip_all_tags((string) ($p->short_description ?? '')),
                'author'           => wp_strip_all_tags((string) ($p->author ?? '')),
                'version'          => $p->version ?? null,
                'rating'           => isset($p->rating) ? (float) $p->rating : null,
                'numRatings'       => (int) ($p->num_ratings ?? 0),
                'activeInstalls'   => (int) ($p->active_installs ?? 0),
                'icon'             => $p->icons['1x'] ?? $p->icons['default'] ?? null,
                'iconHigh'         => $p->icons['2x'] ?? null,
                'lastUpdated'      => $p->last_updated ?? null,
                'requiresPhp'      => $p->requires_php ?? null,
                'requiresWp'       => $p->requires ?? null,
                'testedWp'         => $p->tested ?? null,
                'installed'        => isset($installed[$slug]),
                'active'           => $installed[$slug] ?? false,
            ];
        }, (array) ($api->plugins ?? []));

        return new \WP_REST_Response([
            'items' => $items,
            'info'  => [
                'page'    => (int) ($api->info['page'] ?? 1),
                'pages'   => (int) ($api->info['pages'] ?? 1),
                'results' => (int) ($api->info['results'] ?? count($items)),
            ],
        ]);
    }

    public static function install(\WP_REST_Request $req): \WP_REST_Response
    {
        $slug     = sanitize_key((string) $req['slug']);
        $activate = (bool) $req['activate'];

        if ($slug === '') {
            return new \WP_REST_Response(['error' => 'Invalid slug'], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php';

        // Sprawdź czy nie jest już zainstalowany
        $installed = self::installedSlugs();
        if (isset($installed[$slug])) {
            // Już zainstalowany — może tylko aktywować
            $file = self::pluginFileForSlug($slug);
            if ($activate && $file && !is_plugin_active($file)) {
                $result = activate_plugin($file);
                if (is_wp_error($result)) {
                    return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
                }
            }
            return new \WP_REST_Response(['success' => true, 'alreadyInstalled' => true]);
        }

        // Pobierz informacje o pluginie
        $api = plugins_api('plugin_information', [
            'slug'   => $slug,
            'fields' => ['sections' => false],
        ]);
        if (is_wp_error($api)) {
            return new \WP_REST_Response(['error' => $api->get_error_message()], 502);
        }

        // Inicjalizacja WP_Filesystem
        if (!WP_Filesystem()) {
            return new \WP_REST_Response(['error' => 'Cannot initialize WP_Filesystem'], 500);
        }

        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result   = $upgrader->install($api->download_link);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
        }
        if ($skin->get_errors()->has_errors()) {
            return new \WP_REST_Response(['error' => $skin->get_error_messages()[0] ?? 'Install failed'], 500);
        }
        if (!$result) {
            return new \WP_REST_Response(['error' => 'Plugin_Upgrader::install returned false'], 500);
        }

        // Aktywuj jeśli żądano
        if ($activate) {
            $file = $upgrader->plugin_info();
            if ($file) {
                $activated = activate_plugin($file);
                if (is_wp_error($activated)) {
                    return new \WP_REST_Response([
                        'success'   => true,
                        'installed' => true,
                        'activated' => false,
                        'warning'   => $activated->get_error_message(),
                    ]);
                }
            }
        }

        return new \WP_REST_Response([
            'success'   => true,
            'installed' => true,
            'activated' => $activate,
        ]);
    }

    /**
     * @return array<string,bool> [slug => active?]
     */
    private static function installedSlugs(): array
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $all    = get_plugins();
        $active = (array) get_option('active_plugins', []);
        $out    = [];
        foreach ($all as $file => $_) {
            $slug = strtok($file, '/');
            if ($slug) {
                $out[$slug] = in_array($file, $active, true);
            }
        }
        return $out;
    }

    private static function pluginFileForSlug(string $slug): ?string
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        foreach (array_keys(get_plugins()) as $file) {
            if (strtok($file, '/') === $slug) {
                return $file;
            }
        }
        return null;
    }
}
