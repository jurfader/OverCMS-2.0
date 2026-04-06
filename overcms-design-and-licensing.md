# OverCMS — Design System & Licensing Architecture
## Dokumentacja referencyjna do przebudowy na bazie WordPress

---

## CZĘŚĆ 1: SYSTEM WYGLĄDU (DESIGN SYSTEM)

---

### 1.1 Typografia

```
--font-sans: 'Inter', ui-sans-serif, system-ui, -apple-system, sans-serif
--font-mono: 'JetBrains Mono', ui-monospace, 'Cascadia Code', monospace
```

Inter używany do całego UI panelu. JetBrains Mono wyłącznie do kodu i kluczy licencyjnych.

---

### 1.2 Kolory — zmienne CSS

#### Kolory markowe (niezależne od motywu)

```css
--color-primary:         #E91E8C       /* Główny różowy */
--color-primary-hover:   #D41880       /* Różowy ciemniejszy (hover) */
--color-primary-muted:   rgba(233 30 140 / 0.15)  /* Tło różowe (ikony, aktywne elementy) */

--color-secondary:       #9333EA       /* Fioletowy */
--color-secondary-hover: #7C22C7
--color-secondary-muted: rgba(147 51 234 / 0.15)

--color-border-active:   #E91E8C       /* Obramowanie aktywnych pól */

--color-success:     #22C55E           /* Zielony */
--color-warning:     #F59E0B           /* Pomarańczowy */
--color-destructive: #EF4444           /* Czerwony */
--color-info:        #3B82F6           /* Niebieski */
```

#### Motyw ciemny (domyślny, `html.dark`)

```css
--color-background:       #0A0B14      /* Prawie czarne tło strony */
--color-background-alt:   #0D0F1A
--color-surface:          #111421      /* Tło kart/formularzy */
--color-surface-elevated: #181B2C      /* Nieznacznie jaśniejsze surface */
--color-surface-hover:    #1E2235      /* Hover na elementach listy */

--color-foreground:       #F8FAFC      /* Główny tekst — prawie biały */
--color-muted-foreground: #8B9CC3      /* Wtórny tekst — niebieskawa szarość */
--color-subtle:           #4A5580      /* Wyciszony tekst, grupowe labelki nav */

--color-border:           rgba(255 255 255 / 0.06)
--color-border-hover:     rgba(255 255 255 / 0.12)

/* Glassmorphism */
--glass-bg:          rgba(17 20 33 / 0.65)
--glass-border:      rgba(255 255 255 / 0.06)
--glass-card-bg:     rgba(17 20 33 / 0.8)
--glass-card-border: rgba(255 255 255 / 0.08)
--glass-blur:        blur(16px) saturate(150%)

/* Cienie */
--shadow-sm: 0 1px 2px rgba(0 0 0 / 0.4)
--shadow:    0 4px 12px rgba(0 0 0 / 0.4)
--shadow-lg: 0 8px 40px rgba(0 0 0 / 0.5)

/* Gradient tła (fixed attachment) */
--bg-gradient:
  radial-gradient(ellipse 60% 40% at 10% 0%, rgba(233 30 140 / 0.08) 0%, transparent 50%),
  radial-gradient(ellipse 60% 40% at 90% 100%, rgba(147 51 234 / 0.08) 0%, transparent 50%)
```

#### Motyw jasny (`:root`)

```css
--color-background:       #ECEEFF
--color-background-alt:   #E4E6FD
--color-surface:          #FFFFFF
--color-surface-elevated: #F5F6FF
--color-surface-hover:    #ECEEFF

--color-foreground:       #0D0F1E
--color-muted-foreground: #4A5580
--color-subtle:           #9BA8CC

--color-border:           rgba(99 102 141 / 0.12)
--color-border-hover:     rgba(99 102 141 / 0.22)

--glass-bg:          rgba(255 255 255 / 0.55)
--glass-border:      rgba(255 255 255 / 0.9)
--glass-card-bg:     rgba(255 255 255 / 0.75)
--glass-card-border: rgba(255 255 255 / 0.95)
--glass-blur:        blur(20px) saturate(180%)

--shadow-sm: 0 1px 3px rgba(0 0 0 / 0.06), 0 1px 2px rgba(0 0 0 / 0.04)
--shadow:    0 4px 16px rgba(0 0 0 / 0.07), 0 1px 3px rgba(0 0 0 / 0.04)
--shadow-lg: 0 8px 40px rgba(99 102 141 / 0.12), 0 2px 8px rgba(0 0 0 / 0.04)

--bg-gradient:
  radial-gradient(ellipse 70% 50% at 15% -5%, rgba(233 30 140 / 0.08) 0%, transparent 60%),
  radial-gradient(ellipse 60% 50% at 85% 105%, rgba(147 51 234 / 0.08) 0%, transparent 60%)
```

