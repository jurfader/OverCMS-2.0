<?php

namespace OverCMS\Core\Rest;

/**
 * Most do wp_get_plugins() — lista, (de)aktywacja, aktualizacje, ustawienia.
 *
 * Endpointy:
 *   GET    /overcms/v1/modules                    — lista pluginów
 *   POST   /overcms/v1/modules/:id/activate       — aktywuj plugin
 *   POST   /overcms/v1/modules/:id/deactivate     — dezaktywuj plugin
 *   POST   /overcms/v1/modules/:id/update         — zaktualizuj plugin
 *   POST   /overcms/v1/modules/check-updates      — wymuś sprawdzenie aktualizacji WP
 */
final class ModulesController
{
    public static function register(): void
    {
        $perm = [RestRouter::class, 'canManage'];
        $ns   = RestRouter::NAMESPACE;

        register_rest_route($ns, '/modules', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'index'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/modules/check-updates', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'checkUpdates'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/modules/(?P<id>[^/]+)/activate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'activate'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/modules/(?P<id>[^/]+)/deactivate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'deactivate'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/modules/(?P<id>[^/]+)/update', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'update'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/modules/(?P<id>[^/]+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [self::class, 'delete'],
            'permission_callback' => $perm,
        ]);
    }

    public static function index(): \WP_REST_Response
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all     = get_plugins();
        $active  = (array) get_option('active_plugins', []);
        $updates = get_site_transient('update_plugins');
        $modules = [];

        foreach ($all as $file => $data) {
            if (str_contains($file, 'overcms-core')) {
                continue;
            }

            $hasUpdate  = isset($updates->response[$file]);
            $newVersion = $hasUpdate ? ($updates->response[$file]->new_version ?? null) : null;

            $modules[] = [
                'id'              => rawurlencode($file),
                'file'            => $file,
                'name'            => $data['Name'],
                'description'     => $data['Description'],
                'version'         => $data['Version'],
                'author'          => wp_strip_all_tags($data['Author']),
                'pluginUri'       => $data['PluginURI'],
                'active'          => in_array($file, $active, true),
                'updateAvailable' => $hasUpdate,
                'newVersion'      => $newVersion,
                'settingsUrl'     => self::getSettingsUrl($file, $data),
            ];
        }

        return new \WP_REST_Response([
            'modules'       => $modules,
            'installNewUrl' => admin_url('plugin-install.php'),
        ]);
    }

    /**
     * Wymuś sprawdzenie aktualizacji pluginów przez WordPress.
     * Czyści transient i odpytuje WP update API.
     */
    public static function checkUpdates(): \WP_REST_Response
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Usuń transient żeby wymusić świeże sprawdzenie
        delete_site_transient('update_plugins');
        wp_update_plugins();

        $updates   = get_site_transient('update_plugins');
        $available = [];

        if ($updates && !empty($updates->response)) {
            foreach ($updates->response as $file => $data) {
                $available[] = [
                    'file'       => $file,
                    'newVersion' => $data->new_version ?? null,
                ];
            }
        }

        return new \WP_REST_Response([
            'checked'   => true,
            'updates'   => $available,
            'count'     => count($available),
        ]);
    }

    public static function activate(\WP_REST_Request $req): \WP_REST_Response
    {
        $file = rawurldecode((string) $req['id']);
        if (!function_exists('activate_plugin')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $result = activate_plugin($file);
        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
        }
        return new \WP_REST_Response(['success' => true]);
    }

    public static function deactivate(\WP_REST_Request $req): \WP_REST_Response
    {
        $file = rawurldecode((string) $req['id']);
        if (!function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        deactivate_plugins([$file]);
        return new \WP_REST_Response(['success' => true]);
    }

    public static function update(\WP_REST_Request $req): \WP_REST_Response
    {
        $file = rawurldecode((string) $req['id']);

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all = get_plugins();
        if (!isset($all[$file])) {
            return new \WP_REST_Response(['error' => 'Plugin nie istnieje: ' . $file], 404);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/misc.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-ajax-upgrader-skin.php';

        add_filter('filesystem_method', static fn () => 'direct');

        if (!WP_Filesystem()) {
            return new \WP_REST_Response(['error' => 'Cannot initialize WP_Filesystem'], 500);
        }

        $skin     = new \WP_Ajax_Upgrader_Skin();
        $upgrader = new \Plugin_Upgrader($skin);
        $result   = $upgrader->upgrade($file);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
        }

        if (method_exists($skin, 'get_errors')) {
            $errors = $skin->get_errors();
            if (is_wp_error($errors) && $errors->has_errors()) {
                return new \WP_REST_Response(['error' => $errors->get_error_message()], 500);
            }
        }

        // Pobierz nową wersję po aktualizacji
        $updated    = get_plugins();
        $newVersion = $updated[$file]['Version'] ?? null;

        return new \WP_REST_Response([
            'success'    => true,
            'newVersion' => $newVersion,
        ]);
    }

    public static function delete(\WP_REST_Request $req): \WP_REST_Response
    {
        $file = rawurldecode((string) $req['id']);

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('delete_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $all = get_plugins();
        if (!isset($all[$file])) {
            return new \WP_REST_Response(['error' => 'Plugin nie istnieje: ' . $file], 404);
        }

        // Dezaktywuj jeśli aktywny
        if (is_plugin_active($file)) {
            deactivate_plugins([$file]);
        }

        add_filter('filesystem_method', static fn () => 'direct');
        WP_Filesystem();

        $result = delete_plugins([$file]);

        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
        }
        if ($result === false) {
            return new \WP_REST_Response(['error' => 'Usunięcie nie powiodło się'], 500);
        }

        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Wyciąga URL strony ustawień pluginu z filtra plugin_action_links.
     * Pluginy rejestrują swoje linki "Settings" przez ten filtr przy plugins_loaded.
     */
    private static function getSettingsUrl(string $file, array $data): ?string
    {
        $links = apply_filters("plugin_action_links_{$file}", [], $file, $data, 'all');

        foreach ($links as $link) {
            if (!is_string($link)) {
                continue;
            }
            if (!preg_match('/href=["\']([^"\']+)["\']/', $link, $m)) {
                continue;
            }
            $href = html_entity_decode($m[1]);
            // Pomiń linki akcyjne (deactivate, delete, activate)
            if (preg_match('/action=(de)?activate|action=delete/', $href)) {
                continue;
            }
            // Upewnij się że to link do wp-admin
            if (!str_contains($href, 'admin.php') && !str_contains($href, 'options') && !str_contains($href, 'page=')) {
                continue;
            }
            // Absolutny URL jeśli względny
            if (!str_starts_with($href, 'http')) {
                $href = admin_url($href);
            }
            return $href;
        }

        return null;
    }
}
