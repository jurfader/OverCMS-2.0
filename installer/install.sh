#!/usr/bin/env bash
#
# OverCMS — installer all-in-one dla Ubuntu/Debian.
#
# Domyślnie SAM SPRAWDZA i DOINSTALOWUJE brakujące zależności:
#   PHP 8.3 + rozszerzenia, MariaDB client, Composer, WP-CLI, unzip, curl
#
# Użycie:
#   bash install.sh                                    # interaktywnie
#   bash install.sh --domain=example.com \
#                   --db-name=overcms \
#                   --db-user=overcms \
#                   --db-pass=secret \
#                   --admin-user=admin \
#                   --admin-email=admin@example.com \
#                   --admin-pass=changeme \
#                   --non-interactive
#
# Opcjonalnie:
#   --divi-zip=/sciezka/do/Divi.zip   — instaluje motyw Divi
#   --skip-redis                       — pomiń konfigurację Redis Object Cache
#   --skip-deps                        — pomiń sprawdzanie/instalację zależności
#                                        (zakłada że wszystko już jest)
#   --auto-install-deps                — automatycznie instaluj brakujące pakiety
#                                        bez pytania (domyślne dla --non-interactive)
#

set -euo pipefail

# Usuń zmienne środowiskowe które mogłyby wyciec z procesu rodzica
# (np. OVERPANEL ma własne DATABASE_URL do PostgreSQL/Prismy, a oscarotero/env
# z LOCAL_FIRST woli env() nad .env → Bedrock próbowałby parsować ten URL).
unset DATABASE_URL
unset DB_NAME DB_USER DB_PASSWORD DB_HOST DB_PREFIX
unset WP_HOME WP_SITEURL WP_ENV
unset AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY
unset AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT

readonly RED='\033[0;31m'
readonly GREEN='\033[0;32m'
readonly YELLOW='\033[0;33m'
readonly BLUE='\033[0;34m'
readonly NC='\033[0m'

log()    { echo -e "${BLUE}▶${NC} $*"; }
ok()     { echo -e "${GREEN}✔${NC} $*"; }
warn()   { echo -e "${YELLOW}⚠${NC} $*"; }
fail()   { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

# ---------- Parse args ----------
DOMAIN=""
DB_NAME=""
DB_USER=""
DB_PASS=""
DB_HOST="localhost"
ADMIN_USER=""
ADMIN_EMAIL=""
ADMIN_PASS=""
DIVI_ZIP=""
NON_INTERACTIVE=0
SKIP_REDIS=0
SKIP_DEPS=0
AUTO_INSTALL_DEPS=0

for arg in "$@"; do
    case "$arg" in
        --domain=*)            DOMAIN="${arg#*=}" ;;
        --db-name=*)           DB_NAME="${arg#*=}" ;;
        --db-user=*)           DB_USER="${arg#*=}" ;;
        --db-pass=*)           DB_PASS="${arg#*=}" ;;
        --db-host=*)           DB_HOST="${arg#*=}" ;;
        --admin-user=*)        ADMIN_USER="${arg#*=}" ;;
        --admin-email=*)       ADMIN_EMAIL="${arg#*=}" ;;
        --admin-pass=*)        ADMIN_PASS="${arg#*=}" ;;
        --divi-zip=*)          DIVI_ZIP="${arg#*=}" ;;
        --non-interactive)     NON_INTERACTIVE=1 ;;
        --skip-redis)          SKIP_REDIS=1 ;;
        --skip-deps)           SKIP_DEPS=1 ;;
        --auto-install-deps)   AUTO_INSTALL_DEPS=1 ;;
        --help|-h)
            grep '^#' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) fail "Nieznana opcja: $arg" ;;
    esac
done

# W trybie non-interactive auto-install jest domyślnie włączony
if [ "$NON_INTERACTIVE" -eq 1 ]; then
    AUTO_INSTALL_DEPS=1
fi

