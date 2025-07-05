<?php
if (!defined('ABSPATH')) exit;

// Agendamento dinâmico baseado no valor definido no admin para o feed de imagens
add_action('init', function() {
    $interval = get_option('dc_image_feed_interval_hours', 24); // Intervalo padrão de 24 horas

    add_filter('cron_schedules', function($schedules) use ($interval) {
        $schedules['dc_dynamic_image_hours'] = [
            'interval' => $interval * 3600,
            'display' => "A cada $interval horas para Imagens"
        ];
        return $schedules;
    });

    if (!wp_next_scheduled('dc_feed_random_image_event')) {
        wp_schedule_event(time(), 'dc_dynamic_image_hours', 'dc_feed_random_image_event');
    }
});

// Reagendar e sortear nova imagem ao mudar o intervalo
add_action('update_option_dc_image_feed_interval_hours', function($old_value, $value) {
    wp_clear_scheduled_hook('dc_feed_random_image_event');
    wp_schedule_event(time(), 'dc_dynamic_image_hours', 'dc_feed_random_image_event');
    do_action('dc_feed_random_image_event'); // Força a atualização imediatamente
}, 10, 2);


// Sorteio de imagem aleatória para o feed
add_action('dc_feed_random_image_event', function() {
    $selected_image_ids_str = get_option('dc_selected_image_ids', '');
    $selected_image_ids = array_filter(array_map('intval', explode(',', $selected_image_ids_str)));

    if (empty($selected_image_ids)) {
        update_option('dc_feed_current_image', 0); // Limpa se não houver imagens selecionadas
        return;
    }

    $previous_image_id = get_option('dc_feed_current_image');
    $max_attempts = 5;
    $attempt = 0;
    $new_image_id = 0;

    do {
        $random_key = array_rand($selected_image_ids);
        $potential_new_image_id = $selected_image_ids[$random_key];

        // Verifica se a imagem ainda existe e é um anexo válido
        if (wp_attachment_is_image($potential_new_image_id)) {
            $new_image_id = $potential_new_image_id;
        } else {
            // Se a imagem não for válida, remove do array de selecionadas para evitar futuras tentativas
            $key_to_remove = array_search($potential_new_image_id, $selected_image_ids);
            if ($key_to_remove !== false) {
                unset($selected_image_ids[$key_to_remove]);
                update_option('dc_selected_image_ids', implode(',', $selected_image_ids));
            }
        }
        $attempt++;
    } while (($new_image_id === 0 || $new_image_id === $previous_image_id) && $attempt < $max_attempts && !empty($selected_image_ids));


    if ($new_image_id) {
        update_option('dc_feed_current_image', $new_image_id);
    } else {
        update_option('dc_feed_current_image', 0); // Nenhuma imagem válida encontrada
    }
});


// Regras de reescrita para o feed de imagens
add_action('init', function() {
    add_rewrite_rule('^image-feed-social\.xml$', 'index.php?dc_custom_image_feed=1', 'top');
    add_rewrite_tag('%dc_custom_image_feed%', '1');
});


// Geração do feed XML de imagens
add_action('template_redirect', function() {
    if (get_query_var('dc_custom_image_feed') == '1') {
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'), true);
        echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . ">\n";
        ?>
<rss version="2.0">
<channel>
    <title><?php bloginfo_rss('name'); ?> - Feed de Imagens Social</title>
    <link><?php bloginfo_rss('url'); ?></link>
    <description>Imagem aleatória da biblioteca atualizada por agendamento</description>
    <language><?php bloginfo_rss('language'); ?></language>
    <pubDate><?php echo date(DATE_RSS); ?></pubDate>
<?php
    $image_id = get_option('dc_feed_current_image');
    if ($image_id && wp_attachment_is_image($image_id)) :
        $image_url = wp_get_attachment_url($image_id);
        $image_title = get_the_title($image_id);
        $image_description = get_post_field('post_content', $image_id); // Usa post_content como descrição
        $guid = $image_url;
        $pubDate = get_post_time('r', false, $image_id);
?>
    <item>
        <title><?php echo esc_html($image_title); ?></title>
        <link><?php echo esc_url($image_url); ?></link>
        <guid isPermaLink="true"><?php echo esc_url($guid); ?></guid>
        <pubDate><?php echo $pubDate; ?></pubDate>
        <description><![CDATA[<?php echo esc_html($image_description); ?>]]></description>
        <image_link><?php echo esc_url($image_url); ?></image_link>
    </item>
<?php
    endif;
?>
</channel>
</rss>
<?php
        exit;
    }
});

