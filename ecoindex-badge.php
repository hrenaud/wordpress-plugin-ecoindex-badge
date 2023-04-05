<?php
/*
Plugin Name: Ecoindex Badge
Plugin URI: https://mon-site.com/plugins/ecoindex-badge/
Description: Ce plugin ajoute le badge Ecoindex à la fin de votre site.
Version: 1.0
Author: Votre nom
Author URI: https://ecoindex.fr/
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ecoindex-badge
*/

// Affiche le contenu de la page de configuration pour le badge Ecoindex
function ecoindex_badge_render_column($column_name, $post_id) {
    if ($column_name == 'ecoindex_badge') {
        $url = get_permalink($post_id);
        $theme = get_option('ecoindex_badge_theme');
        ?>
        <a href="https://bff.ecoindex.fr/redirect/?url=<?php echo esc_attr($url); ?>" target="_blank">
            <img src="https://bff.ecoindex.fr/badge/?theme=<?php echo esc_attr($theme); ?>&url=<?php echo esc_attr($url); ?>" alt="Ecoindex Badge" />
        </a>
        <?php
    }
}

function ecoindex_badge_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'ecoindex_badge_settings_group' ); ?>
            <?php do_settings_sections( 'ecoindex_badge_settings_page' ); ?>
            <?php submit_button(); ?>
        </form>
         <h2>Liste des pages et articles</h2>
        <?php
            $pages = get_pages();
            $posts = get_posts(array(
                'post_type' => 'post',
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ));
            
            if (count($pages) > 0 || count($posts) > 0) {
        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Titre</th>
                    <th>Lien</th>
                    <th>Ecoindex Badge</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page) { ?>
                <tr>
                    <td>Page</td>
                    <td><?php echo esc_html($page->post_title); ?></td>
                    <td><a href="<?php echo esc_url(get_permalink($page->ID)); ?>">Voir</a></td>
                    <td><?php ecoindex_badge_render_column('ecoindex_badge', $page->ID); ?></td>
                </tr>
                <?php } ?>
                <?php foreach ($posts as $post) { ?>
                <tr>
                    <td>Article</td>
                    <td><?php echo esc_html($post->post_title); ?></td>
                    <td><a href="<?php echo esc_url(get_permalink($post->ID)); ?>">Voir</a></td>
                    <td><?php ecoindex_badge_render_column('ecoindex_badge', $post->ID); ?></td>
                </tr>
                <?php } ?>
            </tbody>
        </table>
        <?php } else { ?>
        <p>Aucune page ni article trouvé.</p>
        <?php } ?>
    </div>
    <?php
}

// Ajoute la section et le champ pour le thème du badge Ecoindex dans la page de configuration
function ecoindex_badge_settings_init() {
    register_setting( 'ecoindex_badge_settings_group', 'ecoindex_badge_data_theme' );
    add_settings_section( 'ecoindex_badge_settings_section', 'Options du badge', '', 'ecoindex_badge_settings_page' );
    add_settings_field( 'ecoindex_badge_data_theme', 'Thème du badge', 'ecoindex_badge_data_theme_callback', 'ecoindex_badge_settings_page', 'ecoindex_badge_settings_section' );
}
add_action( 'admin_init', 'ecoindex_badge_settings_init' );

// Affiche le champ pour le thème du badge Ecoindex dans la page de configuration
function ecoindex_badge_data_theme_callback() {
    $value = get_option( 'ecoindex_badge_data_theme', 'light' );
    ?>
    <select name="ecoindex_badge_data_theme">
        <option value="light"<?php selected( $value, 'light' ); ?>>Light</option>
        <option value="dark"<?php selected( $value, 'dark' ); ?>>Dark</option>
    </select>
    <?php
}

// Ajoute le badge Ecoindex en bas à gauche de chaque page du site
function ecoindex_badge_add() {
    $data_theme = get_option( 'ecoindex_badge_data_theme', 'light' );
    echo '<div id="ecoindex-badge" data-theme="' . esc_attr( $data_theme ) . '"></div>';
    echo '<script src="https://cdn.jsdelivr.net/gh/cnumr/ecoindex_badge@2/assets/js/ecoindex-badge.js" defer></script>';
    echo '<style>#ecoindex-badge {position: fixed; bottom: 10px; left: 10px;}</style>';
}
add_action( 'wp_footer', 'ecoindex_badge_add' );




// Ajoute une page de configuration pour le badge Ecoindex
function ecoindex_badge_menu() {
    add_menu_page( 'Ecoindex Badge Settings', 'Ecoindex Badge', 'manage_options', 'ecoindex-badge-settings', 'ecoindex_badge_settings_page', 'dashicons-superhero' );
}
add_action( 'admin_menu', 'ecoindex_badge_menu' );

// Ajoute une colonne Ecoindex dans la liste des articles
function ecoindex_badge_pages_column($columns) {
    $columns['ecoindex_badge'] = 'Ecoindex';
    return $columns;
}
add_filter('manage_pages_columns', 'ecoindex_badge_pages_column');

function ecoindex_badge_pages_content($column_name, $post_id) {
    if ($column_name == 'ecoindex_badge') {
        $url = get_permalink($post_id);
        $theme = get_option('ecoindex_badge_data_theme', 'light');
        $badge = '<a href="https://bff.ecoindex.fr/redirect/?url=' . urlencode($url) . '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' . urlencode($theme) . '&url=' . urlencode($url) . '" alt="Ecoindex Badge" /></a>';
        echo $badge;
    }
}
add_action('manage_pages_custom_column', 'ecoindex_badge_pages_content', 10, 2);

function ecoindex_badge_posts_column($columns) {
    $columns['ecoindex_badge'] = 'Ecoindex';
    return $columns;
}
add_filter('manage_posts_columns', 'ecoindex_badge_posts_column');

function ecoindex_badge_posts_content($column_name, $post_id) {
    if ($column_name == 'ecoindex_badge') {
        $url = get_permalink($post_id);
        $theme = get_option('ecoindex_badge_data_theme', 'light');
        $badge = '<a href="https://bff.ecoindex.fr/redirect/?url=' . urlencode($url) . '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' . urlencode($theme) . '&url=' . urlencode($url) . '" alt="Ecoindex Badge" /></a>';
        echo $badge;
    }
}
add_action('manage_posts_custom_column', 'ecoindex_badge_posts_content', 10, 2);

