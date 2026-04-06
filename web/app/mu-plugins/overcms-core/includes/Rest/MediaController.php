<?php

namespace OverCMS\Core\Rest;

/**
 * Lekkie podsumowanie mediów na potrzeby panelu — szybsze niż /wp/v2/media
 * gdy potrzebujemy tylko id/title/url/mime/sizes.
 */
final class MediaController
{
    public static function register(): void
    {
        register_rest_route(RestRouter::NAMESPACE, '/media/summary', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'summary'],
            'permission_callback' => [RestRouter::class, 'canEdit'],
            'args'                => [
                'page'     => ['type' => 'integer', 'default' => 1],
                'per_page' => ['type' => 'integer', 'default' => 30],
                'search'   => ['type' => 'string', 'default' => ''],
                'mime'     => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    public static function summary(\WP_REST_Request $req): \WP_REST_Response
    {
        $args = [
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => max(1, min(100, (int) $req['per_page'])),
            'paged'          => max(1, (int) $req['page']),
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        if ($search = trim((string) $req['search'])) {
            $args['s'] = sanitize_text_field($search);
        }
        if ($mime = trim((string) $req['mime'])) {
            $args['post_mime_type'] = sanitize_text_field($mime);
        }

        $query = new \WP_Query($args);
        $items = [];
        foreach ($query->posts as $post) {
            $thumb = wp_get_attachment_image_src($post->ID, 'medium');
            $full  = wp_get_attachment_image_src($post->ID, 'full');
            $items[] = [
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'mime'      => $post->post_mime_type,
                'date'      => $post->post_date_gmt,
                'thumb'     => $thumb ? $thumb[0] : null,
                'url'       => $full ? $full[0] : wp_get_attachment_url($post->ID),
                'width'     => $full[1] ?? null,
                'height'    => $full[2] ?? null,
                'sizeBytes' => filesize(get_attached_file($post->ID)) ?: null,
            ];
        }

        return new \WP_REST_Response([
            'items'       => $items,
            'total'       => (int) $query->found_posts,
            'totalPages'  => (int) $query->max_num_pages,
            'page'        => (int) $args['paged'],
        ]);
    }
}
