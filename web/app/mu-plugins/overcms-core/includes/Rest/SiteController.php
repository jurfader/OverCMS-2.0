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
                    'homepageId'  => ['type' => 'integer'],
                    'postsPageId' => ['type' => 'integer'],
                ],
            ],
        ]);
    }

    public static function get(): \WP_REST_Response
    {
        $showOnFront = get_option('show_on_front', 'posts');
        $homepageId  = $showOnFront === 'page' ? (int) get_option('page_on_front', 0) : 0;
        $postsPageId = (int) get_option('page_for_posts', 0);

        // Lista stron do dropdowna (tylko opublikowane)
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        $pageOptions = array_map(static fn($p) => [
            'id'    => (int) $p->ID,
            'title' => $p->post_title !== '' ? $p->post_title : '(bez tytulu)',
        ], $pages);

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
            'homepageId'  => $homepageId,
            'postsPageId' => $postsPageId,
            'pages'       => $pageOptions,
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

        // Strona glowna: homepageId=0 → "Najnowsze posty" (show_on_front=posts);
        // homepageId>0 → konkretna strona statyczna (show_on_front=page, page_on_front=ID)
        if ($req->has_param('homepageId')) {
            $homepageId = (int) $req->get_param('homepageId');
            if ($homepageId > 0 && get_post($homepageId) && get_post_status($homepageId) === 'publish') {
                update_option('show_on_front', 'page');
                update_option('page_on_front', $homepageId);
            } else {
                update_option('show_on_front', 'posts');
                update_option('page_on_front', 0);
            }
        }
        if ($req->has_param('postsPageId')) {
            $postsPageId = (int) $req->get_param('postsPageId');
            if ($postsPageId > 0 && get_post($postsPageId) && get_post_status($postsPageId) === 'publish') {
                update_option('page_for_posts', $postsPageId);
            } else {
                update_option('page_for_posts', 0);
            }
        }

        return self::get();
    }
}
