<?php

namespace OverCMS\Core\Rest;

/**
 * Zarządzanie motywami: lista, upload ZIP, aktywacja, usuwanie.
 *
 * Endpointy:
 *   GET    /overcms/v1/themes                — lista zainstalowanych motywów
 *   POST   /overcms/v1/themes/upload         — multipart/form-data z polem 'file' (.zip)
 *   POST   /overcms/v1/themes/:slug/activate — aktywuj motyw po slug
 *   DELETE /overcms/v1/themes/:slug          — usuń motyw
 */
final class ThemesController
{
    public static function register(): void
    {
        $perm = [RestRouter::class, 'canManage'];
        $ns   = RestRouter::NAMESPACE;

        register_rest_route($ns, '/themes', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'index'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/themes/upload', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'upload'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/themes/(?P<slug>[a-zA-Z0-9_.-]+)/activate', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'activate'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/themes/(?P<slug>[a-zA-Z0-9_.-]+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [self::class, 'delete'],
            'permission_callback' => $perm,
        ]);
    }

    public static function index(): \WP_REST_Response
    {
        $themes  = wp_get_themes();
        $current = wp_get_theme();
        $items   = [];

        foreach ($themes as $slug => $theme) {
            $items[] = [
                'slug'        => $slug,
                'name'        => $theme->get('Name'),
                'version'     => $theme->get('Version'),
                'author'      => wp_strip_all_tags((string) $theme->get('Author')),
                'description' => wp_strip_all_tags((string) $theme->get('Description')),
                'screenshot'  => $theme->get_screenshot() ?: null,
                'active'      => $slug === $current->get_stylesheet(),
            ];
        }

        return new \WP_REST_Response([
            'themes'        => $items,
            'activeSlug'    => $current->get_stylesheet(),
        ]);
    }

    public static function upload(\WP_REST_Request $req): \WP_REST_Response
    {
        try {
            $files = $req->get_file_params();
            if (empty($files['file'])) {
                return new \WP_REST_Response(['error' => 'Brak pliku w polu „file"'], 400);
            }

            $file = $files['file'];
            if (!is_uploaded_file($file['tmp_name'])) {
                return new \WP_REST_Response(['error' => 'Niepoprawny upload'], 400);
            }
            if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                return new \WP_REST_Response(['error' => 'Błąd uploadu PHP: kod ' . $file['error']], 400);
            }
            if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
                return new \WP_REST_Response(['error' => 'Wymagany plik .zip'], 400);
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/misc.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-theme-upgrader.php';
            require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader-skin.php';
            require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';

            // Wymuś direct filesystem (potrzebujemy zapisu do wp-content/themes)
            add_filter('filesystem_method', static fn () => 'direct');

            if (!WP_Filesystem()) {
                return new \WP_REST_Response([
                    'error' => 'Cannot initialize WP_Filesystem (sprawdź uprawnienia ' . get_current_user() . ')',
                ], 500);
            }

            $skin     = new \Automatic_Upgrader_Skin();
            $upgrader = new \Theme_Upgrader($skin);
            // overwrite='replace' żeby działało też przy reuploadzie tej samej wersji
            $result   = $upgrader->install($file['tmp_name'], ['overwrite_package' => true]);

            if (is_wp_error($result)) {
                return new \WP_REST_Response(['error' => 'install: ' . $result->get_error_message()], 500);
            }
            if ($skin->get_errors()->has_errors()) {
                return new \WP_REST_Response([
                    'error' => 'skin: ' . ($skin->get_error_messages()[0] ?? 'unknown'),
                ], 500);
            }
            if ($result === false) {
                return new \WP_REST_Response([
                    'error' => 'Theme_Upgrader::install zwróciło false (sprawdź uprawnienia wp-content/themes)',
                ], 500);
            }

            $info = $upgrader->theme_info();
            $slug = $info ? $info->get_stylesheet() : null;

            return new \WP_REST_Response([
                'success' => true,
                'slug'    => $slug,
                'name'    => $info ? $info->get('Name') : null,
                'version' => $info ? $info->get('Version') : null,
            ]);
        } catch (\Throwable $e) {
            return new \WP_REST_Response([
                'error' => 'exception: ' . $e->getMessage(),
                'file'  => basename($e->getFile()) . ':' . $e->getLine(),
            ], 500);
        }
    }

    public static function activate(\WP_REST_Request $req): \WP_REST_Response
    {
        $slug = sanitize_key((string) $req['slug']);
        if (!wp_get_theme($slug)->exists()) {
            return new \WP_REST_Response(['error' => 'Motyw nie istnieje'], 404);
        }
        switch_theme($slug);
        return new \WP_REST_Response([
            'success'    => true,
            'activeSlug' => $slug,
        ]);
    }

    public static function delete(\WP_REST_Request $req): \WP_REST_Response
    {
        $slug = sanitize_key((string) $req['slug']);
        $theme = wp_get_theme($slug);
        if (!$theme->exists()) {
            return new \WP_REST_Response(['error' => 'Motyw nie istnieje'], 404);
        }
        if (wp_get_theme()->get_stylesheet() === $slug) {
            return new \WP_REST_Response(['error' => 'Nie możesz usunąć aktywnego motywu'], 400);
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/theme.php';

        add_filter('filesystem_method', static fn () => 'direct');
        if (!WP_Filesystem()) {
            return new \WP_REST_Response(['error' => 'Cannot initialize WP_Filesystem'], 500);
        }

        $result = delete_theme($slug);
        if (is_wp_error($result)) {
            return new \WP_REST_Response(['error' => $result->get_error_message()], 500);
        }
        return new \WP_REST_Response(['success' => true]);
    }
}
