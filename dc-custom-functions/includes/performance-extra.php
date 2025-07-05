<?php
if (!defined('ABSPATH')) exit;

// Remove wp-embed.min.js
add_action('wp_footer', function() {
    wp_deregister_script('wp-embed');
});

// Preconnect e dns-prefetch
add_action('wp_head', function() {
    echo "\n";
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    echo '<link rel="preconnect" href="https://cdn.seudominio.com">' . "\n";
    echo '<link rel="dns-prefetch" href="//fonts.googleapis.com">' . "\n";
    echo '<link rel="dns-prefetch" href="//fonts.gstatic.com">' . "\n";
    echo '<link rel="dns-prefetch" href="//cdn.seudominio.com">' . "\n";
}, 1);

// Font-display: swap para fontes externas
add_filter('style_loader_tag', function($html, $handle) {
    if (strpos($html, 'fonts.googleapis.com') !== false) {
        $html = str_replace("rel='stylesheet'", "rel='stylesheet' onload=\"this.onload=null;this.rel='stylesheet'\"", $html);
    }
    return $html;
}, 10, 2);
