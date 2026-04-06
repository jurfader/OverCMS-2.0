<?php

namespace OverCMS\Core;

/**
 * Sprawdza GitHub Releases (overmedia/overcms) raz na dobę i pozwala
 * jednym kliknięciem zaktualizować mu-plugin OverCMS Core.
 *
 * Ustawienia żyją w wp_options ('overcms_*'), więc aktualizacja podmieniająca
 * pliki nie traci konfiguracji.
 */
final class Updater
{
    private const TRANSIENT = 'overcms_latest_release';
    private const TTL = DAY_IN_SECONDS;

    public static function register(): void
    {
        add_action('overcms_check_updates', [self::class, 'checkNow']);

        if (!wp_next_scheduled('overcms_check_updates')) {
            wp_schedule_event(time() + 300, 'daily', 'overcms_check_updates');
        }

        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route('overcms/v1', '/updates/check', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'restCheck'],
            'permission_callback' => static fn () => current_user_can('manage_options'),
        ]);

        register_rest_route('overcms/v1', '/updates/install', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'restInstall'],
            'permission_callback' => static fn () => current_user_can('manage_options'),
        ]);
    }

    public static function checkNow(): ?array
    {
        $url = sprintf('https://api.github.com/repos/%s/releases/latest', OVERCMS_GITHUB_REPO);
        $res = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => [
                'Accept'     => 'application/vnd.github+json',
                'User-Agent' => 'OverCMS-Updater/' . OVERCMS_VERSION,
            ],
        ]);

        if (is_wp_error($res) || wp_remote_retrieve_response_code($res) !== 200) {
            return null;
        }

        $body = json_decode((string) wp_remote_retrieve_body($res), true);
        if (!is_array($body) || empty($body['tag_name'])) {
            return null;
        }

        $version = ltrim((string) $body['tag_name'], 'v');
        $asset   = self::pickZipAsset($body['assets'] ?? []);

        $payload = [
            'version'     => $version,
            'currentVersion' => OVERCMS_VERSION,
            'isNewer'     => version_compare($version, OVERCMS_VERSION, '>'),
            'downloadUrl' => $asset['browser_download_url'] ?? null,
            'size'        => $asset['size'] ?? null,
            'publishedAt' => $body['published_at'] ?? null,
            'changelog'   => $body['body'] ?? '',
            'checkedAt'   => time(),
        ];

        set_transient(self::TRANSIENT, $payload, self::TTL);
        return $payload;
    }

    public static function restCheck(\WP_REST_Request $req): \WP_REST_Response
    {
        $force  = (bool) $req->get_param('force');
        $cached = $force ? null : get_transient(self::TRANSIENT);
        $data   = is_array($cached) ? $cached : self::checkNow();

        return new \WP_REST_Response($data ?: ['error' => 'Cannot reach GitHub'], $data ? 200 : 502);
    }

    public static function restInstall(\WP_REST_Request $req): \WP_REST_Response
    {
        $cached = get_transient(self::TRANSIENT);
        if (!is_array($cached) || empty($cached['downloadUrl'])) {
            return new \WP_REST_Response(['error' => 'No update available'], 400);
        }

        $tmp = download_url($cached['downloadUrl'], 60);
        if (is_wp_error($tmp)) {
            return new \WP_REST_Response(['error' => $tmp->get_error_message()], 502);
        }

        if (!class_exists('WP_Filesystem_Base')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }

        $unzipDir = trailingslashit(get_temp_dir()) . 'overcms-update-' . wp_generate_password(8, false);
        wp_mkdir_p($unzipDir);
        $unzip = unzip_file($tmp, $unzipDir);
        @unlink($tmp);

        if (is_wp_error($unzip)) {
            return new \WP_REST_Response(['error' => $unzip->get_error_message()], 500);
        }

        // Znajdź katalog mu-plugins/overcms-core w wypakowanym ZIP
        $source = self::findCoreDir($unzipDir);
        if (!$source) {
            return new \WP_REST_Response(['error' => 'overcms-core not found in archive'], 500);
        }

        // Atomowa podmiana: rename old → backup, copy new, usuń backup
        $target = OVERCMS_DIR;
        $backup = $target . '.backup-' . time();

        if (!@rename($target, $backup)) {
            return new \WP_REST_Response(['error' => 'Cannot move old version aside (permissions?)'], 500);
        }

        if (!self::recursiveCopy($source, $target)) {
            // Rollback
            @rename($backup, $target);
            return new \WP_REST_Response(['error' => 'Copy failed, rolled back'], 500);
        }

        self::deleteTree($backup);
        self::deleteTree($unzipDir);
        delete_transient(self::TRANSIENT);

        return new \WP_REST_Response([
            'success'    => true,
            'newVersion' => $cached['version'],
        ]);
    }

    private static function pickZipAsset(array $assets): ?array
    {
        foreach ($assets as $asset) {
            if (str_ends_with((string) ($asset['name'] ?? ''), '.zip')) {
                return $asset;
            }
        }
        return null;
    }

    private static function findCoreDir(string $root): ?string
    {
        $direct = $root . '/overcms-core';
        if (is_dir($direct) && file_exists($direct . '/overcms-core.php')) {
            return $direct;
        }
        // Często ZIP rozpakuje się do podkatalogu — szukaj o jeden poziom głębiej
        $items = glob($root . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($items as $dir) {
            $candidate = $dir . '/overcms-core';
            if (is_dir($candidate) && file_exists($candidate . '/overcms-core.php')) {
                return $candidate;
            }
        }
        return null;
    }

    private static function recursiveCopy(string $src, string $dst): bool
    {
        if (!is_dir($src)) {
            return false;
        }
        if (!is_dir($dst) && !mkdir($dst, 0755, true) && !is_dir($dst)) {
            return false;
        }
        $items = scandir($src) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $from = $src . '/' . $item;
            $to   = $dst . '/' . $item;
            if (is_dir($from)) {
                if (!self::recursiveCopy($from, $to)) {
                    return false;
                }
            } elseif (!copy($from, $to)) {
                return false;
            }
        }
        return true;
    }

    private static function deleteTree(string $path): void
    {
        if (!file_exists($path)) {
            return;
        }
        if (is_file($path) || is_link($path)) {
            @unlink($path);
            return;
        }
        $items = scandir($path) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            self::deleteTree($path . '/' . $item);
        }
        @rmdir($path);
    }
}
