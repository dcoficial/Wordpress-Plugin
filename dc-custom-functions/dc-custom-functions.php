<?php
/*
Plugin Name: DC Custom Functions
Description: Plugin modular de otimizações profissionais para WordPress.
Version: 2.1.0
Author: Daniel Costa
License: GPLv2 or later
*/

if (!defined('ABSPATH')) exit;

// Caminho base do plugin
define('DC_CUSTOM_FUNCTIONS_PATH', plugin_dir_path(__FILE__));

/**
 * Cancelar cron ao desativar o plugin
 */
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('dc_feed_random_post_event');
    // Adicionar a limpeza para o novo cron do feed de imagens
    wp_clear_scheduled_hook('dc_feed_random_image_event');
});

/**
 * Carregar os módulos
 */
add_action('plugins_loaded', function() {
    require_once DC_CUSTOM_FUNCTIONS_PATH . 'includes/security.php';
    require_once DC_CUSTOM_FUNCTIONS_PATH . 'includes/performance.php';
    require_once DC_CUSTOM_FUNCTIONS_PATH . 'includes/performance-extra.php';
    require_once DC_CUSTOM_FUNCTIONS_PATH . 'includes/comments.php';
    require_once DC_CUSTOM_FUNCTIONS_PATH . 'includes/feed-social.php';
    require_once DC_CUSTOM_FUNCTIONS_PATH . 'includes/feed-images.php';
});