#### Cienie markowe (zawsze aktywne)

```css
--shadow-pink: 0 4px 24px rgba(233 30 140 / 0.25)
--shadow-glow: 0 0 40px rgba(233 30 140 / 0.15)
```

---

### 1.3 Zaokrąglenia (Border Radius)

```css
--radius-xs:  0.25rem    /* 4px */
--radius-sm:  0.375rem   /* 6px */
--radius:     0.5rem     /* 8px  — domyślny dla inputów, przycisków */
--radius-md:  0.625rem   /* 10px */
--radius-lg:  0.75rem    /* 12px — karty */
--radius-xl:  1rem       /* 16px */
--radius-2xl: 1.5rem     /* 24px */
--radius-3xl: 2rem       /* 32px */
```

---

### 1.4 Wymiary layoutu

```css
--sidebar-width:           260px
--sidebar-width-collapsed: 68px
--topbar-height:           60px
```

---

### 1.5 Klasy użytkowe CSS

#### Glassmorphism

```css
/* Używany na sidebar, topbar, dropdown */
.glass {
  background: var(--glass-bg);
  backdrop-filter: var(--glass-blur);
  -webkit-backdrop-filter: var(--glass-blur);
  border: 1px solid var(--glass-border);
  transition: background 0.3s ease, border-color 0.3s ease;
}

/* Używany na karty, panele z treścią */
.glass-card {
  background: var(--glass-card-bg);
  backdrop-filter: var(--glass-blur);
  -webkit-backdrop-filter: var(--glass-blur);
  border: 1px solid var(--glass-card-border);
  box-shadow: var(--shadow-lg);
  transition: background 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease;
}

.glass-card:hover {
  border-color: var(--color-border-hover);
  box-shadow: var(--shadow-lg), 0 0 0 1px var(--color-border-hover);
}
```

#### Gradienty

```css
/* Tekst z gradientem różowo-fioletowym */
.gradient-text {
  background: linear-gradient(135deg, #E91E8C 0%, #9333EA 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

/* Tło z gradientem (przyciski primary, avatary) */
.gradient-bg {
  background: linear-gradient(135deg, #E91E8C 0%, #9333EA 100%);
}

/* Świecenie różowe (logo, ikona aktywna) */
.glow-pink {
  box-shadow: 0 0 20px rgba(233 30 140 / 0.35), 0 0 40px rgba(233 30 140 / 0.1);
}

.glow-purple {
  box-shadow: 0 0 20px rgba(147 51 234 / 0.35), 0 0 40px rgba(147 51 234 / 0.1);
}
```

#### Animacje (keyframes)

```css
@keyframes fade-in {
  from { opacity: 0; transform: translateY(8px); }
  to   { opacity: 1; transform: translateY(0); }
}

@keyframes pulse-glow {
  0%, 100% { box-shadow: 0 0 20px rgba(233 30 140 / 0.3); }
  50%       { box-shadow: 0 0 40px rgba(233 30 140 / 0.6); }
}

@keyframes shimmer {
  from { background-position: -200% 0; }
  to   { background-position:  200% 0; }
}

.animate-fade-in    { animation: fade-in 0.4s ease-out; }
.animate-pulse-glow { animation: pulse-glow 2s ease-in-out infinite; }
.animate-shimmer {
  background: linear-gradient(90deg, transparent, rgba(255 255 255 / 0.05), transparent);
  background-size: 200% 100%;
  animation: shimmer 1.5s ease-in-out infinite;
}
```

#### Scrollbar

