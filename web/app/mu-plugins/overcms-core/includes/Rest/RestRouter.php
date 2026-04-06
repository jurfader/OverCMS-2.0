<?php

namespace OverCMS\Core\Rest;

final class RestRouter
{
    public const NAMESPACE = 'overcms/v1';

    public static function register(): void
    {
        add_action('rest_api_init', static function (): void {
            DashboardController::register();
            SiteController::register();
            SeoController::register();
            ModulesController::register();
            MarketplaceController::register();
            MediaController::register();
            TemplatesController::register();
            NavigationController::register();
            BackupController::register();
            SecurityController::register();
            ThemesController::register();
        });
    }

    public static function canEdit(): bool
    {
        return current_user_can('edit_posts');
    }

    public static function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
