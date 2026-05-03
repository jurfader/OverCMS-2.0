<?php

namespace OverCMS\Core;

/**
 * Kompatybilność z Divi Visual Builderem w kontekście panelu OverCMS.
 *
 * Problem: panel OverCMS osadza strony wp-admin (np. Divi Theme Builder)
 * w <iframe> z parametrem ?overcms_embed=1. Divi VB chce z kolei otworzyć
 * edytor layoutu w KOLEJNYM iframe (iframe-in-iframe). Browser/Divi gubią
 * nonce, browser blokuje zagnieżdżone konteksty, VB wisi w nieskończonym
 * loaderze.
 *
 * Fix: gdy strona z et_fb=1 (Visual Builder) wykryje że jest zagnieżdżona,
 * wybijamy się do top-window. VB ładuje się normalnie na pełnym ekranie.
 */
final class DiviCompatibility
{
    public static function register(): void
    {
        // Wybijanie do top-window gdy VB wykryje że jest w iframe
        add_action('wp_footer', [self::class, 'breakoutFromIframe'], 1);
        add_action('admin_print_footer_scripts', [self::class, 'breakoutFromIframe'], 1);

        // Theme Builder admin page: kliki "Edit Layout" w Divi otwierają VB
        // w iframe — przekierowujemy je na top-window. Działa zarówno gdy
        // strona TB jest w panelu OverCMS, jak i bezpośrednio w wp-admin.
        add_action('admin_print_footer_scripts', [self::class, 'interceptThemeBuilderLinks']);
    }

    /**
     * Wybij VB iframe do top-window. Wstrzykiwany na każdej stronie z et_fb=1.
     * Sprawdza czy jesteśmy w iframe i czy top-window jest na tym samym origin
     * — jeśli tak, replace top-window URL na bieżący, żeby VB miał pełen kontekst.
     */
    public static function breakoutFromIframe(): void
    {
        $isVb = !empty($_GET['et_fb']) || !empty($_GET['et_tb']) || !empty($_GET['et_pb_preview']);
        if (!$isVb) {
            return;
        }
        ?>
<script>
(function(){
    if (window.top === window.self) return;
    try {
        // Sprawdz czy mozemy uzyskac top-window URL (same-origin)
        var topUrl = window.top.location.href;
        var currentUrl = window.location.href;
        if (topUrl === currentUrl) return; // juz jestesmy na top
        // Wybij sie do top-window z URL-em VB
        window.top.location.replace(currentUrl);
    } catch (e) {
        // Cross-origin lub blokada — VB nie zadziala w iframe, zaloguj
        console.warn('[OverCMS] Cannot breakout VB iframe:', e);
    }
})();
</script>
        <?php
    }

    /**
     * Theme Builder w Divi 5.x: linki "Edit Layout" otwieraja VB w nowym iframe
     * (a w panelu OverCMS to juz jest iframe-in-iframe). Przechwytujemy klikniecia
     * i kierujemy je do top-window zeby VB dostal pelny viewport.
     */
    public static function interceptThemeBuilderLinks(): void
    {
        $isThemeBuilder = !empty($_GET['page']) && $_GET['page'] === 'et_theme_builder';
        if (!$isThemeBuilder) {
            return;
        }
        ?>
<script>
(function(){
    function redirectToTop(url) {
        if (window.top !== window.self) {
            try { window.top.location.href = url; return true; } catch (e) {}
        }
        return false;
    }

    // Intercept link clicks z et_fb=1 / et_tb=1
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href*="et_fb=1"], a[href*="et_tb=1"]');
        if (!link) return;
        if (redirectToTop(link.href)) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, true);

    // Theme Builder uzywa tez window.open dla niektorych akcji
    var origOpen = window.open;
    window.open = function(url, target, features) {
        if (url && (url.indexOf('et_fb=1') !== -1 || url.indexOf('et_tb=1') !== -1)) {
            if (redirectToTop(url)) return null;
        }
        return origOpen.apply(this, arguments);
    };
})();
</script>
        <?php
    }
}
