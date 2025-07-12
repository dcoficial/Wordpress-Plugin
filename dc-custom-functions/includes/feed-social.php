<?php
if (!defined('ABSPATH')) exit;

// Agendamento dinâmico baseado no valor definido no admin
add_action('init', function() {
    $interval = get_option('dc_feed_interval_hours', 30);

    add_filter('cron_schedules', function($schedules) use ($interval) {
        $schedules['dc_dynamic_hours'] = [
            'interval' => $interval * 3600,
            'display' => "A cada $interval horas"
        ];
        return $schedules;
    });

    if (!wp_next_scheduled('dc_feed_random_post_event')) {
        wp_schedule_event(time(), 'dc_dynamic_hours', 'dc_feed_random_post_event');
    }
});

// Reagendar e sortear novo post ao mudar o intervalo
add_action('update_option_dc_feed_interval_hours', function($old_value, $value) {
    wp_clear_scheduled_hook('dc_feed_random_post_event');
    wp_schedule_event(time(), 'dc_dynamic_hours', 'dc_feed_random_post_event');
    do_action('dc_feed_random_post_event');
}, 10, 2);

// Sorteio de post aleatório
add_action('dc_feed_random_post_event', function() {
    $previous_post_id = get_option('dc_feed_current_post');
    $max_attempts = 5;
    $attempt = 0;
    $new_post_id = 0; // Initialize $new_post_id

    do {
        $posts = get_posts([
            'post_type'      => 'post',
            'posts_per_page' => 1,
            'orderby'        => 'rand',
            'fields'         => 'ids',
            'post_status'    => 'publish',
            // Exclude the previous post to ensure a different post is selected if possible
            'post__not_in'   => ($previous_post_id && $attempt > 0) ? [$previous_post_id] : []
        ]);

        $new_post_id = $posts ? $posts[0] : 0;
        $attempt++;

    } while ($new_post_id === $previous_post_id && $attempt < $max_attempts);

    if ($new_post_id) {
        // Unsticky the previous post if it exists and is different from the new one
        if ($previous_post_id && $previous_post_id !== $new_post_id) {
            unstick_post($previous_post_id);
            // Optional: Log this action for debugging
            // error_log("DC Feed: Unsticked previous post ID: " . $previous_post_id);
        }

        // Sticky the new post
        stick_post($new_post_id);
        update_option('dc_feed_current_post', $new_post_id);
        // Optional: Log this action for debugging
        // error_log("DC Feed: Stickied new post ID: " . $new_post_id);
    } else {
        // If no new post is selected, ensure the previous one is unstickied to avoid orphaned sticky posts
        if ($previous_post_id) {
            unstick_post($previous_post_id);
            update_option('dc_feed_current_post', 0); // Clear the option if no valid post is found
            // error_log("DC Feed: No new post selected, unstickied previous post ID: " . $previous_post_id);
        }
    }
});

// Regras de reescrita para feed
add_action('init', function() {
    add_rewrite_rule('^feed-social\.xml$', 'index.php?dc_custom_feed=1', 'top');
    add_rewrite_tag('%dc_custom_feed%', '1');
});

// Geração do feed XML
add_action('template_redirect', function() {
    if (get_query_var('dc_custom_feed') == '1') {
        header('Content-Type: application/rss+xml; charset=' . get_option('blog_charset'), true);
        echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?' . ">\n";
        ?>
<rss version="2.0">
<channel>
    <title><?php bloginfo_rss('name'); ?> - Post Feed</title>
    <link><?php bloginfo_rss('url'); ?></link>
    <description>Post aleatório atualizado por agendamento</description>
    <language><?php bloginfo_rss('language'); ?></language>
    <pubDate><?php echo date(DATE_RSS); ?></pubDate>
<?php
    $post_id = get_option('dc_feed_current_post');
    if ($post_id && get_post_status($post_id) === 'publish') :
        $post = get_post($post_id);
        $title = get_the_title($post_id);
        $link  = get_permalink($post_id);
        $guid  = $link;
        $pubDate = get_post_time('r', false, $post_id);
        $raw_excerpt = get_the_excerpt($post_id);
        $clean_excerpt = preg_replace('/\s*(\[.*?\]|&hellip;|\.\.\.)$/', '', $raw_excerpt);

        $thumbnail_id = get_post_thumbnail_id($post_id);
        $featured_image = $thumbnail_id ? wp_get_attachment_url($thumbnail_id) : '';

        $tags = get_the_tags($post_id);
        $hashtags = '';
        if ($tags) {
            foreach ($tags as $tag) {
                $hashtags .= '#' . preg_replace('/\s+/', '', mb_strtolower($tag->name)) . ' ';
            }
        }
?>
    <item>
        <title><?php echo esc_html($title); ?></title>
        <link><?php echo esc_url($link); ?></link>
        <guid isPermaLink="true"><?php echo esc_url($guid); ?></guid>
        <pubDate><?php echo $pubDate; ?></pubDate>
        <description><![CDATA[<?php echo $clean_excerpt; ?>]]></description>
<?php if ($featured_image): ?>
        <featured_image><?php echo esc_url($featured_image); ?></featured_image>
<?php endif; ?>
<?php if ($hashtags): ?>
        <hashtags><?php echo trim($hashtags); ?></hashtags>
<?php endif; ?>
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

// Tela no admin (Página principal do menu Post Feed)
add_action('admin_menu', function() {
    add_options_page(
        'Configurações do Post Feed', // Page Title
        'Post Feed', // Menu Title - Será um item de menu de nível superior em 'Configurações'
        'manage_options', // Capability
        'dc_feed_social', // Menu Slug
        'dc_feed_social_settings_page' // Callback function
    );
});

function dc_feed_social_settings_page() {
    ?>
    <div class="wrap">
        <h1>Configurações do Post Feed</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('dc_feed_social_settings');
            do_settings_sections('dc_feed_social');
            submit_button();
            ?>
        </form>
        <hr>
        <p><strong>URL do Post Feed:</strong> <code><?php echo esc_url(home_url('/feed-social.xml')); ?></code></p>
        <p><strong>Importante:</strong> Após a instalação inicial deste plugin ou se o feed não estiver funcionando, por favor, vá em <a href="<?php echo esc_url(admin_url('options-permalink.php')); ?>">Configurações > Links Permanentes</a> e clique em "Salvar Alterações" para atualizar as regras de reescrita do WordPress.</p>
        <hr>
        <p>Esta página controla o feed de posts aleatórios (<code>/feed-social.xml</code>).</p>
        </div>
    <?php
}

add_action('admin_init', function() {
    register_setting('dc_feed_social_settings', 'dc_feed_interval_hours', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 30
    ]);

    add_settings_section(
        'dc_feed_social_main',
        'Configurações de Intervalo do Post Feed',
        null,
        'dc_feed_social'
    );

    add_settings_field(
        'dc_feed_interval_hours',
        'Intervalo entre posts (em horas)',
        function() {
            $value = get_option('dc_feed_interval_hours', 30);
            echo "<input type='number' name='dc_feed_interval_hours' value='" . esc_attr($value) . "' min='1' />";
        },
        'dc_feed_social',
        'dc_feed_social_main'
    );
});