# ---------- Dependency management ----------
#
# Wymagane narzędzia / pakiety:
#   - PHP 8.3 CLI + ext (mysqli, mbstring, xml, zip, gd, intl, curl, openssl, bcmath)
#   - mariadb-client / mysql-client (do tworzenia bazy)
#   - composer (jeśli vendor/ nie ma)
#   - wp-cli (do wp core install — pobieramy phar lokalnie jeśli brak)
#   - unzip, curl, ca-certificates
#
# WP_CLI ustawiamy globalnie żeby reszta skryptu mogła używać $WP_CLI.
WP_CLI="wp"

# Wykrycie systemu
detect_os() {
    if [ -f /etc/os-release ]; then
        # shellcheck source=/dev/null
        . /etc/os-release
        echo "${ID:-unknown}"
    else
        echo "unknown"
    fi
}

is_root_or_sudo() {
    [ "$(id -u)" -eq 0 ] || command -v sudo >/dev/null
}

run_root() {
    if [ "$(id -u)" -eq 0 ]; then
        "$@"
    else
        sudo "$@"
    fi
}

confirm_install() {
    local what="$1"
    if [ "$AUTO_INSTALL_DEPS" -eq 1 ]; then
        return 0
    fi
    read -rp "Zainstalować ${what}? [Y/n] " ans
    case "$ans" in
        ''|y|Y|yes|YES) return 0 ;;
        *) return 1 ;;
    esac
}

apt_install() {
    local pkgs=("$@")
    log "Instaluję pakiety: ${pkgs[*]}"
    DEBIAN_FRONTEND=noninteractive run_root apt-get install -y --no-install-recommends "${pkgs[@]}" >/dev/null
}

ensure_apt_updated() {
    if [ -z "${APT_UPDATED:-}" ]; then
        log "Aktualizuję listę pakietów apt…"
        run_root apt-get update -qq
        APT_UPDATED=1
    fi
}

ensure_php83_repo() {
    # Na Ubuntu PHP 8.3 jest w PPA ondrej/php (na 22.04 i starszych).
    # Na 24.04 jest w defaultowych repo.
    # Na Debianie używamy repozytorium sury.org.
    local os
    os=$(detect_os)

    if [ "$os" = "ubuntu" ]; then
        if ! apt-cache search '^php8\.3-cli$' 2>/dev/null | grep -q .; then
            log "Dodaję PPA ondrej/php (źródło PHP 8.3 dla Ubuntu)…"
            apt_install software-properties-common ca-certificates lsb-release apt-transport-https
            run_root add-apt-repository -y ppa:ondrej/php >/dev/null
            APT_UPDATED=
            ensure_apt_updated
        fi
    elif [ "$os" = "debian" ]; then
        if ! apt-cache search '^php8\.3-cli$' 2>/dev/null | grep -q .; then
            log "Dodaję repozytorium sury.org (źródło PHP 8.3 dla Debiana)…"
            apt_install ca-certificates apt-transport-https lsb-release curl gnupg
            run_root bash -c 'curl -fsSL https://packages.sury.org/php/apt.gpg -o /etc/apt/trusted.gpg.d/php.gpg'
            run_root bash -c 'echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list'
            APT_UPDATED=
            ensure_apt_updated
        fi
    fi
}

