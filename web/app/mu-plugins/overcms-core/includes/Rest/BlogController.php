<?php

namespace OverCMS\Core\Rest;

/**
 * Blog — tworzenie wpisów z ustawionym Divi builderem.
 *
 * Endpointy:
 *   POST /overcms/v1/blog/create — utwórz wpis + ustaw _et_pb_use_builder
 */
final class BlogController
{
    public static function register(): void
    {
        $perm = [RestRouter::class, 'canEdit'];
        $ns   = RestRouter::NAMESPACE;

        register_rest_route($ns, '/blog/create', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'create'],
            'permission_callback' => $perm,
        ]);
    }

    public static function create(\WP_REST_Request $req): \WP_REST_Response
    {
        $title      = sanitize_text_field((string) ($req['title'] ?? ''));
        $excerpt    = sanitize_textarea_field((string) ($req['excerpt'] ?? ''));
        $status     = in_array($req['status'], ['publish', 'draft'], true) ? $req['status'] : 'draft';
        $categories = array_map('intval', (array) ($req['categories'] ?? []));

        if ($title === '') {
            return new \WP_REST_Response(['error' => 'Tytuł jest wymagany'], 400);
        }

        $postData = [
            'post_title'    => $title,
            'post_excerpt'  => $excerpt,
            'post_status'   => $status,
            'post_type'     => 'post',
            'post_category' => $categories ?: [],
        ];

        $postId = wp_insert_post($postData, true);

        if (is_wp_error($postId)) {
            return new \WP_REST_Response(['error' => $postId->get_error_message()], 500);
        }

        // Włącz Divi visual builder dla tego wpisu
        update_post_meta($postId, '_et_pb_use_builder', 'on');
        // Wymuś domyślny layout Divi (pusty builder gotowy do edycji)
        update_post_meta($postId, '_et_pb_old_content', '');

        $link    = get_permalink($postId) ?: (get_home_url() . '/?p=' . $postId);
        $sep     = str_contains($link, '?') ? '&' : '?';
        $editUrl = $link . $sep . 'et_fb=1&PageSpeed=off';

        return new \WP_REST_Response([
            'id'      => $postId,
            'link'    => $link,
            'editUrl' => $editUrl,
        ]);
    }
}
