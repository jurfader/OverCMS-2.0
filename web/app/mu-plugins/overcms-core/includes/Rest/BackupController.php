<?php

namespace OverCMS\Core\Rest;

/**
 * Backupy: dump MySQL + tar wp-content/uploads do /var/backups/overcms/{domain}/.
 *
 * Endpointy:
 *   GET    /overcms/v1/backups                       — lista plików
 *   POST   /overcms/v1/backups                       — utwórz nowy backup
 *   GET    /overcms/v1/backups/:filename/download    — strumieniowo pobierz
 *   DELETE /overcms/v1/backups/:filename             — usuń
 */
final class BackupController
{
    private const BACKUP_DIR = '/var/backups/overcms';

    public static function register(): void
    {
        $perm = [RestRouter::class, 'canManage'];
        $ns   = RestRouter::NAMESPACE;

        register_rest_route($ns, '/backups', [
            ['methods' => \WP_REST_Server::READABLE,  'callback' => [self::class, 'index'],  'permission_callback' => $perm],
            ['methods' => \WP_REST_Server::CREATABLE, 'callback' => [self::class, 'create'], 'permission_callback' => $perm],
        ]);

        register_rest_route($ns, '/backups/(?P<filename>[A-Za-z0-9_.-]+)/download', [
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => [self::class, 'download'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/backups/(?P<filename>[A-Za-z0-9_.-]+)/restore', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'restore'],
            'permission_callback' => $perm,
        ]);

        register_rest_route($ns, '/backups/(?P<filename>[A-Za-z0-9_.-]+)', [
            'methods'             => \WP_REST_Server::DELETABLE,
            'callback'            => [self::class, 'delete'],
            'permission_callback' => $perm,
        ]);
    }

    private static function siteDir(): string
    {
        $domain = parse_url(home_url('/'), PHP_URL_HOST) ?: 'site';
        $domain = preg_replace('/[^a-z0-9.-]/i', '', $domain);
        return self::BACKUP_DIR . '/' . $domain;
    }

    public static function index(): \WP_REST_Response
    {
        $dir = self::siteDir();
        if (!is_dir($dir)) {
            return new \WP_REST_Response(['items' => [], 'totalSizeMb' => 0]);
        }

        $files = glob($dir . '/*.tar.gz') ?: [];
        $items = [];
        $total = 0;
        foreach ($files as $file) {
            $size = filesize($file) ?: 0;
            $total += $size;
            $items[] = [
                'filename'  => basename($file),
                'sizeMb'    => round($size / 1048576, 2),
                'createdAt' => date('c', filemtime($file) ?: time()),
            ];
        }

        // Najnowsze pierwsze
        usort($items, static fn ($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

        return new \WP_REST_Response([
            'items'       => $items,
            'totalSizeMb' => round($total / 1048576, 2),
            'dir'         => $dir,
        ]);
    }

    public static function create(): \WP_REST_Response
    {
        $dir = self::siteDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return new \WP_REST_Response(['error' => 'Cannot create backup directory: ' . $dir], 500);
        }

        $timestamp = date('Y-m-d_His');
        $tmpDir    = '/tmp/overcms-backup-' . wp_generate_password(8, false);
        if (!mkdir($tmpDir, 0700, true)) {
            return new \WP_REST_Response(['error' => 'Cannot create temp dir'], 500);
        }

        $errors = [];

        // 1. Dump MySQL
        $sqlFile = $tmpDir . '/database.sql';
        $cmd = sprintf(
            'mysqldump --no-tablespaces --single-transaction --quick -h %s -u %s -p%s %s > %s 2>&1',
            escapeshellarg(DB_HOST),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_PASSWORD),
            escapeshellarg(DB_NAME),
            escapeshellarg($sqlFile)
        );
        exec($cmd, $output, $code);
        if ($code !== 0 || !file_exists($sqlFile)) {
            $errors[] = 'mysqldump exit ' . $code . ': ' . implode("\n", $output);
            self::rrmdir($tmpDir);
            return new \WP_REST_Response(['error' => implode('; ', $errors)], 500);
        }

        // 2. Spakuj sql + uploads + themes + plugins do tar.gz
        $archive    = $dir . '/backup-' . $timestamp . '.tar.gz';
        $contentDir = WP_CONTENT_DIR;

        // Zbierz istniejące foldery do spakowania
        $folders = [];
        foreach (['uploads', 'themes', 'plugins'] as $f) {
            if (is_dir($contentDir . '/' . $f)) {
                $folders[] = $f;
            }
        }

        // tar -czf archive.tar.gz -C tmpDir database.sql -C wp-content uploads themes plugins
        $tarCmd = sprintf(
            'tar -czf %s -C %s database.sql -C %s %s 2>&1',
            escapeshellarg($archive),
            escapeshellarg($tmpDir),
            escapeshellarg($contentDir),
            implode(' ', array_map('escapeshellarg', $folders))
        );
        exec($tarCmd, $tarOut, $tarCode);
        self::rrmdir($tmpDir);

        if ($tarCode !== 0 || !file_exists($archive)) {
            return new \WP_REST_Response([
                'error' => 'tar exit ' . $tarCode . ': ' . implode("\n", $tarOut),
            ], 500);
        }

        return new \WP_REST_Response([
            'success'  => true,
            'filename' => basename($archive),
            'sizeMb'   => round((filesize($archive) ?: 0) / 1048576, 2),
        ]);
    }

    public static function download(\WP_REST_Request $req): \WP_REST_Response
    {
        $filename = basename((string) $req['filename']);
        if (!preg_match('/\.tar\.gz$/', $filename)) {
            return new \WP_REST_Response(['error' => 'Invalid filename'], 400);
        }
        $path = self::siteDir() . '/' . $filename;
        if (!file_exists($path)) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }

        // Streamuj plik bezpośrednio (bez wrapping w JSON)
        nocache_headers();
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public static function restore(\WP_REST_Request $req): \WP_REST_Response
    {
        @set_time_limit(300);
        @ignore_user_abort(true);

        $filename = basename((string) $req['filename']);
        if (!preg_match('/\.tar\.gz$/', $filename)) {
            return new \WP_REST_Response(['error' => 'Invalid filename'], 400);
        }
        $path = self::siteDir() . '/' . $filename;
        if (!file_exists($path)) {
            return new \WP_REST_Response(['error' => 'Backup nie istnieje'], 404);
        }

        $tmpDir = '/tmp/overcms-restore-' . wp_generate_password(8, false);
        if (!mkdir($tmpDir, 0700, true)) {
            return new \WP_REST_Response(['error' => 'Cannot create temp dir'], 500);
        }

        // Wypakuj archiwum
        $extractCmd = sprintf('tar -xzf %s -C %s 2>&1', escapeshellarg($path), escapeshellarg($tmpDir));
        exec($extractCmd, $extOut, $extCode);
        if ($extCode !== 0) {
            self::rrmdir($tmpDir);
            return new \WP_REST_Response(['error' => 'tar extract failed: ' . implode("\n", $extOut)], 500);
        }

        // 1. Przywróć bazę danych
        $sqlFile = $tmpDir . '/database.sql';
        if (file_exists($sqlFile)) {
            // Uwaga: --no-tablespaces to flaga mysqldump, nie mysql clienta — nie używamy jej tu
            $restoreCmd = sprintf(
                'mysql -h %s -u %s -p%s %s < %s 2>&1',
                escapeshellarg(DB_HOST),
                escapeshellarg(DB_USER),
                escapeshellarg(DB_PASSWORD),
                escapeshellarg(DB_NAME),
                escapeshellarg($sqlFile)
            );
            exec($restoreCmd, $dbOut, $dbCode);
            if ($dbCode !== 0) {
                self::rrmdir($tmpDir);
                return new \WP_REST_Response([
                    'error' => 'mysql restore failed (exit ' . $dbCode . '): ' . implode("\n", $dbOut),
                ], 500);
            }
        }

        // 2. Przywróć pliki (uploads, themes, plugins)
        $contentDir = WP_CONTENT_DIR;
        foreach (['uploads', 'themes', 'plugins'] as $folder) {
            $src = $tmpDir . '/' . $folder;
            if (!is_dir($src)) {
                continue;
            }
            $dst = $contentDir . '/' . $folder;
            // rsync-like: kopiuj zawartość src do dst
            $cpCmd = sprintf('cp -a %s/. %s/ 2>&1', escapeshellarg($src), escapeshellarg($dst));
            exec($cpCmd, $cpOut, $cpCode);
        }

        self::rrmdir($tmpDir);

        return new \WP_REST_Response(['success' => true]);
    }

    public static function delete(\WP_REST_Request $req): \WP_REST_Response
    {
        $filename = basename((string) $req['filename']);
        if (!preg_match('/\.tar\.gz$/', $filename)) {
            return new \WP_REST_Response(['error' => 'Invalid filename'], 400);
        }
        $path = self::siteDir() . '/' . $filename;
        if (!file_exists($path)) {
            return new \WP_REST_Response(['error' => 'Not found'], 404);
        }
        if (!unlink($path)) {
            return new \WP_REST_Response(['error' => 'Delete failed'], 500);
        }
        return new \WP_REST_Response(['success' => true]);
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $p = $dir . '/' . $item;
            is_dir($p) ? self::rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
