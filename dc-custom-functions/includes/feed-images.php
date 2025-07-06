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
    // Ação acionada APENAS quando a opção 'dc_image_feed_interval_hours' é atualizada.
    // Isso garante que o feed só é forçado a mudar quando a frequência é alterada.
    wp_clear_scheduled_hook('dc_feed_random_image_event');
    wp_schedule_event(time(), 'dc_dynamic_image_hours', 'dc_feed_random_image_event');
    do_action('dc_feed_random_image_event'); // Força a atualização imediatamente
}, 10, 2);


// Sorteio de imagem aleatória para o feed
add_action('dc_feed_random_image_event', function() {
    $selected_image_ids_str = get_option('dc_selected_image_ids', '');
    $selected_image_ids = array_filter(array_map('intval', explode(',', $selected_image_ids_str)));

    error_log("DC Image Feed Cron: Running. Selected IDs string: " . $selected_image_ids_str); // Debugging

    if (empty($selected_image_ids)) {
        update_option('dc_feed_current_image', 0); // Limpa se não houver imagens selecionadas
        error_log("DC Image Feed Cron: No images selected. Current image set to 0."); // Debugging
        return;
    }

    $previous_image_id = get_option('dc_feed_current_image');
    $max_attempts = 5;
    $attempt = 0;
    $new_image_id = 0;
    $valid_selected_ids = []; // Para armazenar IDs válidos durante o processo de seleção

    // Filtra IDs inválidos de antemão para eficiência e melhor lógica
    foreach ($selected_image_ids as $id) {
        if (wp_attachment_is_image($id)) {
            $valid_selected_ids[] = $id;
        }
    }

    if (empty($valid_selected_ids)) {
        update_option('dc_feed_current_image', 0);
        update_option('dc_selected_image_ids', ''); // Limpa seleções inválidas
        error_log("DC Image Feed Cron: All previously selected images are invalid. Current image set to 0 and selections cleared."); // Debugging
        return;
    }

    // Garante que a opção `dc_selected_image_ids` contenha apenas IDs de imagens válidas
    // Isso é importante se algumas imagens foram deletadas após a seleção.
    if (count($valid_selected_ids) !== count($selected_image_ids)) {
        update_option('dc_selected_image_ids', implode(',', $valid_selected_ids));
        error_log("DC Image Feed Cron: Updated selected_image_ids to contain only valid image IDs."); // Debugging
    }


    do {
        $random_key = array_rand($valid_selected_ids);
        $potential_new_image_id = $valid_selected_ids[$random_key];
        $new_image_id = $potential_new_image_id; // Como pré-filtramos, isso deve ser válido
        $attempt++;
    } while ($new_image_id === $previous_image_id && count($valid_selected_ids) > 1 && $attempt < $max_attempts); // Tenta novamente apenas se houver mais de uma imagem válida

    if ($new_image_id) {
        update_option('dc_feed_current_image', $new_image_id);
        error_log("DC Image Feed Cron: New image ID selected: " . $new_image_id); // Debugging
    } else {
        update_option('dc_feed_current_image', 0); // Não deve acontecer se valid_selected_ids não estiver vazio
        error_log("DC Image Feed Cron: Failed to select a new image ID."); // Debugging
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
    error_log("DC Image Feed XML: Attempting to generate XML for image ID: " . $image_id); // Debugging

    if ($image_id && wp_attachment_is_image($image_id)) :
        $image_url = wp_get_attachment_url($image_id);
        $image_title = get_the_title($image_id);
        $image_description = get_post_field('post_content', $image_id);
        if (empty($image_description)) {
            $image_description = $image_title; // Fallback para o título se a descrição estiver vazia
        }
        $guid = $image_url;
        $pubDate = get_post_time('r', false, $image_id);
?>
    <item>
        <title><?php echo esc_html($image_title); ?></title>
        <link><?php echo esc_url($image_url); ?></link>
        <guid isPermaLink="true"><?php echo esc_url($guid); ?></guid>
        <pubDate><?php echo $pubDate; ?></pubDate>
        <description><![CDATA[<?php echo esc_html($image_description); ?>]]></description>
        <image_id><?php echo esc_html($image_id); ?></image_id>
        <image_url><?php echo esc_url($image_url); ?></image_url>
    </item>
<?php
    else :
        // Comentário de depuração no XML se nenhuma imagem válida for encontrada
?>
    <?php
    endif;
?>
</channel>
</rss>
<?php
        exit;
    }
});

// Adiciona a página de configurações de imagem no admin como um item de menu de nível superior
add_action('admin_menu', function() {
    add_options_page(
        'Configurações do Feed de Imagens', // Page Title
        'Image Feed', // Menu Title - Será um item de menu de nível superior em 'Configurações'
        'manage_options', // Capability
        'dc_image_feed', // Menu Slug (DEVE ser diferente de 'dc_feed_social')
        'dc_image_feed_settings_page' // Callback function
    );
});

function dc_image_feed_settings_page() {
    // Processa o salvamento das opções
    if (isset($_POST['submit'])) {
        check_admin_referer('dc_image_feed_settings_nonce'); // Nonce de segurança

        $selected_ids_input = isset($_POST['dc_selected_image_ids']) ? (array) $_POST['dc_selected_image_ids'] : [];
        $selected_ids = array_filter(array_map('intval', $selected_ids_input)); // Garante que sejam inteiros e remove vazios

        // Antes de salvar, filtra quaisquer anexos inexistentes ou não-imagem da lista selecionada
        $valid_selected_ids = [];
        foreach ($selected_ids as $id) {
            if (wp_attachment_is_image($id)) {
                $valid_selected_ids[] = $id;
            } else {
                error_log("DC Image Feed Admin: Invalid image ID " . $id . " removed from selection."); // Debugging
            }
        }

        // Salva as imagens selecionadas
        update_option('dc_selected_image_ids', implode(',', $valid_selected_ids));

        // Guarda o valor antigo do intervalo para comparar
        $old_interval = get_option('dc_image_feed_interval_hours', 24);
        $new_interval = isset($_POST['dc_image_feed_interval_hours']) ? absint($_POST['dc_image_feed_interval_hours']) : 24;
        
        // Salva o novo intervalo. Ação do hook 'update_option_dc_image_feed_interval_hours' cuidará da geração do feed.
        update_option('dc_image_feed_interval_hours', $new_interval);

        echo '<div class="notice notice-success is-dismissible"><p>Configurações salvas!</p></div>';

        // Linha removida: do_action('dc_feed_random_image_event');
        // A geração forçada do feed agora ocorre APENAS quando a opção de intervalo é atualizada (via o hook 'update_option_dc_image_feed_interval_hours').
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
        <hr>
        <p><strong>URL do Feed de Imagens:</strong> <code><?php echo esc_url(home_url('/image-feed-social.xml')); ?></code></p>
        <p><strong>Importante:</strong> Após a instalação inicial deste plugin ou se o feed não estiver funcionando, por favor, vá em <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>">Configurações > Links Permanentes</a> e clique em "Salvar Alterações" para atualizar as regras de reescrita do WordPress.</p>
    </div>
    <?php
}
