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

        // Normalizacja na potrzeby UI.
        // plugins_api() zwraca pluginy jako stdClass LUB array w zależności od
        // konfiguracji — WP core też robi `(array) $plugin` przed iteracją w
        // wp-admin/includes/class-wp-plugin-install-list-table.php. Defensywnie
        // konwertujemy każdy element żeby działało w obu przypadkach.
        $installed = self::installedSlugs();
        $items = array_map(static function ($p) use ($installed) {
            $p     = is_object($p) ? get_object_vars($p) : (array) $p;
            $icons = isset($p['icons']) ? (array) $p['icons'] : [];
            $slug  = (string) ($p['slug'] ?? '');

            return [
                'slug'             => $slug,
                'name'             => wp_strip_all_tags((string) ($p['name'] ?? '')),
                'shortDescription' => wp_strip_all_tags((string) ($p['short_description'] ?? '')),
                'author'           => wp_strip_all_tags((string) ($p['author'] ?? '')),
                'version'          => $p['version'] ?? null,
                'rating'           => isset($p['rating']) ? (float) $p['rating'] : null,
                'numRatings'       => (int) ($p['num_ratings'] ?? 0),
                'activeInstalls'   => (int) ($p['active_installs'] ?? 0),
                'icon'             => $icons['1x'] ?? $icons['default'] ?? $icons['svg'] ?? null,
                'iconHigh'         => $icons['2x'] ?? null,
                'lastUpdated'      => $p['last_updated'] ?? null,
                'requiresPhp'      => $p['requires_php'] ?? null,
                'requiresWp'       => $p['requires'] ?? null,
                'testedWp'         => $p['tested'] ?? null,
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

        try {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
            require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
            require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader-skin.php';
            require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

            // Sprawdź czy nie jest już zainstalowany
            $installed = self::installedSlugs();
            if (isset($installed[$slug])) {
                $file = self::pluginFileForSlug($slug);
                if ($activate && $file && !is_plugin_active($file)) {
                    $result = activate_plugin($file);
                    if (is_wp_error($result)) {
                        return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
                    }
                }
                return new \WP_REST_Response(['success' => true, 'alreadyInstalled' => true]);
            }

            // Pobierz informacje o pluginie z wp.org
            $api = plugins_api('plugin_information', [
                'slug'   => $slug,
                'fields' => ['sections' => false],
            ]);
            if (is_wp_error($api)) {
                return new \WP_REST_Response([
                    'error' => 'plugins_api failed: ' . $api->get_error_message(),
                ], 502);
            }
            if (empty($api->download_link)) {
                return new \WP_REST_Response(['error' => 'No download link returned by wp.org'], 502);
            }

            // Wymuś metodę direct (FS_METHOD jest też zdefiniowane w overcms-core.php,
            // ale dodajemy filtr na wypadek gdyby zostało nadpisane).
            add_filter('filesystem_method', static fn () => 'direct');

            if (!WP_Filesystem()) {
                return new \WP_REST_Response([
                    'error' => 'Cannot initialize WP_Filesystem (check that wp-content is writable by ' . get_current_user() . ')',
                ], 500);
            }

            // Automatic skin tłumi prompty kredencjali; ajax skin też działa.
            $skin     = new \Automatic_Upgrader_Skin();
            $upgrader = new \Plugin_Upgrader($skin);
            $result   = $upgrader->install($api->download_link);

            if (is_wp_error($result)) {
                return new \WP_REST_Response(['error' => 'install: ' . $result->get_error_message()], 500);
            }
            if ($skin->get_errors()->has_errors()) {
                $messages = $skin->get_error_messages();
                return new \WP_REST_Response([
                    'error' => 'skin: ' . ($messages[0] ?? 'unknown skin error'),
                    'all'   => $messages,
                ], 500);
            }
            if ($result === false) {
                return new \WP_REST_Response([
                    'error' => 'Plugin_Upgrader::install returned false (likely permission issue on wp-content/plugins)',
                ], 500);
            }

            // Aktywuj jeśli żądano
            if ($activate) {
                $file = $upgrader->plugin_info();
                if (!$file) {
                    // Spróbuj znaleźć po slug
                    $file = self::pluginFileForSlug($slug);
                }
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
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'error' => 'exception: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
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
