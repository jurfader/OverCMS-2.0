<?php

namespace OverCMS\Core\Rest;

/**
 * Backupy: dump MySQL + tar wp-content/(uploads|themes|plugins) do /var/backups/overcms/{domain}/.
 *
 * Endpointy:
 *   GET    /overcms/v1/backups                       — lista plików
 *   POST   /overcms/v1/backups                       — utwórz nowy backup
 *   GET    /overcms/v1/backups/:filename/download    — strumieniowo pobierz
 *   POST   /overcms/v1/backups/:filename/restore     — przywróć
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

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private static function siteDir(): string
    {
        $domain = parse_url(home_url('/'), PHP_URL_HOST) ?: 'site';
        $domain = preg_replace('/[^a-z0-9.-]/i', '', $domain);
        return self::BACKUP_DIR . '/' . $domain;
    }

    /**
     * Rozkłada DB_HOST na host + port (obsługa "host:port" i socketów).
     * @return array{host: string, port: string}
     */
    private static function dbHostPort(): array
    {
        $raw = DB_HOST;
        // Sokety (np. "localhost:/var/run/mysqld/mysqld.sock") — bierzemy localhost
        if (str_contains($raw, ':/')) {
            return ['host' => 'localhost', 'port' => '3306'];
        }
        if (str_contains($raw, ':')) {
            [$host, $port] = explode(':', $raw, 2);
            return ['host' => $host ?: 'localhost', 'port' => $port ?: '3306'];
        }
        return ['host' => $raw ?: 'localhost', 'port' => '3306'];
    }

    /**
     * Buduje tablicę env dla exec() z MYSQL_PWD zamiast -p w komendzie.
     * Unikamy problemów z hasłami zawierającymi znaki specjalne shella.
     */
    private static function mysqlEnv(): string
    {
        // Przekazujemy hasło przez zmienną środowiskową — bezpieczniejsze niż -p w shellu
        return 'MYSQL_PWD=' . escapeshellarg(DB_PASSWORD) . ' ';
    }

    /** Szuka binarium mysql/mysqldump w znanych lokalizacjach */
    private static function findBin(string $name): string
    {
        $paths = [
            '/usr/bin/' . $name,
            '/usr/local/bin/' . $name,
            '/usr/mysql/bin/' . $name,
        ];
        foreach ($paths as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        // Fallback — niech shell szuka
        return $name;
    }

    private static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $p = $dir . '/' . $item;
            is_dir($p) ? self::rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    // -------------------------------------------------------------------------
    // Endpoints
    // -------------------------------------------------------------------------

    public static function index(): \WP_REST_Response
    {
        $dir = self::siteDir();
        if (!is_dir($dir)) {
            return new \WP_REST_Response(['items' => [], 'totalSizeMb' => 0, 'dir' => $dir]);
        }

        $files = glob($dir . '/*.tar.gz') ?: [];
        $items = [];
        $total = 0;
        foreach ($files as $file) {
            $size  = filesize($file) ?: 0;
            $total += $size;
            $items[] = [
                'filename'  => basename($file),
                'sizeMb'    => round($size / 1048576, 2),
                'createdAt' => date('c', filemtime($file) ?: time()),
            ];
        }

        usort($items, static fn ($a, $b) => strcmp($b['createdAt'], $a['createdAt']));

        return new \WP_REST_Response([
            'items'       => $items,
            'totalSizeMb' => round($total / 1048576, 2),
            'dir'         => $dir,
        ]);
    }

    public static function create(): \WP_REST_Response
    {
        @set_time_limit(300);
        @ignore_user_abort(true);

        $dir = self::siteDir();
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return new \WP_REST_Response(['error' => 'Cannot create backup directory: ' . $dir], 500);
        }

        $timestamp = date('Y-m-d_His');
        $tmpDir    = '/tmp/overcms-backup-' . wp_generate_password(8, false);
        if (!mkdir($tmpDir, 0700, true)) {
            return new \WP_REST_Response(['error' => 'Cannot create temp dir'], 500);
        }

        // 1. Dump bazy danych
        $sqlFile  = $tmpDir . '/database.sql';
        $hp       = self::dbHostPort();
        $dumpBin  = self::findBin('mysqldump');

        $dumpCmd = self::mysqlEnv() . sprintf(
            '%s --no-tablespaces --single-transaction --quick -h %s -P %s -u %s %s > %s 2>&1',
            escapeshellarg($dumpBin),
            escapeshellarg($hp['host']),
            escapeshellarg($hp['port']),
            escapeshellarg(DB_USER),
            escapeshellarg(DB_NAME),
            escapeshellarg($sqlFile)
        );

        exec($dumpCmd, $dumpOut, $dumpCode);
        if ($dumpCode !== 0 || !file_exists($sqlFile)) {
            self::rrmdir($tmpDir);
            return new \WP_REST_Response([
                'error' => 'mysqldump failed (exit ' . $dumpCode . '): ' . implode(' | ', $dumpOut),
            ], 500);
        }

        // 2. Spakuj sql + uploads + themes + plugins
        $archive    = $dir . '/backup-' . $timestamp . '.tar.gz';
        $contentDir = WP_CONTENT_DIR;

        $folders = [];
        foreach (['uploads', 'themes', 'plugins'] as $f) {
            if (is_dir($contentDir . '/' . $f)) {
                $folders[] = $f;
            }
        }

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
                'error' => 'tar failed (exit ' . $tarCode . '): ' . implode(' | ', $tarOut),
            ], 500);
        }

        return new \WP_REST_Response([
            'success'  => true,
            'filename' => basename($archive),
            'sizeMb'   => round((filesize($archive) ?: 0) / 1048576, 2),
        ]);
    }

    public static function download(\WP_REST_Request $req): void
    {
        $filename = basename((string) $req['filename']);
        if (!preg_match('/\.tar\.gz$/', $filename)) {
            status_header(400);
            exit('Invalid filename');
        }
        $path = self::siteDir() . '/' . $filename;
        if (!file_exists($path)) {
            status_header(404);
            exit('Not found');
        }

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
            return new \WP_REST_Response(['error' => 'Backup nie istnieje: ' . $filename], 404);
        }

        $tmpDir = '/tmp/overcms-restore-' . wp_generate_password(8, false);
        if (!mkdir($tmpDir, 0700, true)) {
            return new \WP_REST_Response(['error' => 'Cannot create temp dir'], 500);
        }

        // 1. Wypakuj archiwum
        $extractCmd = sprintf('tar -xzf %s -C %s 2>&1', escapeshellarg($path), escapeshellarg($tmpDir));
        exec($extractCmd, $extOut, $extCode);
        if ($extCode !== 0) {
            self::rrmdir($tmpDir);
            return new \WP_REST_Response([
                'error' => 'tar extract failed (exit ' . $extCode . '): ' . implode(' | ', $extOut),
            ], 500);
        }

        // 2. Przywróć bazę danych (przez PDO — bez shellowych problemów z hasłem)
        $sqlFile = $tmpDir . '/database.sql';
        if (file_exists($sqlFile)) {
            $hp = self::dbHostPort();
            try {
                $dsn = 'mysql:host=' . $hp['host'] . ';port=' . $hp['port'] . ';dbname=' . DB_NAME . ';charset=utf8mb4';
                $pdo = new \PDO($dsn, DB_USER, DB_PASSWORD, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                ]);

                // Czytaj i wykonuj plik SQL statement po statement
                $sql = file_get_contents($sqlFile);
                if ($sql === false) {
                    self::rrmdir($tmpDir);
                    return new \WP_REST_Response(['error' => 'Cannot read database.sql'], 500);
                }

                // Wyłącz sprawdzanie kluczy obcych na czas importu
                $pdo->exec('SET FOREIGN_KEY_CHECKS=0; SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO"; SET time_zone="+00:00";');

                // Podziel SQL na instrukcje (obsługa DELIMITER $$, procedur itp. jest pominięta — standardowy dump WP jest prosty)
                $statements = self::splitSql($sql);
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if ($stmt === '') {
                        continue;
                    }
                    $pdo->exec($stmt);
                }

                $pdo->exec('SET FOREIGN_KEY_CHECKS=1;');
            } catch (\PDOException $e) {
                self::rrmdir($tmpDir);
                return new \WP_REST_Response(['error' => 'DB restore failed: ' . $e->getMessage()], 500);
            }
        }

        // 3. Przywróć pliki (uploads, themes, plugins)
        $contentDir = WP_CONTENT_DIR;
        foreach (['uploads', 'themes', 'plugins'] as $folder) {
            $src = $tmpDir . '/' . $folder;
            if (!is_dir($src)) {
                continue;
            }
            $dst = $contentDir . '/' . $folder;
            $cpCmd = sprintf('cp -a %s/. %s/ 2>&1', escapeshellarg($src), escapeshellarg($dst));
            exec($cpCmd);
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

    // -------------------------------------------------------------------------
    // SQL splitter — dzieli dump na pojedyncze instrukcje bez regexa na całym pliku
    // -------------------------------------------------------------------------

    /**
     * @return string[]
     */
    private static function splitSql(string $sql): array
    {
        $statements = [];
        $current    = '';
        $inString   = false;
        $strChar    = '';
        $len        = strlen($sql);

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];

            // Obsługa komentarzy liniowych -- i #
            if (!$inString && $i + 1 < $len && $ch === '-' && $sql[$i + 1] === '-') {
                $end = strpos($sql, "\n", $i);
                $i   = $end === false ? $len - 1 : $end;
                continue;
            }
            if (!$inString && $ch === '#') {
                $end = strpos($sql, "\n", $i);
                $i   = $end === false ? $len - 1 : $end;
                continue;
            }
            // Obsługa komentarzy blokowych /* */
            if (!$inString && $ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $i   = $end === false ? $len - 1 : $end + 1;
                continue;
            }

            // Wejście/wyjście z cudzysłowu
            if ($inString) {
                if ($ch === '\\') {
                    $current .= $ch;
                    $i++;
                    if ($i < $len) {
                        $current .= $sql[$i];
                    }
                    continue;
                }
                if ($ch === $strChar) {
                    $inString = false;
                }
                $current .= $ch;
                continue;
            }
            if ($ch === '"' || $ch === "'") {
                $inString = true;
                $strChar  = $ch;
                $current .= $ch;
                continue;
            }

            // Koniec instrukcji
            if ($ch === ';') {
                $statements[] = $current;
                $current      = '';
                continue;
            }

            $current .= $ch;
        }

        if (trim($current) !== '') {
            $statements[] = $current;
        }

        return $statements;
    }
}
