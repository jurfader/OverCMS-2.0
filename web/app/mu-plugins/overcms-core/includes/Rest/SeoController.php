<?php

namespace OverCMS\Core\Rest;

/**
 * Most do Rank Math: czyta i zapisuje meta SEO dla pojedynczych stron.
 * Dla globalnych ustawień zwraca też status sitemap/schema.
 */
final class SeoController
{
    public static function register(): void
    {
        register_rest_route(RestRouter::NAMESPACE, '/seo/global', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'getGlobal'],
                'permission_callback' => [RestRouter::class, 'canManage'],
            ],
        ]);

        register_rest_route(RestRouter::NAMESPACE, '/seo/page/(?P<id>\d+)', [
            [
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => [self::class, 'getPage'],
                'permission_callback' => [RestRouter::class, 'canEdit'],
            ],
            [
                'methods'             => \WP_REST_Server::EDITABLE,
                'callback'            => [self::class, 'updatePage'],
                'permission_callback' => [RestRouter::class, 'canEdit'],
            ],
        ]);
    }

    public static function getGlobal(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'rankMathInstalled' => self::rankMathActive(),
            'sitemapUrl'        => self::rankMathActive()
                ? home_url('/sitemap_index.xml')
                : home_url('/wp-sitemap.xml'),
            'robotsUrl'         => home_url('/robots.txt'),
            'titleSeparator'    => get_option('rank_math_title_separator', '-'),
            'siteName'          => get_bloginfo('name'),
            'siteDescription'   => get_bloginfo('description'),
        ]);
    }

    public static function getPage(\WP_REST_Request $req): \WP_REST_Response
    {
        $id = (int) $req['id'];
        if (!get_post($id)) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }

        return new \WP_REST_Response([
            'id'          => $id,
            'title'       => get_post_meta($id, 'rank_math_title', true) ?: get_the_title($id),
            'description' => get_post_meta($id, 'rank_math_description', true),
            'focusKeyword'=> get_post_meta($id, 'rank_math_focus_keyword', true),
            'canonical'   => get_post_meta($id, 'rank_math_canonical_url', true),
            'noindex'     => self::hasRobotsFlag($id, 'noindex'),
            'nofollow'    => self::hasRobotsFlag($id, 'nofollow'),
            'ogTitle'     => get_post_meta($id, 'rank_math_facebook_title', true),
            'ogDescription' => get_post_meta($id, 'rank_math_facebook_description', true),
            'ogImage'     => get_post_meta($id, 'rank_math_facebook_image', true),
        ]);
    }

    public static function updatePage(\WP_REST_Request $req): \WP_REST_Response
    {
        $id = (int) $req['id'];
        if (!get_post($id) || !current_user_can('edit_post', $id)) {
            return new \WP_REST_Response(['error' => 'Forbidden'], 403);
        }

        $map = [
            'title'         => 'rank_math_title',
            'description'   => 'rank_math_description',
            'focusKeyword'  => 'rank_math_focus_keyword',
            'canonical'     => 'rank_math_canonical_url',
            'ogTitle'       => 'rank_math_facebook_title',
            'ogDescription' => 'rank_math_facebook_description',
            'ogImage'       => 'rank_math_facebook_image',
        ];

        foreach ($map as $param => $meta) {
            $val = $req->get_param($param);
            if ($val !== null) {
                update_post_meta($id, $meta, sanitize_text_field((string) $val));
            }
        }

        // Robots
        $robots = (array) (get_post_meta($id, 'rank_math_robots', true) ?: ['index']);
        if ($req->get_param('noindex') !== null) {
            $robots = array_diff($robots, ['index', 'noindex']);
            $robots[] = $req->get_param('noindex') ? 'noindex' : 'index';
        }
        if ($req->get_param('nofollow') !== null) {
            $robots = array_diff($robots, ['nofollow']);
            if ($req->get_param('nofollow')) {
                $robots[] = 'nofollow';
            }
        }
        update_post_meta($id, 'rank_math_robots', array_values(array_unique($robots)));

        return self::getPage($req);
    }

    private static function rankMathActive(): bool
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        return is_plugin_active('seo-by-rank-math/rank-math.php');
    }

    private static function hasRobotsFlag(int $postId, string $flag): bool
    {
        $robots = get_post_meta($postId, 'rank_math_robots', true);
        return is_array($robots) && in_array($flag, $robots, true);
    }
}
