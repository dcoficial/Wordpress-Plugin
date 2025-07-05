<?php
// Segurança: bloquear acesso direto
if (!defined('ABSPATH')) exit;

/**
 * 1 Remover cabeçalhos sensíveis
 */
add_filter('wp_headers', function($headers) {
    unset($headers['X-Pingback']);
    return $headers;
});

// Remover Server e X-Powered-By (alguns servidores podem já bloquear isso no nível do Apache/Nginx)
@header_remove('Server');
@header_remove('X-Powered-By');

/**
 * 2 Bloquear enumeração de autores via URL ?author=
 */
if (!is_admin()) {
    add_action('init', function() {
        if (isset($_GET['author']) && is_numeric($_GET['author'])) {
            wp_redirect(home_url(), 301);
            exit;
        }
    });
}

/**
 * 3 Limitar o acesso ao REST API para visitantes não logados
 * (Mantém funcionamento do Gutenberg e painel admin)
 */
add_filter('rest_authentication_errors', function($result) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_disabled', 'REST API desativada para visitantes.', array('status' => 403));
    }
    return $result;
});

/**
 * 4 Desabilitar pingbacks via XML-RPC (muito usado em ataques DDoS)
 */
add_filter('xmlrpc_methods', function($methods) {
    unset($methods['pingback.ping']);
    return $methods;
});

/**
 * 5 Evitar exposição de informações de PHP (failsafe)
 */
@ini_set('display_errors', '0');
@ini_set('expose_php', '0');
