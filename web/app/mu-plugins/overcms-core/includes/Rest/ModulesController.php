<?php

namespace OverCMS\Core\Rest;

/**
 * Most do wp_get_plugins() — pozwala panelowi wyświetlać i (de)aktywować pluginy
 * (standardowy marketplace WordPress.org otwiera się w nowej karcie/iframe).
 */
final class ModulesController
{
    public static function register(): void
    {
        register_rest_route(RestRouter::NAMESPACE, '/modules', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'index'],
            'permission_callback' => [RestRouter::class, 'canManage'],
        ]);

        register_rest_route(RestRouter::NAMESPACE, '/modules/(?P<id>[^/]+)/activate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'activate'],
            'permission_callback' => [RestRouter::class, 'canManage'],
        ]);

        register_rest_route(RestRouter::NAMESPACE, '/modules/(?P<id>[^/]+)/deactivate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'deactivate'],
            'permission_callback' => [RestRouter::class, 'canManage'],
        ]);
    }

    public static function index(): \WP_REST_Response
    {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all     = get_plugins();
        $active  = (array) get_option('active_plugins', []);
        $modules = [];

        foreach ($all as $file => $data) {
            // Ukryj OverCMS Core (mu-plugin) z listy — nie da się go wyłączyć
            if (str_contains($file, 'overcms-core')) {
                continue;
            }
            $modules[] = [
                'id'          => rawurlencode($file),
                'file'        => $file,
                'name'        => $data['Name'],
                'description' => $data['Description'],
                'version'     => $data['Version'],
                'author'      => wp_strip_all_tags($data['Author']),
                'pluginUri'   => $data['PluginURI'],
                'active'      => in_array($file, $active, true),
            ];
        }

        return new \WP_REST_Response([
            'modules'        => $modules,
            'installNewUrl'  => admin_url('plugin-install.php'),
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
}