```css
::-webkit-scrollbar         { width: 5px; height: 5px; }
::-webkit-scrollbar-track   { background: transparent; }
::-webkit-scrollbar-thumb   {
  background: var(--color-border-hover);
  border-radius: 99px;
}
::-webkit-scrollbar-thumb:hover { background: var(--color-subtle); }
```

#### Body

```css
body {
  background-color: var(--color-background);
  background-image: var(--bg-gradient);
  background-attachment: fixed;
  color: var(--color-foreground);
  font-family: var(--font-sans);
  transition: background-color 0.3s ease, color 0.2s ease;
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

::selection {
  background: var(--color-primary-muted);
  color: var(--color-foreground);
}

:focus-visible {
  outline: 2px solid var(--color-primary);
  outline-offset: 2px;
}
```

---

### 1.6 Komponenty UI

#### Button

Warianty:
| Wariant | Opis wizualny |
|---------|--------------|
| `default` | `gradient-bg` (różowo-fioletowy), biały tekst, `glow-pink` na hover |
| `destructive` | `bg-destructive (#EF4444)`, biały tekst |
| `outline` | przezroczyste tło, `border-[var(--color-border-hover)]`, hover: `bg-surface-elevated` |
| `secondary` | `bg-surface-elevated`, tekst foreground |
| `ghost` | przezroczyste, hover: `bg-surface-elevated` |
| `link` | tekst primary, underline na hover |

Rozmiary: `sm` (h-8, text-xs, px-3), `default` (h-9, px-4), `lg` (h-11, px-6), `icon` (h-9 w-9)

Ikony przed tekstem: `w-3.5 h-3.5 mr-1`

#### Input / Textarea

```
h-9 w-full rounded-[var(--radius)]
border border-[var(--color-border-hover)]
bg-[var(--color-surface)]
px-3 py-2 text-sm
text-[var(--color-foreground)]
placeholder:text-[var(--color-subtle)]
focus-visible:border-[var(--color-primary)]
focus-visible:ring-1 focus-visible:ring-[var(--color-primary)]
```

Textarea: `min-h-[80px] resize-none`

#### Badge

```
rounded-full px-2.5 py-0.5 text-xs font-medium
inline-flex items-center gap-1
```

Warianty kolorów:
- `default` — primary pink
- `success` — `bg-success/15 text-success`
- `warning` — `bg-warning/15 text-warning`
- `destructive` — `bg-destructive/15 text-destructive`
- `outline` — obramowanie, brak tła
- `secondary` — fioletowy

#### Card

```
glass-card rounded-[var(--radius-lg)] p-6
```

Struktura wewnętrzna:
- Header: `flex items-center gap-1.5 mb-4`
- Tytuł: `text-base font-semibold text-[var(--color-foreground)]`
- Opis: `text-sm text-[var(--color-muted-foreground)]`
- Footer: `flex items-center mt-4 pt-4 border-t border-[var(--color-border)]`

#### Switch (Toggle)

```
h-5 w-9 rounded-full
checked: gradient-bg
unchecked: bg-[var(--color-surface-elevated)]
thumb: white w-4 h-4 with shadow, translates 0 → 16px
```

#### Tooltip

```
glass rounded-[var(--radius-sm)] px-3 py-1.5 text-xs
```

---

### 1.7 Layout panelu admina

#### Struktura HTML

```
<div class="min-h-screen bg-background">
  <aside class="sidebar glass border-r">   ← fixed, lewa krawędź
  <header class="topbar glass border-b">   ← fixed, góra, prawo od sidebar
  <main class="content">                   ← pt=topbar-height, pl=sidebar-width (lub collapsed)
    <div class="p-6">                      ← padding treści
```

#### Sidebar

Stała szerokość `260px`, zwinięty `68px`. Animacja Framer Motion `duration: 0.25, ease: [0.4, 0, 0.2, 1]`.