// Adiciona a sub-página no admin
add_action('admin_menu', function() {
    add_options_page(
        'Configurações do Feed de Imagens',
        'Image Feed',
        'manage_options',
        'dc_image_feed',
        'dc_image_feed_settings_page'
    );
});

function dc_image_feed_settings_page() {
    // Processa o salvamento das opções
    if (isset($_POST['submit'])) {
        check_admin_referer('dc_image_feed_settings_nonce'); // Nonce de segurança

        $selected_ids = isset($_POST['dc_selected_image_ids']) ? array_map('intval', $_POST['dc_selected_image_ids']) : [];
        update_option('dc_selected_image_ids', implode(',', $selected_ids));

        $interval = isset($_POST['dc_image_feed_interval_hours']) ? absint($_POST['dc_image_feed_interval_hours']) : 24;
        update_option('dc_image_feed_interval_hours', $interval);

        echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas!</p></div>';
    }

    $selected_image_ids_str = get_option('dc_selected_image_ids', '');
    $selected_image_ids = array_filter(array_map('intval', explode(',', $selected_image_ids_str)));

    // Obtém todas as imagens da biblioteca de mídia
    $args = array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'posts_per_page' => -1, // Retorna todas as imagens
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    $images = get_posts($args);

    $current_interval = get_option('dc_image_feed_interval_hours', 24);

    ?>
    <div class="wrap">
        <h1>Configurações do Feed de Imagens</h1>
        <form method="post" action="">
            <?php wp_nonce_field('dc_image_feed_settings_nonce'); ?>

            <h2>Configurações de Intervalo</h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="dc_image_feed_interval_hours">Intervalo entre imagens (em horas)</label></th>
                    <td>
                        <input type="number" id="dc_image_feed_interval_hours" name="dc_image_feed_interval_hours" value="<?php echo esc_attr($current_interval); ?>" min="1" />
                        <p class="description">Defina a frequência de atualização do feed de imagens.</p>
                    </td>
                </tr>
            </table>

            <h2>Seleção de Imagens</h2>
            <p>Selecione as imagens que farão parte do seu feed.</p>
            <div id="image-selection-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                <?php if ($images) : ?>
                    <?php foreach ($images as $image) :
                        $thumbnail_url = wp_get_attachment_image_url($image->ID, 'thumbnail');
                        $full_url = wp_get_attachment_url($image->ID);
                        $is_selected = in_array($image->ID, $selected_image_ids);
                        ?>
                        <div style="border: 1px solid #ccc; padding: 10px; text-align: center; <?php echo $is_selected ? 'background-color: #e0ffe0;' : ''; ?>">
                            <label>
                                <input type="checkbox" name="dc_selected_image_ids[]" value="<?php echo esc_attr($image->ID); ?>" <?php checked($is_selected); ?> />
                                <img src="<?php echo esc_url($thumbnail_url); ?>" alt="<?php echo esc_attr($image->post_title); ?>" style="max-width: 100%; height: auto; display: block; margin: 0 auto 5px;" title="<?php echo esc_attr($image->post_title); ?>" />
                                <span style="font-size: 0.8em;"><?php echo esc_html($image->post_title); ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                    <p>Nenhuma imagem encontrada na biblioteca de mídia.</p>
                <?php endif; ?>
            </div>
            <?php submit_button('Salvar Configurações'); ?>
        </form>
    </div>
    <?php
}
