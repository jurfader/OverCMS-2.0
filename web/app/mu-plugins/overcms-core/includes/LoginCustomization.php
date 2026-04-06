<?php

namespace OverCMS\Core;

/**
 * Stylizuje wp-login.php do wyglądu zgodnego z panelem OverCMS:
 * gradient pink→purple, glassmorphism card, logo OverCMS, ciemny motyw.
 */
final class LoginCustomization
{
    public static function register(): void
    {
        add_action('login_enqueue_scripts', [self::class, 'enqueueStyles']);
        add_filter('login_headerurl', [self::class, 'headerUrl']);
        add_filter('login_headertext', [self::class, 'headerText']);
        add_filter('login_errors', [self::class, 'genericError']);
        add_filter('login_message', [self::class, 'addBranding']);
    }

    public static function headerUrl(): string
    {
        return home_url('/');
    }

    public static function headerText(): string
    {
        return get_bloginfo('name');
    }

    /**
     * Z bezpieczeństwa: zwracaj generyczny komunikat błędu zamiast
     * "incorrect username" / "incorrect password" — utrudnia enumerację kont.
     */
    public static function genericError(string $error): string
    {
        if (str_contains((string) $error, 'incorrect') || str_contains((string) $error, 'unknown')) {
            return '<strong>Błąd:</strong> Nieprawidłowy login lub hasło.';
        }
        return $error;
    }

    public static function addBranding(string $message): string
    {
        if (!empty($message)) {
            return $message;
        }
        return '<p class="overcms-tagline">Panel OverCMS · Zaloguj się aby zarządzać witryną</p>';
    }

    public static function enqueueStyles(): void
    {
        // Inline styles — bez dodatkowego requestu
        ?>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style id="overcms-login">
            :root {
                --ocms-bg:        #0A0B14;
                --ocms-surface:   rgba(17, 20, 33, 0.8);
                --ocms-border:    rgba(255, 255, 255, 0.08);
                --ocms-text:      #F8FAFC;
                --ocms-muted:     #8B9CC3;
                --ocms-primary:   #E91E8C;
                --ocms-secondary: #9333EA;
            }

            html, body.login {
                background: var(--ocms-bg) !important;
                background-image:
                    radial-gradient(ellipse 60% 40% at 10% 0%,  rgba(233, 30, 140, 0.10) 0%, transparent 50%),
                    radial-gradient(ellipse 60% 40% at 90% 100%, rgba(147, 51, 234, 0.10) 0%, transparent 50%) !important;
                background-attachment: fixed !important;
                color: var(--ocms-text) !important;
                font-family: 'Inter', -apple-system, system-ui, sans-serif !important;
                -webkit-font-smoothing: antialiased;
            }

            body.login #login {
                width: 380px;
                padding: 8% 0 0;
            }

