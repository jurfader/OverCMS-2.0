<?php

namespace OverCMS\Core;

/**
 * Kompatybilność z Divi Visual Builderem w kontekście panelu OverCMS.
 *
 * Problem: panel OverCMS osadza Divi Theme Builder w <iframe> z parametrem
 * ?overcms_embed=1. Klikniecie "Edit Layout" w Theme Builderze tworzy
 * KOLEJNY iframe z VB (iframe-in-iframe). Browser/Divi gubia nonce/auth,
 * VB wisi w nieskonczonym loaderze.
 *
 * Fix: na stronie Theme Buildera w embed mode przechwytujemy KAZDA proba
 * zaladowania VB w iframe — przez:
 *   1. Override HTMLIFrameElement.prototype.src setter
 *   2. MutationObserver na nowo dodawane <iframe> z VB URL
 *   3. Override window.open
 * I forsujemy otwarcie w nowej karcie (window.open _blank) — VB dostaje
 * pelny viewport bez zagniezdzonego kontekstu.
 */
final class DiviCompatibility
{
    public static function register(): void
    {
        add_action('admin_print_footer_scripts', [self::class, 'interceptThemeBuilderVb']);
    }

    public static function interceptThemeBuilderVb(): void
    {
        $isThemeBuilder = !empty($_GET['page']) && $_GET['page'] === 'et_theme_builder';
        $isEmbed = !empty($_GET['overcms_embed']) || !empty($_COOKIE['overcms_embed']);
        if (!$isThemeBuilder || !$isEmbed) {
            return;
        }
        ?>
<script>
(function(){
    function isVbUrl(url) {
        if (!url || typeof url !== 'string') return false;
        return url.indexOf('et_fb=1') !== -1 || url.indexOf('et_tb=1') !== -1 || url.indexOf('app_window=1') !== -1;
    }

    function openInNewTab(url) {
        // Otwarcie w top-window zeby ominac iframe-in-iframe
        try {
            (window.top || window).open(url, '_blank', 'noopener,noreferrer');
        } catch (e) {
            window.open(url, '_blank', 'noopener,noreferrer');
        }
    }

    // 1. Override iframe.src setter — najwczesniejsza warstwa
    try {
        var iframeSrcDesc = Object.getOwnPropertyDescriptor(HTMLIFrameElement.prototype, 'src');
        if (iframeSrcDesc && iframeSrcDesc.configurable) {
            Object.defineProperty(HTMLIFrameElement.prototype, 'src', {
                configurable: true,
                get: iframeSrcDesc.get,
                set: function(value) {
                    if (isVbUrl(value)) {
                        console.info('[OverCMS] VB iframe blocked, opening in new tab:', value);
                        openInNewTab(value);
                        // Pozostaw iframe pusty (about:blank) zeby Divi nie dostal bledow JS
                        return iframeSrcDesc.set.call(this, 'about:blank');
                    }
                    return iframeSrcDesc.set.call(this, value);
                }
            });
        }
    } catch (e) {
        console.warn('[OverCMS] Cannot override iframe.src setter:', e);
    }

    // 2. MutationObserver — fallback dla iframes dodawanych z atrybutem src przez setAttribute
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(m) {
            m.addedNodes.forEach(function(node) {
                if (node.nodeType !== 1) return;
                // Bezposrednio dodany <iframe>
                if (node.tagName === 'IFRAME' && isVbUrl(node.getAttribute('src'))) {
                    var url = node.getAttribute('src');
                    console.info('[OverCMS] VB iframe (observer) blocked:', url);
                    node.setAttribute('src', 'about:blank');
                    node.remove();
                    openInNewTab(url);
                }
                // Iframe zagnieżdżony w dodanym wezle
                var nested = node.querySelectorAll && node.querySelectorAll('iframe[src*="et_fb=1"], iframe[src*="et_tb=1"]');
                if (nested && nested.length) {
                    nested.forEach(function(ifr) {
                        var url = ifr.getAttribute('src');
                        ifr.setAttribute('src', 'about:blank');
                        ifr.remove();
                        openInNewTab(url);
                    });
                }
            });
        });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });

    // 3. Override window.open — niektore akcje TB uzywaja window.open
    var origOpen = window.open;
    window.open = function(url, target, features) {
        if (isVbUrl(url)) {
            return origOpen.call(this, url, '_blank', features || 'noopener,noreferrer');
        }
        return origOpen.apply(this, arguments);
    };

    // 4. Intercept klikniec linkow z VB URL (gdyby Divi uzywal <a href>)
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a');
        if (!link || !isVbUrl(link.href)) return;
        e.preventDefault();
        e.stopPropagation();
        openInNewTab(link.href);
    }, true);
})();
</script>
        <?php
    }
}
