<?php

namespace OverCMS\Core;

/**
 * Wycina zbędne fragmenty wp-admin: Komentarze, Linki, dashboard widgets,
 * Hello Dolly, pasek admina na froncie.
 */
final class AdminCleanup
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'removeMenus'], 999);
        add_action('wp_before_admin_bar_render', [self::class, 'cleanAdminBar']);
        add_action('wp_dashboard_setup', [self::class, 'removeDashboardWidgets'], 999);
        add_action('admin_init', [self::class, 'removeHelloDolly']);
        add_filter('show_admin_bar', '__return_false');

        // Wyłącz komentarze całkowicie
        add_action('admin_init', [self::class, 'disableCommentsAdmin']);
        add_filter('comments_open', '__return_false', 20);
        add_filter('pings_open', '__return_false', 20);
        add_filter('comments_array', '__return_empty_array', 10);

        // Ukryj wskaźnik komentarzy w admin barze
        add_action('admin_menu', static function (): void {
            remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');
        });
    }

    public static function removeMenus(): void
    {
        remove_menu_page('edit-comments.php');
        remove_menu_page('link-manager.php');
        // Tools zostawiamy bo Rank Math/Cache Enabler tam dodają submenu
    }

    public static function cleanAdminBar(): void
    {
        global $wp_admin_bar;
        if (!$wp_admin_bar) {
            return;
        }
        $wp_admin_bar->remove_menu('wp-logo');
        $wp_admin_bar->remove_menu('comments');
        $wp_admin_bar->remove_menu('new-link');
        $wp_admin_bar->remove_menu('new-post');
        $wp_admin_bar->remove_menu('updates');
    }

    public static function removeDashboardWidgets(): void
    {
        $widgets = [
            'dashboard_primary'           => 'side',     // WordPress News
            'dashboard_quick_press'       => 'side',     // Quick Draft
            'dashboard_right_now'         => 'normal',   // At a Glance
            'dashboard_activity'          => 'normal',   // Activity
            'dashboard_recent_drafts'     => 'side',
            'dashboard_recent_comments'   => 'normal',
            'dashboard_incoming_links'    => 'normal',
            'dashboard_plugins'           => 'normal',
            'dashboard_secondary'         => 'side',
            'dashboard_site_health'       => 'normal',
            'welcome_panel'               => 'normal',
        ];
        foreach ($widgets as $id => $context) {
            remove_meta_box($id, 'dashboard', $context);
        }
        remove_action('welcome_panel', 'wp_welcome_panel');
    }

    public static function removeHelloDolly(): void
    {
        $hello = WP_PLUGIN_DIR . '/hello.php';
        if (file_exists($hello)) {
            @unlink($hello);
        }
    }

    public static function disableCommentsAdmin(): void
    {
        // Ukryj metaboksy komentarzy z edytora postów/stron
        foreach (['post', 'page'] as $type) {
            remove_meta_box('commentstatusdiv', $type, 'normal');
            remove_meta_box('commentsdiv', $type, 'normal');
            remove_meta_box('trackbacksdiv', $type, 'normal');
        }
    }
}
