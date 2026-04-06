<?php

namespace OverCMS\Core;

/**
 * Przekierowuje domyślny dashboard wp-admin/index.php do panelu OverCMS.
 * Edycja stron przez Divi (post.php?action=edit) działa normalnie.
 */
final class Redirects
{
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'redirectDashboard']);
        add_filter('login_redirect', [self::class, 'redirectAfterLogin'], 10, 3);
    }

    public static function redirectDashboard(): void
    {
        global $pagenow;

        if (!is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        if ($pagenow !== 'index.php') {
            return;
        }

        // Nie przekierowuj jeśli już jesteśmy na stronie panelu
        if (isset($_GET['page']) && $_GET['page'] === PanelLoader::SLUG) {
            return;
        }

        if (!current_user_can(PanelLoader::CAPABILITY)) {
            return;
        }

        wp_safe_redirect(admin_url('admin.php?page=' . PanelLoader::SLUG));
        exit;
    }

    public static function redirectAfterLogin(string $redirect, string $requested, $user): string
    {
        if (!is_object($user) || !isset($user->ID) || is_wp_error($user)) {
            return $redirect;
        }
        if (!user_can($user, PanelLoader::CAPABILITY)) {
            return $redirect;
        }
        return admin_url('admin.php?page=' . PanelLoader::SLUG);
    }
}
