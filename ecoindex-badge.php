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

// Cette fonction ajoute un slash à la fin de l'URL si celui-ci n'existe pas.
function add_trailing_slash( $url ) {
    if ( substr( $url, -1 ) !== '/' ) {
        $url .= '/';
    }
    return $url;
}


// Cette fonction affiche soit un badge Ecoindex, soit un bouton "Mesurer" pour une colonne donnée dans une liste de publication WordPress.
function ecoindex_badge_render_column( $column_name, $post_id ) {
    if ( $column_name == 'Ecoindex' ) {
        $url = get_permalink( $post_id );
        if ( empty( $url ) ) {
            $url = home_url();
        }
        $theme = get_option('ecoindex_badge_data_theme', 'light');
        $badge_code = '<a href="https://bff.ecoindex.fr/redirect/?url=' . add_trailing_slash( $url ) . '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' . $theme . '&url=' . add_trailing_slash( $url ) . '" alt="Ecoindex Badge" /></a>';
        echo $badge_code;
    }
    elseif ( $column_name == 'Mesurer' ) {
        $url = get_permalink( $post_id );
        if ( empty( $url ) ) {
            $url = home_url();
        }
        echo '<button class="button button-primary ecoindex-measure-button" data-page-url="' . add_trailing_slash( esc_url( $url ) ) . '">Mesurer</button>';
    }
}

// Cette fonction gère la requête AJAX pour envoyer les données de mesure de l'empreinte environnementale d'une page ou d'un article, en utilisant l'API Ecoindex. Les données envoyées sont l'URL de la page, la largeur et la hauteur de la fenêtre de visualisation, et la fonction renvoie l'ID de la tâche Ecoindex correspondante.
function ecoindex_measure_click_handler() {
    $url = $_POST['url'];
    $width = $_POST['width'];
    $height = $_POST['height'];

    $response = wp_remote_post('https://bff.ecoindex.fr/api/tasks', array(
        'body' => json_encode(array(
        'url' => $url,
        'width' => $width,
        'height' => $height,
        )),
        'headers' => array(
        'Content-Type' => 'application/json',
        ),
    ));
    
    if ( is_wp_error( $response ) ) {
        $error_message = $response->get_error_message();
        echo "Something went wrong: $error_message";
        wp_die();
    } else {
        // var_dump($response);
        $data = json_decode( wp_remote_retrieve_body( $response ) );
        $taskId = $data;

        echo $taskId;
    }
    wp_die();
}

add_action( 'wp_ajax_ecoindex_measure', 'ecoindex_measure_click_handler' );
add_action( 'wp_ajax_nopriv_ecoindex_measure', 'ecoindex_measure_click_handler' );

// Cette fonction enregistre le script JavaScript qui permet de lancer une mesure Ecoindex pour chaque page ou article. Le script est localisé pour pouvoir envoyer des requêtes AJAX avec les paramètres nécessaires.
function ecoindex_measure_scripts() {
    wp_enqueue_script( 'ecoindex-measure', plugin_dir_url( __FILE__ ) . 'js/ecoindex-measure.js', array( 'jquery' ), '1.0.0', true );
    wp_localize_script('ecoindex-measure', 'ecoindex_measure_params', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('ecoindex_measure_nonce')
));
}
add_action( 'admin_enqueue_scripts', 'ecoindex_measure_scripts' );

