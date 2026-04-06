#!/usr/bin/env bash
#
# OverCMS — installer all-in-one dla Ubuntu/Linux.
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
#

set -euo pipefail

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

for arg in "$@"; do
    case "$arg" in
        --domain=*)        DOMAIN="${arg#*=}" ;;
        --db-name=*)       DB_NAME="${arg#*=}" ;;
        --db-user=*)       DB_USER="${arg#*=}" ;;
        --db-pass=*)       DB_PASS="${arg#*=}" ;;
        --db-host=*)       DB_HOST="${arg#*=}" ;;
        --admin-user=*)    ADMIN_USER="${arg#*=}" ;;
        --admin-email=*)   ADMIN_EMAIL="${arg#*=}" ;;
        --admin-pass=*)    ADMIN_PASS="${arg#*=}" ;;
        --divi-zip=*)      DIVI_ZIP="${arg#*=}" ;;
        --non-interactive) NON_INTERACTIVE=1 ;;
        --skip-redis)      SKIP_REDIS=1 ;;
        --help|-h)
            grep '^#' "$0" | sed 's/^# \{0,1\}//'
            exit 0
            ;;
        *) fail "Nieznana opcja: $arg" ;;
    esac
done

# ---------- Requirements ----------
log "Sprawdzam wymagania systemu…"

command -v php >/dev/null      || fail "Brak PHP. Zainstaluj: sudo apt install php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-zip php8.3-gd php8.3-intl php8.3-curl"
command -v mysql >/dev/null    || warn "Brak klienta mysql. Tworzenie bazy zostanie pominięte."

PHP_VER=$(php -r 'echo PHP_VERSION_ID;')
[ "$PHP_VER" -ge 80300 ] || fail "Wymagany PHP 8.3+. Aktualnie: $(php -r 'echo PHP_VERSION;')"
ok "PHP $(php -r 'echo PHP_VERSION;')"

# Vendor: jeśli paczka release nie zawiera vendor/, potrzebny composer
if [ ! -f "vendor/autoload.php" ]; then
    command -v composer >/dev/null || fail "Brak Composer i brak vendor/. Zainstaluj: https://getcomposer.org"
fi

# WP-CLI: pobierz lokalnie jeśli nie ma globalnego
WP_CLI="wp"
if ! command -v wp >/dev/null; then
    log "Pobieram WP-CLI lokalnie…"
    curl -fsSL -o /tmp/wp-cli.phar https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
    chmod +x /tmp/wp-cli.phar
    WP_CLI="php /tmp/wp-cli.phar"
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
