<?php

namespace OverCMS\Core;

/**
 * Kompatybilność z Divi Visual Builderem w kontekście panelu OverCMS.
 *
 * Problem: panel OverCMS osadza Divi Theme Builder w <iframe> z
 * parametrem ?overcms_embed=1. Klikniecie "Edit Layout" w Theme Builderze
 * probuje zaladowac VB w KOLEJNYM iframe (iframe-in-iframe). Browser/Divi
 * gubia nonce/auth, VB wisi w nieskonczonym loaderze.
 *
 * Fix: na stronie Theme Buildera (gdy w embed mode) przechwytujemy klikniecia
 * na linki/buttons otwierajace VB i otwieramy je w NOWEJ KARCIE. User edytuje
 * layout w pelnym oknie, po zapisaniu zamyka karte i wraca do panelu OverCMS.
 */
final class DiviCompatibility
{
    public static function register(): void
    {
        add_action('admin_print_footer_scripts', [self::class, 'interceptThemeBuilderLinks']);
    }

    /**
     * Theme Builder admin page w embed mode: linki i window.open z et_fb=1
     * otwieraja VB. Wymuszamy otwieranie w nowej karcie zeby ominac
     * iframe-in-iframe context z panelu OverCMS.
     */
    public static function interceptThemeBuilderLinks(): void
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
        return url && (url.indexOf('et_fb=1') !== -1 || url.indexOf('et_tb=1') !== -1);
    }

    // Intercept klikniec na linki uruchamiajace VB
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a');
        if (!link || !isVbUrl(link.href)) return;
        e.preventDefault();
        e.stopPropagation();
        window.open(link.href, '_blank', 'noopener');
    }, true);

    // Theme Builder uzywa tez window.open z poziomu React/Backbone — wymus _blank
    var origOpen = window.open;
    window.open = function(url, target, features) {
        if (isVbUrl(url)) {
            return origOpen.call(this, url, '_blank', features || 'noopener');
        }
        return origOpen.apply(this, arguments);
    };
})();
</script>
        <?php
    }
}
