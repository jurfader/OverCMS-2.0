# OverCMS — instalacja na serwerze

Krótka instrukcja wgrania paczki dystrybucyjnej `overcms-1.0.0.zip` na serwer Ubuntu i uruchomienia panelu.

## 1. Wymagania serwera

| Komponent | Wersja |
|---|---|
| PHP CLI + FPM | 8.3+ z rozszerzeniami: `mysqli`, `mbstring`, `gd`, `intl`, `zip`, `curl`, `xml`, `openssl` |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Nginx lub Apache | dowolny |
| Opcjonalnie | `redis-server` + `php-redis` (object cache), `composer` (jeśli paczka bez `vendor/`), `wp-cli` (instalator pobierze go sam jeśli brak) |

Instalacja zależności na czystym Ubuntu:

```bash
sudo apt update
sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring \
                    php8.3-xml php8.3-zip php8.3-gd php8.3-intl php8.3-curl \
                    mariadb-server nginx unzip
# Opcjonalnie:
sudo apt install -y redis-server php8.3-redis
```

## 2. Wgranie paczki

```bash
# Utwórz katalog i wgraj overcms-1.0.0.zip (np. przez scp/sftp)
sudo mkdir -p /var/www/overcms
sudo chown -R $USER:$USER /var/www/overcms
cd /var/www/overcms
unzip ~/overcms-1.0.0.zip
# Po rozpakowaniu masz strukturę: /var/www/overcms/web/, /var/www/overcms/installer/, …
```

## 3. Utworzenie bazy danych

```bash
sudo mysql -u root <<SQL
CREATE DATABASE overcms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'overcms'@'localhost' IDENTIFIED BY 'TWOJE_HASLO';
GRANT ALL PRIVILEGES ON overcms.* TO 'overcms'@'localhost';
FLUSH PRIVILEGES;
SQL
```

## 4. Uruchomienie instalatora

### Wariant A — CLI (zalecane)

```bash
cd /var/www/overcms
bash installer/install.sh \
  --domain=twoja-domena.pl \
  --db-name=overcms \
  --db-user=overcms \
  --db-pass=TWOJE_HASLO \
  --admin-user=admin \
  --admin-email=ty@twoja-domena.pl \
  --admin-pass=SilneHaslo123 \
  --non-interactive
```

Lub interaktywnie:

```bash
bash installer/install.sh
```

Instalator wykona: `composer install` (jeśli `vendor/` nie ma w paczce), wygeneruje `.env` z saltami, zainstaluje WordPress, aktywuje pluginy (Rank Math, Cache Enabler, opcjonalnie Redis Object Cache), utworzy stronę startową.

Po sukcesie wypisze URL panelu i dane logowania.

### Wariant B — kreator przez przeglądarkę

Skopiuj `installer/install.php` do `web/install.php`, otwórz `https://twoja-domena.pl/install.php` i przejdź 4 kroki. Plik usuwa się sam po zakończeniu.

## 5. Konfiguracja Nginx

`/etc/nginx/sites-available/overcms`:

```nginx
server {
    listen 80;
    server_name twoja-domena.pl;
    root /var/www/overcms/web;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Hardening
    location ~ /\.(env|git) { deny all; }
    location ~ /vendor/     { deny all; }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/overcms /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Następnie wystaw HTTPS przez certbot:

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d twoja-domena.pl
```

## 6. Pierwsze logowanie

Wejdź na `https://twoja-domena.pl/wp/wp-admin/` — automatycznie przekieruje na panel OverCMS pod `?page=overcms`.

## 7. Instalacja Divi (page builder)

W panelu kliknij **Moduły → Marketplace WordPress.org**, lub przez WP-CLI:

```bash
cd /var/www/overcms
php /tmp/wp-cli.phar theme install /sciezka/do/Divi.zip --activate --path=web/wp
```

(Wymaga ważnej licencji Elegant Themes — wgraj `Divi.zip` pobrane z konta.)

## 8. Aktualizacje

- **Rdzeń WordPress** — `composer update roots/wordpress` w katalogu projektu (twoja konfiguracja w `web/app/` jest nietknięta).
- **OverCMS Core** — w panelu pojawi się notyfikacja gdy będzie nowa wersja na GitHub Releases. Klik 1× wystarczy.
- **Pluginy WP** — przez panel **Moduły** lub `wp plugin update --all --path=web/wp`.

## 9. Permissions (jeśli widzisz błędy zapisu)

```bash
sudo chown -R www-data:www-data /var/www/overcms/web/app/uploads
sudo chown -R www-data:www-data /var/www/overcms/web/app/mu-plugins/overcms-core
sudo chmod 640 /var/www/overcms/.env
```

## 10. Diagnostyka

| Problem | Rozwiązanie |
|---|---|
| Biała strona panelu | Zbuduj React: `cd overcms-panel && npm install && npm run build` (paczka release ma już zbudowany dist) |
| 500 po wejściu | Sprawdź `tail -f /var/log/nginx/error.log` i logi PHP-FPM |
| `Allowed memory size exhausted` | W `php.ini`: `memory_limit = 256M` |
| Redirect loop wp-admin | Wyczyść cache Cache Enablera i przeglądarki |
| Brak ikon w sidebarze | Lucide-react jest w bundle — sprawdź czy `panel/dist/.vite/manifest.json` istnieje |
