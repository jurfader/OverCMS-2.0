<?php

namespace OverCMS\Core;

/**
 * Wydajność strony publicznej: defer skryptów, preconnect, lazy-load.
 */
final class PerformanceOptimizer
{
    public static function register(): void
    {
        // Preconnect do Google Fonts
        add_filter('wp_resource_hints', [self::class, 'addResourceHints'], 10, 2);

        // Defer dla nie-krytycznych skryptów na froncie
        add_filter('script_loader_tag', [self::class, 'deferScripts'], 10, 3);

        // Włącz lazy-load dla iframe (obrazy są lazy domyślnie od WP 5.5)
        add_filter('wp_lazy_loading_enabled', '__return_true');

        // Wyłącz blokowy CSS Gutenberga na froncie jeśli strona nie używa bloków
        add_action('wp_enqueue_scripts', [self::class, 'maybeDequeueGutenbergCss'], 100);
    }

    public static function addResourceHints(array $hints, string $relation): array
    {
        if (is_admin()) {
            return $hints;
        }
        if ($relation === 'preconnect') {
            $hints[] = ['href' => 'https://fonts.googleapis.com', 'crossorigin'];
            $hints[] = ['href' => 'https://fonts.gstatic.com', 'crossorigin'];
        }
        return $hints;
    }

    public static function deferScripts(string $tag, string $handle, string $src): string
    {
        if (is_admin()) {
            return $tag;
        }

        // Skrypty krytyczne — zostaw bez defer
        $critical = ['jquery-core', 'jquery-migrate'];
        if (in_array($handle, $critical, true)) {
            return $tag;
        }

        // Skrypty Divi visual builder — nie ruszaj
        if (str_contains($handle, 'et-builder') || str_contains($handle, 'divi')) {
            return $tag;
        }

        if (str_contains($tag, ' defer') || str_contains($tag, ' async')) {
            return $tag;
        }

        return str_replace('<script ', '<script defer ', $tag);
    }

    public static function maybeDequeueGutenbergCss(): void
    {
        // Tylko strony bez bloków — Divi nie używa Gutenberga
        if (is_singular() && !has_blocks(get_queried_object_id())) {
            wp_dequeue_style('wp-block-library');
            wp_dequeue_style('wp-block-library-theme');
            wp_dequeue_style('global-styles');
            wp_dequeue_style('classic-theme-styles');
        }
    }
}
