<?php

namespace OverCMS\Core\Rest;

/**
 * Most do Divi Library (post_type=et_pb_layout) — pozwala panelowi
 * pokazać galerię szablonów i utworzyć nową stronę z wybranego.
 *
 * Endpointy:
 *   GET  /overcms/v1/templates                — lista layoutów + status Divi
 *   POST /overcms/v1/templates/use            — { templateId, title }
 *                                              → tworzy stronę z layoutem
 */
final class TemplatesController
{
    public static function register(): void
    {
        register_rest_route(RestRouter::NAMESPACE, '/templates', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'index'],
            'permission_callback' => [RestRouter::class, 'canEdit'],
        ]);

        register_rest_route(RestRouter::NAMESPACE, '/templates/use', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'usePage'],
            'permission_callback' => [RestRouter::class, 'canEdit'],
            'args'                => [
                'templateId' => ['type' => 'integer', 'required' => true],
                'title'      => ['type' => 'string',  'required' => true],
            ],
        ]);
    }

    public static function index(): \WP_REST_Response
    {
        $diviActive = self::diviActive();

        if (!$diviActive) {
            return new \WP_REST_Response([
                'diviActive' => false,
                'templates'  => [],
                'message'    => 'Motyw Divi nie jest aktywny. Zainstaluj go aby korzystać z szablonów.',
            ]);
        }

        $layouts = get_posts([
            'post_type'      => 'et_pb_layout',
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $templates = array_map(static function (\WP_Post $p): array {
            $thumbId = (int) get_post_thumbnail_id($p->ID);
            $thumb   = $thumbId ? wp_get_attachment_image_url($thumbId, 'medium') : null;

            // Wykryj typ layoutu (section, row, module, layout) z meta
            $type = get_post_meta($p->ID, '_et_pb_module_type', true) ?: 'layout';
            $built_for = get_post_meta($p->ID, '_et_pb_built_for_post_type', true) ?: 'page';

            return [
                'id'        => $p->ID,
                'title'     => $p->post_title ?: '(bez tytułu)',
                'slug'      => $p->post_name,
                'type'      => $type,
                'builtFor'  => $built_for,
                'thumb'     => $thumb,
                'createdAt' => $p->post_date_gmt,
                'modifiedAt'=> $p->post_modified_gmt,
            ];
        }, $layouts);

        return new \WP_REST_Response([
            'diviActive' => true,
            'templates'  => $templates,
            'count'      => count($templates),
        ]);
    }

    public static function usePage(\WP_REST_Request $req): \WP_REST_Response
    {
        if (!self::diviActive()) {
            return new \WP_REST_Response(['error' => 'Divi nie jest aktywny'], 400);
        }

        $templateId = (int) $req['templateId'];
        $title      = sanitize_text_field((string) $req['title']);

        if ($title === '') {
            return new \WP_REST_Response(['error' => 'Tytuł jest wymagany'], 400);
        }

        $template = get_post($templateId);
        if (!$template || $template->post_type !== 'et_pb_layout') {
            return new \WP_REST_Response(['error' => 'Szablon nie istnieje'], 404);
        }

        // Utwórz nową stronę z zawartością layoutu
        $pageId = wp_insert_post([
            'post_type'    => 'page',
            'post_status'  => 'draft',
            'post_title'   => $title,
            'post_content' => $template->post_content,
            'post_author'  => get_current_user_id(),
        ], true);

        if (is_wp_error($pageId)) {
            return new \WP_REST_Response(['error' => $pageId->get_error_message()], 500);
        }

        // Włącz Divi Builder dla tej strony
        update_post_meta($pageId, '_et_pb_use_builder', 'on');
        update_post_meta($pageId, '_et_pb_page_layout', 'et_no_sidebar');
        update_post_meta($pageId, '_et_pb_side_nav', 'off');
        update_post_meta($pageId, '_et_pb_post_hide_nav', 'default');

        // Skopiuj również _et_pb_* meta z szablonu (jeśli sa)
        $templateMeta = get_post_meta($templateId);
        foreach ($templateMeta as $key => $values) {
            if (str_starts_with($key, '_et_pb_') && !in_array($key, ['_et_pb_use_builder', '_et_pb_page_layout'], true)) {
                update_post_meta($pageId, $key, maybe_unserialize($values[0] ?? ''));
            }
        }

        return new \WP_REST_Response([
            'success'  => true,
            'pageId'   => $pageId,
            'editUrl'  => add_query_arg([
                'p'           => $pageId,
                'et_fb'       => 1,
                'PageSpeed'   => 'off',
            ], home_url('/')),
            'previewUrl' => get_permalink($pageId),
        ]);
    }

    private static function diviActive(): bool
    {
        $theme = wp_get_theme();
        if ($theme && (strcasecmp((string) $theme->get('Name'), 'Divi') === 0 || strcasecmp((string) $theme->get('Template'), 'Divi') === 0)) {
            return true;
        }
        // Sprawdź też template (Divi child theme)
        if ($theme && strcasecmp((string) $theme->get_template(), 'Divi') === 0) {
            return true;
        }
        return false;
    }
}
