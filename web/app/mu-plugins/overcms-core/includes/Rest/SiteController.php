<?php

namespace OverCMS\Core\Rest;

final class SiteController
{
    public static function register(): void
    {
        register_rest_route(RestRouter::NAMESPACE, '/site', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'get'],
                'permission_callback' => [RestRouter::class, 'canEdit'],
            ],
            [
                'methods'             => \WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'update'],
                'permission_callback' => [RestRouter::class, 'canManage'],
                'args'                => [
                    'title'       => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'language'    => ['type' => 'string'],
                    'timezone'    => ['type' => 'string'],
                    'theme'       => ['type' => 'string', 'enum' => ['dark', 'light']],
                ],
            ],
        ]);
    }

    public static function get(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'title'       => get_option('blogname'),
            'description' => get_option('blogdescription'),
            'siteUrl'     => home_url('/'),
            'adminEmail'  => get_option('admin_email'),
            'language'    => get_option('WPLANG') ?: get_locale(),
            'timezone'    => get_option('timezone_string') ?: 'UTC',
            'permalinks'  => get_option('permalink_structure'),
            'theme'       => get_option('overcms_panel_theme', 'dark'),
            'wpVersion'   => get_bloginfo('version'),
            'phpVersion'  => PHP_VERSION,
        ]);
    }

    public static function update(\WP_REST_Request $req): \WP_REST_Response
    {
        if ($title = $req->get_param('title')) {
            update_option('blogname', sanitize_text_field($title));
        }
        if (($desc = $req->get_param('description')) !== null) {
            update_option('blogdescription', sanitize_text_field((string) $desc));
        }
        if ($lang = $req->get_param('language')) {
            update_option('WPLANG', sanitize_text_field($lang));
        }
        if ($tz = $req->get_param('timezone')) {
            update_option('timezone_string', sanitize_text_field($tz));
        }
        if ($theme = $req->get_param('theme')) {
            update_option('overcms_panel_theme', $theme === 'light' ? 'light' : 'dark');
        }
        return self::get();
    }
}
