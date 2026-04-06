<?php

namespace OverCMS\Core\Rest;

final class DashboardController
{
    public static function register(): void
    {
        register_rest_route(RestRouter::NAMESPACE, '/dashboard', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'index'],
            'permission_callback' => [RestRouter::class, 'canEdit'],
        ]);
    }

    public static function index(): \WP_REST_Response
    {
        $pages = wp_count_posts('page');
        $posts = wp_count_posts('post');
        $media = wp_count_attachments();

        $mediaTotal = 0;
        foreach ((array) $media as $count) {
            $mediaTotal += (int) $count;
        }

        $recent = get_posts([
            'post_type'   => ['page', 'post'],
            'post_status' => ['publish', 'draft', 'pending'],
            'numberposts' => 5,
            'orderby'     => 'modified',
            'order'       => 'DESC',
        ]);

        $recentList = array_map(static function (\WP_Post $p): array {
            return [
                'id'          => $p->ID,
                'title'       => $p->post_title ?: '(bez tytułu)',
                'type'        => $p->post_type,
                'status'      => $p->post_status,
                'modifiedAt'  => $p->post_modified_gmt,
                'editUrl'     => get_edit_post_link($p->ID, 'raw'),
            ];
        }, $recent);

        return new \WP_REST_Response([
            'stats' => [
                'pages'     => (int) ($pages->publish ?? 0),
                'pagesAll'  => (int) array_sum((array) $pages),
                'posts'     => (int) ($posts->publish ?? 0),
                'postsAll'  => (int) array_sum((array) $posts),
                'media'     => $mediaTotal,
                'users'     => (int) count_users()['total_users'],
            ],
            'recent' => $recentList,
            'wpVersion' => get_bloginfo('version'),
        ]);
    }
}
