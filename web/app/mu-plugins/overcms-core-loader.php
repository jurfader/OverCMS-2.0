<?php
/**
 * Plugin Name: OverCMS Core Loader
 * Description: Loads the OverCMS Core mu-plugin from a subdirectory (mu-plugins are not auto-loaded recursively).
 * Version:     1.0.0
 * Author:      OVERMEDIA
 */

if (!defined('ABSPATH')) {
    exit;
}

if (file_exists(__DIR__ . '/overcms-core/overcms-core.php')) {
    require_once __DIR__ . '/overcms-core/overcms-core.php';
}
