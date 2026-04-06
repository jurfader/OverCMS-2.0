<?php

namespace OverCMS\Core\Rest;

/**
 * Most do natywnego WP nav-menus.
 *
 * Endpointy:
 *   GET    /overcms/v1/menus                          — lista menu
 *   POST   /overcms/v1/menus                          — { name } utwórz menu
 *   GET    /overcms/v1/menus/:id                      — szczegóły menu + items
 *   DELETE /overcms/v1/menus/:id                      — usuń menu
 *   POST   /overcms/v1/menus/:id/items                — dodaj item
 *   PATCH  /overcms/v1/menus/:id/items/:itemId        — edytuj item
 *   DELETE /overcms/v1/menus/:id/items/:itemId        — usuń item
 *   POST   /overcms/v1/menus/:id/items/reorder        — { order: [id...] }
 *   GET    /overcms/v1/menus/sources                  — pages, posts, categories do dodania
 */
final class NavigationController
{
    public static function register(): void
    {
        $perm = [RestRouter::class, 'canManage'];
        $ns = RestRouter::NAMESPACE;

        register_rest_route($ns, '/menus', [
            ['methods' => \WP_REST_Server::READABLE,  'callback' => [self::class, 'listMenus'],   'permission_callback' => $perm],
            ['methods' => \WP_REST_Server::CREATABLE, 'callback' => [self::class, 'createMenu'], 'permission_callback' => $perm],
        ]);

        register_rest_route($ns, '/menus/sources', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'sources'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/menus/(?P<id>\d+)', [
            ['methods' => \WP_REST_Server::READABLE, 'callback' => [self::class, 'getMenu'],    'permission_callback' => $perm],
            ['methods' => \WP_REST_Server::DELETABLE,'callback' => [self::class, 'deleteMenu'], 'permission_callback' => $perm],
        ]);

