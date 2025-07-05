<?php
// Segurança: bloquear acesso direto
if (!defined('ABSPATH')) exit;

/**
 * Desativação completa de comentários em todo o site
 */

/**
 * 1 Redireciona tentativas de acessar a página de comentários no admin
 */
add_action('admin_init', function () {
    global $pagenow;
    if ($pagenow === 'edit-comments.php') {
        wp_safe_redirect(admin_url());
        exit;
    }

    // Remove o widget de comentários recentes do dashboard
    remove_meta_box('dashboard_recent_comments', 'dashboard', 'normal');

    // Remove suporte a comentários e trackbacks de todos os tipos de post
    foreach (get_post_types() as $post_type) {
        if (post_type_supports($post_type, 'comments')) {
            remove_post_type_support($post_type, 'comments');
            remove_post_type_support($post_type, 'trackbacks');
        }
    }
});

/**
 * 2 Força o fechamento de comentários e pingbacks globalmente
 */
add_filter('comments_open', '__return_false', 20, 2);
add_filter('pings_open', '__return_false', 20, 2);

/**
 * 3 Oculta comentários existentes no frontend
 */
add_filter('comments_array', '__return_empty_array', 10, 2);

/**
 * 4 Remove o menu de comentários do painel administrativo
 */
add_action('admin_menu', function () {
    remove_menu_page('edit-comments.php');
});

/**
 * 5 Remove o item de comentários da barra de administração
 */
add_action('init', function () {
    if (is_admin_bar_showing()) {
        remove_action('admin_bar_menu', 'wp_admin_bar_comments_menu', 60);
    }
});