Struktura:
```
aside.glass.border-r
├── Logo (h-60px, border-b)
│   ├── Ikona gradient 32×32 z glow-pink
│   └── Tekst "OverCMS" jako gradient-text (chowa się po zwinięciu)
├── Nawigacja (flex-1, overflow-y-auto, py-4, px-2, space-y-6)
│   ├── Sekcja "Główne" — Dashboard, Strony, Media
│   ├── Sekcja "Witryna" — SEO, Nawigacja, Szablony, Treści
│   └── Sekcja "System" — Użytkownicy, Ustawienia, Moduły
│       └── Moduły mają rozwijane pod-pozycje (slide animation)
└── Stopka (border-t, p-2)
    ├── Przycisk zwijania (ChevronLeft/Right)
    └── Numer wersji (font-mono, text-subtle)
```

Stylowanie elementów nav:
- Aktywny: `bg-[var(--color-primary-muted)] text-[var(--color-primary)] rounded-[var(--radius)]`
- Aktywny pasek: `absolute left-0 w-0.5 h-5 bg-[var(--color-primary)] rounded-r`
- Hover: `bg-[var(--color-surface-elevated)]`
- Ikona: `w-4 h-4 shrink-0`
- Label grupy: `text-[10px] uppercase tracking-widest text-[var(--color-subtle)]`
- Zwinięty → tooltips z etykietami

#### Topbar

```
header.glass.border-b
├── Pole wyszukiwania (max-w-sm, input z ikoną Search po lewej)
├── flex-1 ml-auto (spacer)
├── Przycisk motywu (Sun/Moon icon)
├── Dzwonek (Bell) z czerwoną kropką
├── Separator pionowy
└── Dropdown użytkownika
    ├── Avatar (gradient-bg, inicjały)
    ├── Imię i rola
    └── Menu: Profil, Wyloguj
```

#### Strona treści

Nagłówek strony (każda podstrona):
```
<div>
  <h1 class="text-2xl font-bold text-[var(--color-foreground)]">Tytuł</h1>
  <p class="text-sm text-[var(--color-muted-foreground)] mt-1">Opis</p>
</div>
```

Karty formularzy:
```html
<div class="glass-card rounded-[var(--radius-lg)] p-6 space-y-4">
  <h2 class="text-sm font-semibold">Sekcja</h2>
  <!-- pola -->
</div>
```

Tabele:
```html
<div class="glass-card rounded-[var(--radius-lg)] overflow-hidden">
  <!-- Nagłówek -->
  <div class="grid grid-cols-[...] px-5 py-2.5 border-b
              bg-[var(--color-surface-elevated)]">
    <span class="text-[10px] font-semibold uppercase tracking-widest
                 text-[var(--color-subtle)]">Kolumna</span>
  </div>
  <!-- Wiersze -->
  <div class="divide-y divide-[var(--color-border)]">
    <div class="grid grid-cols-[...] items-center px-5 py-3.5
                hover:bg-[var(--color-surface-elevated)]">
    </div>
  </div>
</div>
```

Komunikaty stanu (sukces/błąd):
```html
<div class="rounded-[var(--radius)] bg-[var(--color-success)]/10
            text-[var(--color-success)] px-4 py-3 flex items-center gap-2.5">
  <CheckCircle2 class="w-4 h-4" />
  Operacja zakończona sukcesem
</div>
```

Karty statystyk (dashboard):
```html
<div class="glass-card rounded-[var(--radius-lg)] p-5">
  <p class="text-xs text-[var(--color-muted-foreground)]">Etykieta</p>
  <p class="text-2xl font-bold text-[var(--color-foreground)] mt-1">42</p>
  <p class="text-xs text-[var(--color-success)] mt-1">+12% vs ostatni miesiąc</p>
</div>
```

---

### 1.8 Przełącznik motywu

Implementacja: `next-themes` z atrybutem `class` na `<html>`.
Domyślny motyw: **dark**.
Użytkownik może przełączać przyciskiem Sun/Moon w topbarze.

Dla WordPress: należy dodać `data-theme="dark"` lub klasę `dark` na `<html>` i sterować przez JS/cookie.

---

## CZĘŚĆ 2: SYSTEM LICENCJONOWANIA

---

### 2.1 Architektura ogólna