ensure_php() {
    local missing_php=0
    if ! command -v php >/dev/null; then
        missing_php=1
    else
        local php_id
        php_id=$(php -r 'echo PHP_VERSION_ID;')
        [ "$php_id" -ge 80300 ] || missing_php=1
    fi

    if [ "$missing_php" -eq 1 ]; then
        if ! confirm_install "PHP 8.3 + wymagane rozszerzenia"; then
            fail "PHP 8.3 jest wymagany. Przerwano."
        fi
        ensure_apt_updated
        ensure_php83_repo
        apt_install \
            php8.3-cli php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-xml \
            php8.3-zip php8.3-gd php8.3-intl php8.3-curl php8.3-bcmath
        # Wymuś PHP 8.3 jako domyślny
        if command -v update-alternatives >/dev/null && [ -x /usr/bin/php8.3 ]; then
            run_root update-alternatives --set php /usr/bin/php8.3 >/dev/null 2>&1 || true
        fi
    else
        # Sprawdź wymagane rozszerzenia (mogą brakować nawet jeśli php-cli jest)
        local need_ext=()
        local ext
        for ext in mysqli mbstring xml zip gd intl curl openssl; do
            if ! php -r "exit(extension_loaded('${ext}') ? 0 : 1);" 2>/dev/null; then
                need_ext+=("php8.3-${ext}")
            fi
        done
        if [ ${#need_ext[@]} -gt 0 ]; then
            if confirm_install "brakujące rozszerzenia PHP: ${need_ext[*]}"; then
                ensure_apt_updated
                # mysqli ma osobny pakiet (php8.3-mysql)
                local pkgs=()
                local p
                for p in "${need_ext[@]}"; do
                    case "$p" in
                        php8.3-mysqli) pkgs+=(php8.3-mysql) ;;
                        php8.3-openssl) ;; # zwykle wbudowany
                        *) pkgs+=("$p") ;;
                    esac
                done
                [ ${#pkgs[@]} -gt 0 ] && apt_install "${pkgs[@]}"
            else
                warn "Pominięto instalację rozszerzeń — instalator może się wywalić"
            fi
        fi
    fi

    ok "PHP $(php -r 'echo PHP_VERSION;')"
}

ensure_mysql_client() {
    if command -v mysql >/dev/null; then
        return 0
    fi
    if ! confirm_install "klient MariaDB (mariadb-client)"; then
        warn "Brak klienta mysql — tworzenie bazy zostanie pominięte"
        return 0
    fi
    ensure_apt_updated
    apt_install mariadb-client
    ok "mariadb-client zainstalowany"
}

ensure_unzip() {
    if command -v unzip >/dev/null; then
        return 0
    fi
    if ! confirm_install "narzędzie unzip"; then
        fail "unzip jest wymagany do rozpakowania paczek"
    fi
    ensure_apt_updated
    apt_install unzip
}

ensure_curl() {
    if command -v curl >/dev/null; then
        return 0
    fi
    if ! confirm_install "curl"; then
        fail "curl jest wymagany"
    fi
    ensure_apt_updated
    apt_install curl ca-certificates
}

ensure_composer() {
    if command -v composer >/dev/null; then
        ok "Composer $(composer --version --no-ansi 2>/dev/null | awk '{print $3}')"
        return 0
    fi
    if [ -f "vendor/autoload.php" ]; then
        # Mamy vendor/, composer nie jest potrzebny
        return 0
    fi
    if ! confirm_install "Composer (poprzez oficjalny installer)"; then
        fail "Brak Composer i brak vendor/ — nie da się zainstalować zależności PHP"
    fi
    log "Pobieram Composer…"
    local expected actual
    expected=$(curl -fsSL https://composer.github.io/installer.sig)
    curl -fsSL https://getcomposer.org/installer -o /tmp/composer-setup.php
    actual=$(php -r "echo hash_file('sha384', '/tmp/composer-setup.php');")
    if [ "$expected" != "$actual" ]; then
        rm -f /tmp/composer-setup.php
        fail "Checksum Composer installer nie zgadza się — instalacja przerwana"
    fi
    run_root php /tmp/composer-setup.php --quiet --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
    ok "Composer zainstalowany: $(composer --version --no-ansi 2>/dev/null | awk '{print $3}')"
}

ensure_wp_cli() {
    if command -v wp >/dev/null; then
        WP_CLI="wp"
        return 0
    fi
    if [ -x /usr/local/bin/wp ]; then
        WP_CLI="/usr/local/bin/wp"
        return 0
    fi
    if confirm_install "WP-CLI globalnie (/usr/local/bin/wp)"; then
        log "Pobieram WP-CLI…"
        curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x /tmp/wp-cli.phar
        run_root mv /tmp/wp-cli.phar /usr/local/bin/wp
        WP_CLI="/usr/local/bin/wp"
        ok "WP-CLI zainstalowany"
    else
        log "Pobieram WP-CLI lokalnie do /tmp/wp-cli.phar…"
        curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
        chmod +x /tmp/wp-cli.phar
        WP_CLI="php /tmp/wp-cli.phar"
    fi
}

ensure_dependencies() {
    log "Sprawdzam i przygotowuję zależności…"

    local os
    os=$(detect_os)
    if [ "$os" != "ubuntu" ] && [ "$os" != "debian" ]; then
        warn "Wykryto system: ${os}. Auto-instalacja wspiera tylko Ubuntu/Debian."
        warn "Zostanie wykonane tylko sprawdzanie — brakujące zależności trzeba doinstalować ręcznie."
        AUTO_INSTALL_DEPS=0
    fi

    if ! is_root_or_sudo; then
        warn "Nie jesteś rootem ani nie masz sudo — auto-instalacja zależności wyłączona"
        AUTO_INSTALL_DEPS=0
    fi

    ensure_curl
    ensure_unzip
    ensure_php
    ensure_mysql_client
    ensure_composer
    ensure_wp_cli

    ok "Wszystkie zależności gotowe"
}

if [ "$SKIP_DEPS" -eq 1 ]; then
    log "Pomijam sprawdzanie zależności (--skip-deps)"
    # Mimo wszystko ustaw WP_CLI
    if command -v wp >/dev/null; then
        WP_CLI="wp"
    elif [ -f /tmp/wp-cli.phar ]; then
        WP_CLI="php /tmp/wp-cli.phar"
    fi
else
    ensure_dependencies
fi

# WP-CLI odmawia uruchomienia jako root bez --allow-root.
# Gdy jesteśmy rootem (typowe dla automatycznych instalatorów typu OVERPANEL),
# dodaj ten flag do każdego wywołania $WP_CLI.
if [ "$(id -u)" -eq 0 ]; then
    WP_CLI="$WP_CLI --allow-root"
fi

# ---------- Interactive prompts ----------
if [ "$NON_INTERACTIVE" -eq 0 ]; then
    [ -z "$DOMAIN" ]      && read -rp "Domena (np. example.com): " DOMAIN
    [ -z "$DB_NAME" ]     && read -rp "Nazwa bazy danych [overcms]: " DB_NAME && DB_NAME="${DB_NAME:-overcms}"
    [ -z "$DB_USER" ]     && read -rp "Użytkownik bazy [overcms]: " DB_USER && DB_USER="${DB_USER:-overcms}"
    [ -z "$DB_PASS" ]     && read -rsp "Hasło bazy: " DB_PASS && echo
    [ -z "$ADMIN_USER" ]  && read -rp "Login admina [admin]: " ADMIN_USER && ADMIN_USER="${ADMIN_USER:-admin}"
    [ -z "$ADMIN_EMAIL" ] && read -rp "Email admina: " ADMIN_EMAIL
    [ -z "$ADMIN_PASS" ]  && read -rsp "Hasło admina (puste = wygeneruj): " ADMIN_PASS && echo
fi

[ -n "$DOMAIN" ]      || fail "--domain jest wymagane"
[ -n "$DB_NAME" ]     || fail "--db-name jest wymagane"
[ -n "$DB_USER" ]     || fail "--db-user jest wymagane"
[ -n "$DB_PASS" ]     || fail "--db-pass jest wymagane"
[ -n "$ADMIN_EMAIL" ] || fail "--admin-email jest wymagane"
[ -n "$ADMIN_USER" ]  || ADMIN_USER="admin"
[ -n "$ADMIN_PASS" ]  || ADMIN_PASS=$(LC_ALL=C tr -dc 'A-Za-z0-9!@#%^*' </dev/urandom | head -c 20)

# ---------- Project root ----------
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"
log "Katalog projektu: $ROOT_DIR"

# ---------- Composer install ----------
if [ -f "vendor/autoload.php" ]; then
    ok "vendor/ już obecny — pomijam composer install"
else
    log "Instaluję zależności PHP…"
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress
    ok "Composer gotowy"
fi

# ---------- Generate .env ----------
log "Generuję .env…"
SALTS=$(curl -s https://api.wordpress.org/secret-key/1.1/salt/ || true)
if [ -z "$SALTS" ]; then
    warn "Nie udało się pobrać saltów z api.wordpress.org — generuję losowe lokalnie"
    gen_salt() { LC_ALL=C tr -dc 'A-Za-z0-9!@#$%^&*()_+=-' </dev/urandom | head -c 64; }
    SALTS=""
    for k in AUTH_KEY SECURE_AUTH_KEY LOGGED_IN_KEY NONCE_KEY AUTH_SALT SECURE_AUTH_SALT LOGGED_IN_SALT NONCE_SALT; do
        SALTS+="define('$k', '$(gen_salt)');"$'\n'
    done
fi

cat > .env <<EOF
DB_NAME='${DB_NAME}'
DB_USER='${DB_USER}'
DB_PASSWORD='${DB_PASS}'
DB_HOST='${DB_HOST}'

WP_ENV='production'
WP_HOME='https://${DOMAIN}'
WP_SITEURL="\${WP_HOME}/wp"

EOF

# Append salts (parsed from PHP define() to .env format)
echo "$SALTS" | sed -E "s/define\('([A-Z_]+)', *'(.*)'\);/\1='\2'/" >> .env
ok ".env zapisany"

# ---------- Create database ----------
if command -v mysql >/dev/null && [ -n "$DB_PASS" ]; then
    log "Tworzę bazę danych jeśli nie istnieje…"
    mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" -e \
        "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
        2>/dev/null || warn "Nie udało się utworzyć bazy automatycznie — utwórz ją ręcznie"
fi

# ---------- WP install ----------
log "Instaluję WordPress…"
$WP_CLI core install \
    --url="https://${DOMAIN}" \
    --title="OverCMS" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email \
    --path=web/wp

# Ustaw permalinks na pretty
$WP_CLI rewrite structure '/%postname%/' --hard --path=web/wp

ok "WordPress zainstalowany"

# ---------- Activate plugins ----------
log "Aktywuję pluginy…"
$WP_CLI plugin activate seo-by-rank-math --path=web/wp || warn "Nie udało się aktywować Rank Math"
$WP_CLI plugin activate cache-enabler --path=web/wp    || warn "Nie udało się aktywować Cache Enabler"

if [ "$SKIP_REDIS" -eq 0 ] && php -m | grep -qi redis; then
    $WP_CLI plugin activate redis-cache --path=web/wp && \
    $WP_CLI redis enable --path=web/wp 2>/dev/null || warn "Redis Object Cache nie został włączony"
else
    warn "Pomijam Redis (rozszerzenie php-redis nie jest zainstalowane)"
fi

# ---------- Install Divi ----------
if [ -n "$DIVI_ZIP" ]; then
    if [ -f "$DIVI_ZIP" ]; then
        log "Instaluję Divi z $DIVI_ZIP…"
        $WP_CLI theme install "$DIVI_ZIP" --activate --path=web/wp
    else
        warn "Plik Divi nie istnieje: $DIVI_ZIP"
    fi
fi

# ---------- Seed: strona startowa ----------
log "Tworzę stronę startową…"
HOME_ID=$($WP_CLI post create --post_type=page --post_status=publish --post_title="Witaj w OverCMS" \
    --post_content="<h1>Witaj!</h1><p>To jest Twoja nowa strona OverCMS. Edytuj ją w panelu.</p>" \
    --porcelain --path=web/wp)
$WP_CLI option update show_on_front 'page' --path=web/wp
$WP_CLI option update page_on_front "$HOME_ID" --path=web/wp
ok "Strona startowa: $HOME_ID"

# ---------- Summary ----------
echo ""
echo -e "${GREEN}╔═══════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║       OverCMS zainstalowany pomyślnie!        ║${NC}"
echo -e "${GREEN}╚═══════════════════════════════════════════════╝${NC}"
echo ""
echo "  URL witryny:  https://${DOMAIN}"
echo "  Panel:        https://${DOMAIN}/wp/wp-admin/admin.php?page=overcms"
echo "  Login:        ${ADMIN_USER}"
echo "  Hasło:        ${ADMIN_PASS}"
echo ""
echo "Skonfiguruj serwer WWW (Nginx/Apache) tak by document_root wskazywał na:"
echo "  ${ROOT_DIR}/web"
echo ""