            /* Logo */
            body.login #login h1 a {
                background: none;
                background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><defs><linearGradient id='g' x1='0' y1='0' x2='64' y2='64' gradientUnits='userSpaceOnUse'><stop offset='0' stop-color='%23E91E8C'/><stop offset='1' stop-color='%239333EA'/></linearGradient></defs><rect x='6' y='6' width='52' height='52' rx='12' fill='url(%23g)'/><path d='M20 32l8 8 16-16' stroke='white' stroke-width='5' stroke-linecap='round' stroke-linejoin='round' fill='none'/></svg>") !important;
                background-repeat: no-repeat;
                background-size: contain;
                background-position: center;
                width: 64px;
                height: 64px;
                margin: 0 auto 24px;
                filter: drop-shadow(0 0 24px rgba(233, 30, 140, 0.4));
                transition: filter 0.3s ease;
            }
            body.login #login h1 a:hover {
                filter: drop-shadow(0 0 32px rgba(233, 30, 140, 0.6));
            }

            /* Glass card */
            body.login form {
                background: var(--ocms-surface) !important;
                backdrop-filter: blur(16px) saturate(150%);
                -webkit-backdrop-filter: blur(16px) saturate(150%);
                border: 1px solid var(--ocms-border) !important;
                border-radius: 16px !important;
                box-shadow: 0 8px 40px rgba(0, 0, 0, 0.5) !important;
                padding: 32px 28px !important;
                margin-top: 0 !important;
            }

            body.login form p label {
                color: var(--ocms-muted) !important;
                font-size: 12px !important;
                font-weight: 500 !important;
            }

            body.login form .input,
            body.login input[type="text"],
            body.login input[type="email"],
            body.login input[type="password"] {
                background: rgba(255, 255, 255, 0.04) !important;
                border: 1px solid rgba(255, 255, 255, 0.12) !important;
                border-radius: 8px !important;
                color: var(--ocms-text) !important;
                font-size: 14px !important;
                padding: 10px 14px !important;
                box-shadow: none !important;
                transition: border-color 0.2s, box-shadow 0.2s;
            }
            body.login form .input:focus,
            body.login input:focus {
                border-color: var(--ocms-primary) !important;
                box-shadow: 0 0 0 3px rgba(233, 30, 140, 0.15) !important;
                outline: none !important;
            }

            /* Show password button */
            body.login .button.wp-hide-pw {
                color: var(--ocms-muted) !important;
                box-shadow: none !important;
            }
            body.login .button.wp-hide-pw:hover {
                color: var(--ocms-primary) !important;
            }

            /* Submit button */
            body.login .submit .button-primary,
            body.login .button-primary {
                background: linear-gradient(135deg, #E91E8C 0%, #9333EA 100%) !important;
                border: none !important;
                border-radius: 8px !important;
                color: #fff !important;
                font-weight: 600 !important;
                font-size: 14px !important;
                height: 42px !important;
                padding: 0 24px !important;
                text-shadow: none !important;
                box-shadow: 0 4px 24px rgba(233, 30, 140, 0.25) !important;
                width: 100%;
                transition: all 0.2s;
            }
            body.login .submit .button-primary:hover,
            body.login .button-primary:hover {
                box-shadow: 0 6px 32px rgba(233, 30, 140, 0.35) !important;
                transform: translateY(-1px);
            }

            /* Remember me */
            body.login .forgetmenot label {
                color: var(--ocms-muted) !important;
                font-size: 12px !important;
            }
            body.login input[type="checkbox"]:checked::before {
                color: var(--ocms-primary) !important;
            }
            body.login input[type="checkbox"] {
                background: rgba(255, 255, 255, 0.06) !important;
                border-color: rgba(255, 255, 255, 0.2) !important;
            }

            /* Links under form */
            body.login #nav,
            body.login #backtoblog {
                text-align: center !important;
                margin-top: 16px !important;
            }
            body.login #nav a,
            body.login #backtoblog a {
                color: var(--ocms-muted) !important;
                font-size: 12px !important;
                text-decoration: none !important;
                transition: color 0.2s;
            }
            body.login #nav a:hover,
            body.login #backtoblog a:hover {
                color: var(--ocms-primary) !important;
            }

            /* Error / message boxes */
            body.login .message,
            body.login #login_error,
            body.login .notice {
                background: rgba(239, 68, 68, 0.10) !important;
                border-left: 3px solid #EF4444 !important;
                border-radius: 8px !important;
                color: #FCA5A5 !important;
                font-size: 13px !important;
                padding: 12px 14px !important;
                box-shadow: none !important;
                backdrop-filter: blur(12px);
            }
            body.login .message {
                background: rgba(34, 197, 94, 0.10) !important;
                border-left-color: #22C55E !important;
                color: #86EFAC !important;
            }

            /* Tagline */
            body.login .overcms-tagline {
                color: var(--ocms-muted) !important;
                font-size: 12px !important;
                text-align: center !important;
                margin: -12px 0 16px !important;
            }

            /* Language switcher footer */
            body.login .language-switcher {
                display: none !important;
            }

            /* Privacy policy link */
            body.login .privacy-policy-page-link {
                margin-top: 24px !important;
            }
            body.login .privacy-policy-page-link a {
                color: var(--ocms-muted) !important;
                font-size: 11px !important;
            }
        </style>
        <?php
    }
}