```
┌─────────────────────────────────────────────────────────────┐
│                    SERWER LICENCJI                           │
│   Hono + PostgreSQL + ED25519 + Stripe                      │
│   Adres: 51.38.137.199:3002                                 │
│                                                             │
│   Tabele: lic_licenses, lic_activations, lic_plugins,       │
│           lic_audit                                         │
└───────────────────────┬─────────────────────────────────────┘
                        │ HTTP (private network)
          ┌─────────────┼───────────────────┐
          ▼             ▼                   ▼
    ┌──────────┐  ┌──────────┐      ┌──────────────┐
    │ API CMS  │  │  Portal  │      │  Panel WP    │
    │ (Hono)   │  │ (Next.js)│      │  (PHP)       │
    │          │  │          │      │              │
    │ validate │  │ purchase │      │ validate     │
    │ activate │  │ checkout │      │ activate     │
    │ plugins  │  │ status   │      │ plugins      │
    └──────────┘  └──────────┘      └──────────────┘
```

---

### 2.2 Plany licencyjne

| Plan | Instalacje | Czas | Pluginy | Cena |
|------|-----------|------|---------|------|
| **trial** | 1 | 14 dni | Brak dostępu do płatnych | Bezpłatny |
| **solo** | 1 | Lifetime | Solo+ | Jednorazowa opłata |
| **agency** | Bez limitu (9999) | Lifetime | Wszystkie | Jednorazowa opłata |

---

### 2.3 Schemat bazy danych

#### Tabela `lic_licenses`

```sql
id              UUID PRIMARY KEY DEFAULT gen_random_uuid()
key             VARCHAR(64) UNIQUE NOT NULL   -- Format: XXXX-XXXX-XXXX-XXXX (hex)
plan            ENUM('trial','solo','agency') DEFAULT 'trial'
status          ENUM('active','suspended','expired','revoked') DEFAULT 'active'
buyerEmail      VARCHAR(255) NOT NULL
buyerName       VARCHAR(255)
maxInstallations INTEGER DEFAULT 1
expiresAt       TIMESTAMP                     -- NULL = dożywotnia
stripeCustomerId VARCHAR(100)
stripeSubId     VARCHAR(100)
notes           TEXT
createdAt       TIMESTAMP DEFAULT now()
updatedAt       TIMESTAMP DEFAULT now()
```

#### Tabela `lic_activations`

```sql
id              UUID PRIMARY KEY DEFAULT gen_random_uuid()
licenseId       UUID NOT NULL REFERENCES lic_licenses(id) ON DELETE CASCADE
domain          VARCHAR(255) NOT NULL          -- Znormalizowana domena
installationId  VARCHAR(64) NOT NULL           -- Unikalny ID instalacji
active          BOOLEAN DEFAULT true
lastSeenAt      TIMESTAMP DEFAULT now()
activatedAt     TIMESTAMP DEFAULT now()

UNIQUE INDEX na (licenseId, domain)
```

#### Tabela `lic_plugins`

```sql
id              VARCHAR(100) PRIMARY KEY       -- np. 'reservations'
name            VARCHAR(255) NOT NULL
description     TEXT
version         VARCHAR(50) NOT NULL
icon            VARCHAR(100)                   -- Nazwa ikony Lucide
author          VARCHAR(255)
minCmsVersion   VARCHAR(50)
requiredPlan    ENUM('trial','solo','agency') DEFAULT 'solo'
price           INTEGER DEFAULT 0              -- Grosze (0 = bezpłatny)
currency        VARCHAR(3) DEFAULT 'PLN'
downloadUrl     TEXT                           -- URL do .tar.gz
changelog       TEXT
active          BOOLEAN DEFAULT true
downloads       INTEGER DEFAULT 0
createdAt       TIMESTAMP DEFAULT now()
updatedAt       TIMESTAMP DEFAULT now()
```

#### Tabela `lic_audit`

```sql
id              UUID PRIMARY KEY DEFAULT gen_random_uuid()
licenseId       UUID REFERENCES lic_licenses(id) ON DELETE SET NULL
event           VARCHAR(100)                   -- 'activate', 'validate', 'deactivate', itd.
domain          VARCHAR(255)
meta            TEXT                           -- JSON
createdAt       TIMESTAMP DEFAULT now()
```

---

### 2.4 Endpointy API serwera licencji

Bazowy URL: `http://51.38.137.199:3002`

