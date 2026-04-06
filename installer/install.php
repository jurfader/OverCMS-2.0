<?php
/**
 * OverCMS — kreator instalacji przez przeglądarkę.
 *
 * Uruchom: skopiuj zawartość ZIP-a z release na serwer, otwórz w przeglądarce
 *   https://twoja-domena.pl/install.php
 * Po zakończeniu plik usuwa się sam.
 *
 * Wymaga: PHP 8.2+, Composer w PATH lub composer.phar w katalogu projektu.
 */

declare(strict_types=1);

session_start();
$root = dirname(__DIR__);
chdir($root);

$step = (int) ($_GET['step'] ?? 1);
$errors = [];

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function checkRequirements(): array
{
    return [
        'PHP 8.2+'        => PHP_VERSION_ID >= 80200,
        'ext: pdo_mysql'  => extension_loaded('pdo_mysql') || extension_loaded('mysqli'),
        'ext: mbstring'   => extension_loaded('mbstring'),
        'ext: gd'         => extension_loaded('gd'),
        'ext: zip'        => extension_loaded('zip'),
        'ext: curl'       => extension_loaded('curl'),
        'ext: openssl'    => extension_loaded('openssl'),
        'web/ writable'   => is_writable(__DIR__ . '/../web'),
        '..  writable'    => is_writable(dirname(__DIR__)),
    ];
}

