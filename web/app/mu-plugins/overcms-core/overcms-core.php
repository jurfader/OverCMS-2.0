<?php
/**
 * Plugin Name: OverCMS Core
 * Plugin URI:  https://github.com/overmedia/overcms
 * Description: Rdzeń OverCMS — własny panel admina, REST API, hardening, system aktualizacji.
 * Version:     1.0.0
 * Author:      OVERMEDIA
 * Author URI:  https://overmedia.pl
 * License:     Proprietary
 * Text Domain: overcms
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OVERCMS_VERSION', '1.0.0');
define('OVERCMS_DIR', __DIR__);
define('OVERCMS_URL', plugins_url('', __FILE__));
define('OVERCMS_PANEL_DIST', __DIR__ . '/panel/dist');
define('OVERCMS_PANEL_DIST_URL', OVERCMS_URL . '/panel/dist');
define('OVERCMS_GITHUB_REPO', 'jurfader/OverCMS-2.0');

// PSR-4 autoloader for OverCMS\Core\
spl_autoload_register(static function (string $class): void {
    $prefix = 'OverCMS\\Core\\';
    $base   = __DIR__ . '/includes/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file     = $base . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

// Bootstrap modules
add_action('plugins_loaded', static function (): void {
    \OverCMS\Core\Hardening::register();
    \OverCMS\Core\PerformanceOptimizer::register();
    \OverCMS\Core\AdminCleanup::register();
    \OverCMS\Core\PanelLoader::register();
    \OverCMS\Core\Redirects::register();
    \OverCMS\Core\Updater::register();
    \OverCMS\Core\Rest\RestRouter::register();
}, 5);
