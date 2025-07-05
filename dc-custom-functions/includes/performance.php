<?php
// Segurança: bloquear acesso direto
if (!defined('ABSPATH')) exit;

/**
 *  Desativar Emojis (remove scripts, styles e filtros relacionados)
 */
remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');
remove_filter('the_content_feed', 'wp_staticize_emoji');
remove_filter('comment_text_rss', 'wp_staticize_emoji');
remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

add_filter('tiny_mce_plugins', function($plugins) {
    return array_diff($plugins, ['wpemoji']);
});

/**
 * 2 Reduzir a frequência do Heartbeat API (controla chamadas AJAX periódicas)
 */
add_filter('heartbeat_settings', function($settings) {
    $settings['interval'] = 60; // Intervalo de 60 segundos (pode ajustar)
    return $settings;
});

/**
 * 3 Remover query strings de versões em scripts e styles (ajuda no cache)
 */
function dc_remove_version_query_strings($src) {
    return remove_query_arg('ver', $src);
}
add_filter('script_loader_src', 'dc_remove_version_query_strings', 15, 1);
add_filter('style_loader_src', 'dc_remove_version_query_strings', 15, 1);

/**
 * 4 Desativar o lazy-load nativo do WordPress
 */
add_filter('wp_lazy_loading_enabled', '__return_false');

/**
 * 5 Aplicar lazy-load manual somente abaixo da primeira dobra
 * (exclui as primeiras imagens definidas por $skip_images)
 */
add_filter('the_content', function($content) {
    $skip_images = 2; // Número de imagens iniciais sem lazy-load
    $image_count = 0;

    return preg_replace_callback('/<img[^>]+>/i', function($matches) use (&$image_count, $skip_images) {
        $image_count++;
        if ($image_count <= $skip_images) {
            return $matches[0]; // não aplica lazy-load nas primeiras imagens
        } else {
            if (strpos($matches[0], 'loading=') === false) {
                return preg_replace('/<img/i', '<img loading="lazy"', $matches[0], 1);
            }
            return $matches[0];
        }
    }, $content);
});