// Cette fonction génère la page des paramètres du plugin Ecoindex Badge, affiche une liste de toutes les pages et articles du site avec leur titre, leur lien, leur score Ecoindex et un bouton pour mesurer leur impact environnemental.
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

            // Obtenir l'ID de la page d'accueil
            $front_page_id = get_option('page_on_front');
            
            // Si la page d'accueil est une page statique
            if ( 'page' == get_option('show_on_front') && $front_page_id ) {
                // Ajouter la page d'accueil à la liste
                $pages[] = get_post($front_page_id);
            } else { // Si la page d'accueil est la page des derniers articles
                // Ajouter un faux objet pour représenter la page d'accueil
                $home = new stdClass();
                $home->post_title = 'Accueil';
                $home->ID = get_option('page_for_posts');
                $pages[] = $home;
            }
            
            if (count($pages) > 0 || count($posts) > 0) {
        ?>
        <table class="wp-list-table widefat striped">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Titre</th>
                    <th>Lien</th>
                    <th>Ecoindex</th>
                    <th>Mesurer</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pages as $page) { ?>
                <tr>
                    <td>Page</td>
                    <td><?php echo esc_html($page->post_title); ?></td>
                    <td><a href="<?php echo esc_url(get_permalink($page->ID)); ?>">Voir</a></td>
                    <td><?php ecoindex_badge_render_column('Ecoindex', $page->ID); ?></td>
                    <td><?php echo ecoindex_badge_render_column( 'Mesurer', $page->ID ); ?></td>
                </tr>
                <?php } ?>
                <?php foreach ($posts as $post) { ?>
                <tr>
                    <td>Article</td>
                    <td><?php echo esc_html($post->post_title); ?></td>
                    <td><a href="<?php echo esc_url(get_permalink($post->ID)); ?>">Voir</a></td>
                    <td><?php ecoindex_badge_render_column('Ecoindex', $post->ID); ?></td>
                    <td><?php echo ecoindex_badge_render_column( 'Mesurer', $post->ID ); ?></td>
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

// Ajoute une page de configuration pour le badge Ecoindex
function ecoindex_badge_menu() {
    add_menu_page( 'Ecoindex Badge Settings', 'Ecoindex Badge', 'manage_options', 'ecoindex-badge-settings', 'ecoindex_badge_settings_page', 'dashicons-superhero' );
}
add_action( 'admin_menu', 'ecoindex_badge_menu' );


// Ajoute le badge Ecoindex en bas à gauche de chaque page du site
function ecoindex_badge_add() {
    $data_theme = get_option( 'ecoindex_badge_data_theme', 'light' );
    $theme = get_option( 'ecoindex_badge_theme' );
    $url = home_url( $_SERVER['REQUEST_URI'] );
    echo '<a id="ecoindex-badge" href="https://bff.ecoindex.fr/redirect/?url=' . add_trailing_slash( $url ) . '" target="_blank">';
    echo '<img src="https://bff.ecoindex.fr/badge/?theme=' . $theme . '&url=' . add_trailing_slash( $url ) . '" alt="Ecoindex Badge" />';
    echo '</a>';
    echo '<style>#ecoindex-badge {position: fixed; bottom: 10px; left: 10px;}</style>';
}
add_action( 'wp_footer', 'ecoindex_badge_add' );

// Ajoute une colonne Ecoindex dans la liste des articles
function ecoindex_badge_pages_column($columns) {
    $columns['ecoindex_badge'] = 'Ecoindex';
    return $columns;
}
add_filter('manage_pages_columns', 'ecoindex_badge_pages_column');

// Cette fonction affiche le badge Ecoindex sur les pages en utilisant l'URL de la page courante avec la gestion de l'ajout du slash final et du thème personnalisé défini dans les paramètres.
function ecoindex_badge_pages_content($column_name, $post_id) {
    if ($column_name == 'ecoindex_badge' && get_post_status($post_id) == 'publish') {
        $url = get_permalink($post_id);
        $theme = get_option('ecoindex_badge_data_theme', 'light');
        $badge = '<a href="https://bff.ecoindex.fr/redirect/?url=' . add_trailing_slash( $url ) . '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' . urlencode($theme) . '&url=' . add_trailing_slash( $url ) . '" alt="Ecoindex Badge" /></a>';
        echo $badge;
    }
}
add_action('manage_pages_custom_column', 'ecoindex_badge_pages_content', 10, 2);

// La fonction "ecoindex_badge_posts_column" ajoute une colonne "Ecoindex" à la page d'administration des articles en utilisant le filtre "manage_posts_columns".
function ecoindex_badge_posts_column($columns) {
    $columns['ecoindex_badge'] = 'Ecoindex';
    return $columns;
}
add_filter('manage_posts_columns', 'ecoindex_badge_posts_column');

// Cette fonction ajoute le badge Ecoindex à la colonne 'Ecoindex' dans la liste des articles de l'interface d'administration WordPress.
function ecoindex_badge_posts_content($column_name, $post_id) {
    if ($column_name == 'ecoindex_badge' && get_post_status($post_id) == 'publish') {
        $url = get_permalink($post_id);
        $theme = get_option('ecoindex_badge_data_theme', 'light');
        $badge = '<a href="https://bff.ecoindex.fr/redirect/?url=' . add_trailing_slash( $url ) . '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' . urlencode($theme) . '&url=' . add_trailing_slash( $url ) . '" alt="Ecoindex Badge" /></a>';
        echo $badge;
    }
}
add_action('manage_posts_custom_column', 'ecoindex_badge_posts_content', 10, 2);