        register_rest_route($ns, '/menus/(?P<id>\d+)/items', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'addItem'],
            'permission_callback' => $perm,
            'args'                => [
                'type'     => ['type' => 'string', 'required' => true],
                'objectId' => ['type' => 'integer'],
                'url'      => ['type' => 'string'],
                'title'    => ['type' => 'string', 'required' => true],
                'parent'   => ['type' => 'integer', 'default' => 0],
            ],
        ]);

        register_rest_route($ns, '/menus/(?P<id>\d+)/items/(?P<itemId>\d+)', [
            ['methods' => \WP_REST_Server::EDITABLE, 'callback' => [self::class, 'updateItem'], 'permission_callback' => $perm],
            ['methods' => \WP_REST_Server::DELETABLE,'callback' => [self::class, 'deleteItem'], 'permission_callback' => $perm],
        ]);

        register_rest_route($ns, '/menus/(?P<id>\d+)/items/reorder', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'reorderItems'],
            'permission_callback' => $perm,
            'args'                => [
                'order' => ['type' => 'array', 'required' => true],
            ],
        ]);
    }

    public static function listMenus(): \WP_REST_Response
    {
        require_once ABSPATH . 'wp-admin/includes/nav-menu.php';

        $menus = wp_get_nav_menus();
        $locations = get_registered_nav_menus();
        $assigned = get_nav_menu_locations();

        $out = array_map(static function ($menu) use ($assigned) {
            $loc = array_keys(array_filter($assigned, fn ($id) => (int) $id === (int) $menu->term_id));
            return [
                'id'        => (int) $menu->term_id,
                'name'      => $menu->name,
                'slug'      => $menu->slug,
                'count'     => (int) $menu->count,
                'locations' => $loc,
            ];
        }, $menus);

        return new \WP_REST_Response([
            'menus'     => array_values($out),
            'locations' => $locations,
        ]);
    }

    public static function createMenu(\WP_REST_Request $req): \WP_REST_Response
    {
        $name = sanitize_text_field((string) $req->get_param('name'));
        if ($name === '') {
            return new \WP_REST_Response(['error' => 'Nazwa menu jest wymagana'], 400);
        }

        $id = wp_create_nav_menu($name);
        if (is_wp_error($id)) {
            return new \WP_REST_Response(['error' => $id->get_error_message()], 500);
        }
        return new \WP_REST_Response(['success' => true, 'id' => (int) $id]);
    }

    public static function getMenu(\WP_REST_Request $req): \WP_REST_Response
    {
        $id = (int) $req['id'];
        $menu = wp_get_nav_menu_object($id);
        if (!$menu) {
            return new \WP_REST_Response(['error' => 'Menu nie istnieje'], 404);
        }

        $items = wp_get_nav_menu_items($id, ['orderby' => 'menu_order']) ?: [];
        $out = array_map(static function ($item): array {
            return [
                'id'         => (int) $item->ID,
                'title'      => $item->title,
                'url'        => $item->url,
                'type'       => $item->type,         // post_type | taxonomy | custom
                'object'     => $item->object,       // page, post, category, custom
                'objectId'   => (int) $item->object_id,
                'parent'     => (int) $item->menu_item_parent,
                'order'      => (int) $item->menu_order,
                'target'     => $item->target,
                'classes'    => is_array($item->classes) ? implode(' ', $item->classes) : '',
            ];
        }, $items);

        return new \WP_REST_Response([
            'id'    => (int) $menu->term_id,
            'name'  => $menu->name,
            'items' => $out,
        ]);
    }

    public static function deleteMenu(\WP_REST_Request $req): \WP_REST_Response
    {
        $id = (int) $req['id'];
        $result = wp_delete_nav_menu($id);
        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
        }
        return new \WP_REST_Response(['success' => true]);
    }

    public static function addItem(\WP_REST_Request $req): \WP_REST_Response
    {
        $menuId   = (int) $req['id'];
        $type     = sanitize_key((string) $req->get_param('type'));   // page|post|category|custom
        $objectId = (int) $req->get_param('objectId');
        $url      = esc_url_raw((string) $req->get_param('url'));
        $title    = sanitize_text_field((string) $req->get_param('title'));
        $parent   = (int) $req->get_param('parent');

        $args = [
            'menu-item-title'     => $title,
            'menu-item-status'    => 'publish',
            'menu-item-parent-id' => $parent,
        ];

        switch ($type) {
            case 'page':
            case 'post':
                $args['menu-item-object-id'] = $objectId;
                $args['menu-item-object']    = $type;
                $args['menu-item-type']      = 'post_type';
                break;
            case 'category':
            case 'tag':
                $args['menu-item-object-id'] = $objectId;
                $args['menu-item-object']    = $type === 'tag' ? 'post_tag' : 'category';
                $args['menu-item-type']      = 'taxonomy';
                break;
            case 'custom':
            default:
                $args['menu-item-url']  = $url;
                $args['menu-item-type'] = 'custom';
                break;
        }

        $itemId = wp_update_nav_menu_item($menuId, 0, $args);
        if (is_wp_error($itemId)) {
            return new \WP_REST_Response(['error' => $itemId->get_error_message()], 500);
        }
        return new \WP_REST_Response(['success' => true, 'itemId' => (int) $itemId]);
    }

    public static function updateItem(\WP_REST_Request $req): \WP_REST_Response
    {
        $menuId = (int) $req['id'];
        $itemId = (int) $req['itemId'];

        $args = [];
        if (($t = $req->get_param('title')) !== null) $args['menu-item-title'] = sanitize_text_field((string) $t);
        if (($u = $req->get_param('url')) !== null)   $args['menu-item-url']   = esc_url_raw((string) $u);
        if (($p = $req->get_param('parent')) !== null) $args['menu-item-parent-id'] = (int) $p;
        if (($tg = $req->get_param('target')) !== null) $args['menu-item-target'] = sanitize_text_field((string) $tg);

        $result = wp_update_nav_menu_item($menuId, $itemId, $args);
        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
        }
        return new \WP_REST_Response(['success' => true]);
    }

    public static function deleteItem(\WP_REST_Request $req): \WP_REST_Response
    {
        $itemId = (int) $req['itemId'];
        $result = wp_delete_post($itemId, true);
        if (!$result) {
            return new \WP_REST_Response(['error' => 'Nie udało się usunąć'], 500);
        }
        return new \WP_REST_Response(['success' => true]);
    }

    public static function reorderItems(\WP_REST_Request $req): \WP_REST_Response
    {
        $order = (array) $req->get_param('order');
        $position = 1;
        foreach ($order as $itemId) {
            $itemId = (int) $itemId;
            if ($itemId <= 0) continue;
            wp_update_post([
                'ID'         => $itemId,
                'menu_order' => $position++,
            ]);
        }
        return new \WP_REST_Response(['success' => true, 'count' => $position - 1]);
    }

    public static function sources(): \WP_REST_Response
    {
        $pages = get_posts([
            'post_type'   => 'page',
            'post_status' => 'publish',
            'numberposts' => 100,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ]);

        $posts = get_posts([
            'post_type'   => 'post',
            'post_status' => 'publish',
            'numberposts' => 50,
            'orderby'     => 'date',
            'order'       => 'DESC',
        ]);

        $categories = get_categories(['hide_empty' => false]);

        return new \WP_REST_Response([
            'pages' => array_map(static fn ($p) => [
                'id'    => $p->ID,
                'title' => $p->post_title,
                'url'   => get_permalink($p->ID),
            ], $pages),
            'posts' => array_map(static fn ($p) => [
                'id'    => $p->ID,
                'title' => $p->post_title,
                'url'   => get_permalink($p->ID),
            ], $posts),
            'categories' => array_map(static fn ($c) => [
                'id'    => $c->term_id,
                'title' => $c->name,
                'url'   => get_term_link($c),
            ], $categories),
        ]);
    }
}