#### Publiczne (bez auth)

**POST /activate**
```json
Request:  { "licenseKey": "XXXX-XXXX-XXXX-XXXX", "domain": "example.com", "installationId": "abc123" }
Response: { "success": true, "plan": "solo", "expiresAt": null, "signature": "base64..." }
Błędy:    404 INVALID_LICENSE | 403 LICENSE_INACTIVE | 403 LICENSE_EXPIRED | 403 MAX_INSTALLATIONS_REACHED
```

**POST /validate**
```json
Request:  { "licenseKey": "...", "domain": "...", "installationId": "..." }
Response: { "valid": true, "plan": "solo", "expiresAt": null, "signature": "base64..." }
          { "valid": false, "error": "DOMAIN_NOT_ACTIVATED" }
```

**POST /deactivate**
```json
Request:  { "licenseKey": "...", "domain": "...", "installationId": "..." }
Response: { "success": true }
```

**GET /status?licenseKey=...&domain=...**
```json
Response: {
  "valid": true,
  "plan": "solo",
  "status": "active",
  "domainActivated": true,
  "totalActivations": 1,
  "maxInstallations": 1,
  "expiresAt": null
}
```

**GET /plugins** — lista dostępnych pluginów (marketplace)
**POST /plugins/:id/download** — pobierz plugin (wymaga aktywnej licencji)

#### Admin (wymaga `Authorization: Bearer {LICENSE_ADMIN_SECRET}`)

```
GET    /admin/licenses           — lista wszystkich licencji
GET    /admin/licenses/:key      — szczegóły + aktywacje + audit
POST   /admin/licenses           — stwórz licencję ręcznie
PATCH  /admin/licenses/:key      — aktualizuj (plan, status, notes, itd.)
DELETE /admin/licenses/:key      — unieważnij
GET    /admin/stats              — statystyki
POST   /plugins                  — dodaj/aktualizuj plugin (wymaga X-Admin-Key header)
```

#### Stripe (checkout)

```
POST /checkout/session           — tworzy sesję Stripe
POST /webhooks/stripe            — odbiera zdarzenia (checkout.session.completed → tworzy licencję)
```

---

### 2.5 Logika walidacji w CMS (middleware)

Kod działający po stronie API CMS przy każdym żądaniu:

```
1. Jeśli ścieżka = /health → przepuść bez sprawdzania
2. Jeśli minęło < 24h od ostatniego sprawdzenia → użyj cache
3. Jeśli minęło >= 24h:
   a. POST /validate do serwera licencji (timeout 5s)
   b. Zapisz wynik do /tmp/overcms-license-status.json
   c. Zaktualizuj cache in-memory
4. Jeśli serwer licencji nieosiągalny:
   a. Odczytaj cache z dysku
   b. Jeśli nigdy nie walidowany → przepuść (optymistyczny start)
   c. Jeśli ostatnia walidacja + 7 dni > teraz → przepuść z ostrzeżeniem
   d. Jeśli minęło > 7 dni od ostatniej walidacji → zwróć 403
5. Jeśli licencja nieważna → zwróć 403 z { error: "License invalid", code: "LICENSE_INVALID" }
```

**Zmienne środowiskowe API:**
```
OVERCMS_LICENSE_KEY=XXXX-XXXX-XXXX-XXXX
LICENSE_SERVER_URL=http://51.38.137.199:3002
OVERCMS_INSTALL_ID=unique-installation-id
API_DOMAIN=example.com
```

---

### 2.6 Podpisy ED25519

Serwer licencji podpisuje odpowiedzi kluczem prywatnym ED25519.

Klucz publiczny osadzony w paczce core:
```
MCowBQYDK2VwAyEAyQpvyOLQEQ8UU5BKIMcZrEz5SxKdJT1iCExu7hnvNrQ=
```

Podpis jest w polu `"signature"` odpowiedzi jako base64.

Logika: jeśli odpowiedź nie zawiera podpisu → akceptuj (backwards compat). Jeśli zawiera → weryfikuj.

Dla WordPress: weryfikacja podpisu opcjonalna — wystarczy sprawdzić pole `valid: true`.

---