if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['db'] = [
        'host' => trim($_POST['db_host'] ?? 'localhost'),
        'name' => trim($_POST['db_name'] ?? ''),
        'user' => trim($_POST['db_user'] ?? ''),
        'pass' => $_POST['db_pass'] ?? '',
    ];
    try {
        $pdo = new PDO(
            "mysql:host={$_SESSION['db']['host']};dbname={$_SESSION['db']['name']};charset=utf8mb4",
            $_SESSION['db']['user'],
            $_SESSION['db']['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        header('Location: install.php?step=3');
        exit;
    } catch (PDOException $e) {
        $errors[] = 'Błąd połączenia z bazą: ' . $e->getMessage();
    }
}

if ($step === 3 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['admin'] = [
        'user'   => trim($_POST['admin_user'] ?? 'admin'),
        'email'  => trim($_POST['admin_email'] ?? ''),
        'pass'   => $_POST['admin_pass'] ?? '',
        'domain' => trim($_POST['domain'] ?? $_SERVER['HTTP_HOST']),
    ];
    if (!filter_var($_SESSION['admin']['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Niepoprawny email.';
    } elseif (strlen($_SESSION['admin']['pass']) < 8) {
        $errors[] = 'Hasło min. 8 znaków.';
    } else {
        header('Location: install.php?step=4');
        exit;
    }
}

if ($step === 4) {
    // Wykonaj instalację
    $db = $_SESSION['db'] ?? null;
    $ad = $_SESSION['admin'] ?? null;
    if (!$db || !$ad) {
        $errors[] = 'Brak danych konfiguracji — zacznij od kroku 1.';
    } else {
        $script = sprintf(
            'bash %s --domain=%s --db-host=%s --db-name=%s --db-user=%s --db-pass=%s --admin-user=%s --admin-email=%s --admin-pass=%s --non-interactive 2>&1',
            escapeshellarg(__DIR__ . '/install.sh'),
            escapeshellarg($ad['domain']),
            escapeshellarg($db['host']),
            escapeshellarg($db['name']),
            escapeshellarg($db['user']),
            escapeshellarg($db['pass']),
            escapeshellarg($ad['user']),
            escapeshellarg($ad['email']),
            escapeshellarg($ad['pass'])
        );
        $output = [];
        $code = 0;
        exec($script, $output, $code);
        $installLog = implode("\n", $output);
        if ($code === 0) {
            // Self-destruct
            @unlink(__FILE__);
            session_destroy();
        } else {
            $errors[] = 'Instalator zakończył się błędem (kod ' . $code . ').';
        }
    }
}

?><!doctype html>
<html lang="pl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>OverCMS — Instalator</title>
<style>
:root {
  --bg: #0A0B14; --surface: #111421; --border: rgba(255,255,255,0.08);
  --fg: #F8FAFC; --muted: #8B9CC3; --primary: #E91E8C; --secondary: #9333EA;
}
* { box-sizing: border-box; }
body { margin: 0; min-height: 100vh; font-family: -apple-system, system-ui, sans-serif;
       background: var(--bg) radial-gradient(ellipse 60% 40% at 10% 0%, rgba(233,30,140,0.08), transparent 50%),
                              radial-gradient(ellipse 60% 40% at 90% 100%, rgba(147,51,234,0.08), transparent 50%);
       background-attachment: fixed; color: var(--fg);
       display: flex; align-items: center; justify-content: center; padding: 40px 20px; }
.card { background: rgba(17,20,33,0.8); backdrop-filter: blur(16px) saturate(150%);
        border: 1px solid var(--border); border-radius: 12px;
        box-shadow: 0 8px 40px rgba(0,0,0,0.5); padding: 32px; max-width: 520px; width: 100%; }
h1 { margin: 0 0 8px; font-size: 24px; background: linear-gradient(135deg, #E91E8C, #9333EA); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
.subtitle { color: var(--muted); font-size: 14px; margin-bottom: 24px; }
.steps { display: flex; gap: 8px; margin-bottom: 24px; }
.step { flex: 1; height: 4px; border-radius: 99px; background: rgba(255,255,255,0.06); }
.step.active { background: linear-gradient(135deg, #E91E8C, #9333EA); }
label { display: block; font-size: 12px; color: var(--muted); margin: 12px 0 6px; }
input { width: 100%; height: 38px; padding: 0 12px; background: var(--surface); color: var(--fg);
        border: 1px solid rgba(255,255,255,0.12); border-radius: 8px; font-size: 14px; }
input:focus { outline: none; border-color: var(--primary); }
button { margin-top: 24px; width: 100%; height: 42px; border: 0; border-radius: 8px;
         background: linear-gradient(135deg, #E91E8C, #9333EA); color: #fff; font-size: 14px; font-weight: 600;
         cursor: pointer; box-shadow: 0 4px 24px rgba(233,30,140,0.25); }
.req { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid var(--border); font-size: 14px; }
.req:last-child { border: 0; }
.ok { color: #22C55E; } .fail { color: #EF4444; }
.error { background: rgba(239,68,68,0.1); color: #EF4444; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
.success { background: rgba(34,197,94,0.1); color: #22C55E; padding: 16px; border-radius: 8px; font-size: 14px; }
pre { background: rgba(0,0,0,0.4); padding: 12px; border-radius: 8px; overflow: auto; font-size: 11px; max-height: 240px; }
</style>
</head>
<body>
<div class="card">
  <h1>OverCMS</h1>
  <p class="subtitle">Krok <?= h((string) $step) ?> z 4 — instalator</p>
  <div class="steps">
    <?php for ($i = 1; $i <= 4; $i++): ?>
      <div class="step <?= $i <= $step ? 'active' : '' ?>"></div>
    <?php endfor; ?>
  </div>

  <?php foreach ($errors as $err): ?>
    <div class="error"><?= h($err) ?></div>
  <?php endforeach; ?>

  <?php if ($step === 1): ?>
    <h2 style="font-size:16px;margin:0 0 12px">Wymagania serwera</h2>
    <?php foreach (checkRequirements() as $name => $ok): ?>
      <div class="req">
        <span><?= h($name) ?></span>
        <span class="<?= $ok ? 'ok' : 'fail' ?>"><?= $ok ? '✓ OK' : '✗ Brak' ?></span>
      </div>
    <?php endforeach; ?>
    <form method="get" action="install.php">
      <input type="hidden" name="step" value="2">
      <button type="submit">Dalej</button>
    </form>

  <?php elseif ($step === 2): ?>
    <form method="post" action="install.php?step=2">
      <label>Host bazy danych</label>
      <input name="db_host" value="localhost" required>
      <label>Nazwa bazy</label>
      <input name="db_name" placeholder="overcms" required>
      <label>Użytkownik</label>
      <input name="db_user" required>
      <label>Hasło</label>
      <input name="db_pass" type="password" required>
      <button type="submit">Połącz z bazą</button>
    </form>

  <?php elseif ($step === 3): ?>
    <form method="post" action="install.php?step=3">
      <label>Domena (bez https://)</label>
      <input name="domain" value="<?= h($_SERVER['HTTP_HOST']) ?>" required>
      <label>Login administratora</label>
      <input name="admin_user" value="admin" required>
      <label>Email administratora</label>
      <input name="admin_email" type="email" required>
      <label>Hasło (min. 8 znaków)</label>
      <input name="admin_pass" type="password" minlength="8" required>
      <button type="submit">Zainstaluj</button>
    </form>

  <?php elseif ($step === 4): ?>
    <?php if (empty($errors)): ?>
      <div class="success">
        <strong>OverCMS zainstalowany!</strong><br>
        Plik install.php został usunięty. Zaloguj się do panelu:<br>
        <a href="/wp/wp-admin/admin.php?page=overcms" style="color:#E91E8C">/wp/wp-admin/admin.php?page=overcms</a>
      </div>
    <?php else: ?>
      <pre><?= h($installLog ?? '') ?></pre>
    <?php endif; ?>

  <?php endif; ?>
</div>
</body>
</html>