### 2.7 Dystrybucja pluginów (Marketplace)

Schemat dystrybucji:
1. Plugin spakowany jako `.tar.gz` i wgrany na serwer (lub S3)
2. URL do pliku zapisany w polu `downloadUrl` tabeli `lic_plugins`
3. Użytkownik klika "Zainstaluj" w panelu
4. Panel CMS wysyła `POST /plugins/:id/download` z kluczem licencyjnym
5. Serwer licencji sprawdza plan, zwraca `downloadUrl` + podpis
6. CMS pobiera tar.gz, rozpakowuje do katalogu `modules/`
7. Rejestruje plugin w lokalnej bazie, użytkownik aktywuje ręcznie

Plan enforcement w marketplace:
```
trial  = poziom 0  →  dostęp tylko do pluginów z requiredPlan='trial'
solo   = poziom 1  →  dostęp do trial + solo
agency = poziom 2  →  dostęp do wszystkich
```

---

### 2.8 Portal klienta

Strona zakupu i zarządzania licencją (`portal` app):

```
/                     — landing (cennik, plany)
/checkout/success     — po zakupie (pokazuje klucz licencyjny)
/license              — zarządzanie (aktywacje, plan, deaktywacja domen)
```

API portalu pobiera dane licencji przez `GET /customer/:key` (podpisany endpoint).
**WAŻNE:** Portal nigdy nie wywołuje serwera licencji bezpośrednio z przeglądarki — zawsze przez własny backend (Next.js route handlers), żeby nie ujawniać URL serwera licencji.

---

## CZĘŚĆ 3: INSTRUKCJA PRZEBUDOWY NA WORDPRESS

---

### 3.1 Co zachować z WordPressa

- Silnik PHP + MySQL/PostgreSQL
- System pluginów (hooks, filters, actions)
- REST API (`/wp-json/wp/v2/`)
- Zarządzanie mediami (wp_posts attachment)
- System użytkowników + role

### 3.2 Co usunąć/ukryć z WordPressa

- Domyślny motyw admina (wp-admin default styles)
- Pasek admina na frontendzie (`show_admin_bar(false)`)
- Dashboard widgets (hello dolly, quick draft, news)
- Zbędne menu: Komentarze, Linki
- Domyślny edytor (Gutenberg) — zastąpić własnym

### 3.3 Nowy panel admina (zamiast wp-admin)

Opcje:
1. **React SPA** ładowana pod `/overcms/` — komunikuje się przez WP REST API + custom REST endpoints
2. **Własny motyw admina** — zastąpienie domyślnych stylów WordPress

Rekomendacja: React SPA (jak OverCMS), bo daje pełną kontrolę nad wyglądem.

Architektura:
```
WordPress (backend)
├── REST API (/wp-json/overcms/v1/...)  ← custom endpoints
├── Plugin licencji (sprawdza co 24h)
├── Plugin marketplace (pobiera/instaluje pluginy)
└── Plugin każdego modułu (blog, portfolio, itd.)

React SPA (frontend panelu)
├── Ten sam design system (CSS variables z sekcji 1)
├── Sidebar + Topbar identyczne jak OverCMS
└── Strony: Dashboard, Treści, Media, SEO, Moduły, Ustawienia
```

### 3.4 Plugin licencji dla WordPress

Minimalny plugin PHP do walidacji:

```php
<?php
/**
 * Plugin Name: OverCMS License
 * Version: 1.0
 */

define('OVERCMS_LICENSE_KEY', get_option('overcms_license_key'));
define('OVERCMS_LICENSE_SERVER', 'http://51.38.137.199:3002');
define('OVERCMS_LICENSE_CACHE_FILE', WP_CONTENT_DIR . '/overcms-license.json');
define('OVERCMS_GRACE_DAYS', 7);
define('OVERCMS_CHECK_INTERVAL', 86400); // 24h

function overcms_validate_license(): bool {
    $cached = overcms_read_cache();

    // Sprawdź czy potrzeba odświeżenia
    if ($cached && (time() - $cached['timestamp']) < OVERCMS_CHECK_INTERVAL) {
        return (bool) $cached['valid'];
    }

    // Waliduj przez HTTP
    $domain = parse_url(get_site_url(), PHP_URL_HOST);
    $result = wp_remote_post(OVERCMS_LICENSE_SERVER . '/validate', [
        'timeout' => 5,
        'body'    => json_encode([
            'licenseKey'     => OVERCMS_LICENSE_KEY,
            'domain'         => $domain,
            'installationId' => get_option('overcms_install_id'),
        ]),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($result)) {
        // Serwer nieosiągalny — grace period
        if (!$cached) return true; // Pierwszy raz
        $grace_end = $cached['timestamp'] + (OVERCMS_GRACE_DAYS * 86400);
        return time() < $grace_end;
    }

    $body  = json_decode(wp_remote_retrieve_body($result), true);
    $valid = (bool) ($body['valid'] ?? false);
    $plan  = $body['plan'] ?? 'trial';

    overcms_write_cache(['valid' => $valid, 'plan' => $plan, 'timestamp' => time()]);

    return $valid;
}

function overcms_get_plan(): string {
    $cached = overcms_read_cache();
    return $cached['plan'] ?? 'trial';
}

function overcms_read_cache(): ?array {
    if (!file_exists(OVERCMS_LICENSE_CACHE_FILE)) return null;
    return json_decode(file_get_contents(OVERCMS_LICENSE_CACHE_FILE), true);
}

function overcms_write_cache(array $data): void {
    file_put_contents(OVERCMS_LICENSE_CACHE_FILE, json_encode($data));
}
```

### 3.5 Marketplace dla WordPress

Custom REST endpoint instalujący plugin:

```php
register_rest_route('overcms/v1', '/marketplace/install', [
    'methods'  => 'POST',
    'callback' => 'overcms_install_plugin',
    'permission_callback' => function() {
        return current_user_can('manage_options');
    },
]);

function overcms_install_plugin(WP_REST_Request $request): WP_REST_Response {
    $plugin_id   = $request->get_param('pluginId');
    $license_key = get_option('overcms_license_key');
    $domain      = parse_url(get_site_url(), PHP_URL_HOST);
    $install_id  = get_option('overcms_install_id');

    // Pobierz URL do pobrania z serwera licencji
    $res = wp_remote_post(OVERCMS_LICENSE_SERVER . "/plugins/{$plugin_id}/download", [
        'body'    => json_encode(compact('licenseKey', 'domain', 'installationId')),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($res)) {
        return new WP_REST_Response(['error' => 'Cannot reach license server'], 502);
    }

    $body        = json_decode(wp_remote_retrieve_body($res), true);
    $download_url = $body['data']['downloadUrl'];
    $version     = $body['data']['version'];

    // Pobierz i zainstaluj plugin
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

    $upgrader = new Plugin_Upgrader();
    $result   = $upgrader->install($download_url);

    if (is_wp_error($result) || !$result) {
        return new WP_REST_Response(['error' => 'Installation failed'], 500);
    }

    return new WP_REST_Response(['data' => ['pluginId' => $plugin_id, 'version' => $version, 'status' => 'installed']]);
}
```

---

### 3.6 Checklist migracji

- [ ] Skopiować zmienne CSS z sekcji 1.1–1.5 do nowego globals.css
- [ ] Zainstalować font Inter + JetBrains Mono
- [ ] Zbudować komponent Sidebar zgodnie z sekcją 1.7
- [ ] Zbudować komponent Topbar zgodnie z sekcją 1.7
- [ ] Ostylować komponenty: Button, Input, Badge, Card, Switch, Tooltip
- [ ] Dodać obsługę motywu jasny/ciemny (domyślnie ciemny)
- [ ] Wdrożyć plugin licencji (walidacja co 24h + grace period 7 dni)
- [ ] Wdrożyć marketplace (pobieranie pluginów przez serwer licencji)
- [ ] Wyłączyć domyślny wp-admin i zastąpić własnym panelem
- [ ] Skonfigurować klucz API admina serwera licencji: `LICENSE_ADMIN_SECRET`
- [ ] Skonfigurować `OVERCMS_LICENSE_KEY` dla instalacji WordPress

---

*Dokument wygenerowany: 2026-04-04*
*Projekt: OVERMEDIA / OverCMS*
