<?php
/*
Plugin Name: Badge Ecoindex pour WordPress
Plugin URI: https://novagia.fr/
Description: Ce plugin ajoute le badge Ecoindex en bas de pages de votre site.
Version: 1.2.9
Author: Renaud Héluin @ NovaGaïa (https://ecoindex.fr/)
Author URI: https://ecoindex.fr/
License: MIT
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: ecoindex-badge
*/

define('ECOINDEX_BADGE_VERSION', '1.2.9');

if (!class_exists('WP_List_Table')) {
  require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

// Class générant un tableau de boutons
class Ecoindex_Badge_List_Table extends WP_List_Table
{
  function __construct()
  {
    global $status, $page;

    parent::__construct([
      'singular' => __('ecoindex_item', 'mylisttable'), //singular name of the listed records
      'plural' => __('ecoindex_items', 'mylisttable'), //plural name of the listed records
      'ajax' => false, //does this table support ajax?
    ]);

    add_action('admin_head', [&$this, 'admin_header']);
  }

  function get_columns()
  {
    $columns = [
      'type' => 'Type',
      'titre' => 'Titre',
      'lien' => 'Lien',
      'ecoindex' => 'Ecoindex',
      'mesurer' => 'Mesurer',
    ];
    return $columns;
  }

  function prepare_items()
  {
    $per_page = 10;
    $current_page = $this->get_pagenum();
    $pages = get_pages();
    $posts = get_posts([
      'post_type' => 'post',
      'posts_per_page' => $per_page,
      'paged' => $current_page,
      'post_status' => 'publish',
    ]);

    // Obtenir l'ID de la page d'accueil
    $front_page_id = get_option('page_on_front');

    // Si la page d'accueil est une page statique
    if ('page' == get_option('show_on_front') && $front_page_id) {
      // Ajouter la page d'accueil à la liste
      // $pages[] = get_post($front_page_id);
    } else {
      // Si la page d'accueil est la page des derniers articles
      // Ajouter un faux objet pour représenter la page d'accueil
      $home = new stdClass();
      $home->post_title = 'Accueil';
      $home->ID = get_option('page_for_posts');
      $pages[] = $home;
    }

    if (count($pages) > 0 || count($posts) > 0) {
      $items = array_merge($pages, $posts);
    }

    $columns = $this->get_columns();
    $hidden = [];
    $sortable = [];
    $this->_column_headers = [$columns, $hidden, $sortable];

    $total_items = count($items);
    $found_data = array_slice(
      $items,
      ($current_page - 1) * $per_page,
      $per_page
    );

    $this->set_pagination_args([
      'total_items' => $total_items, //WE have to calculate the total number of items
      'per_page' => $per_page, //WE have to determine how many items to show on a page
    ]);
    $this->items = $found_data;
  }

  function column_default($item, $column_name)
  {
    $hasRemaningMesure = has_remaining_daily_requests();
    switch ($column_name) {
      case 'type':
        return get_post_type($item);
      case 'titre':
        return esc_html($item->post_title);
      case 'lien':
        return '<a href=' . esc_url(get_permalink($item->ID)) . '>Voir</a>';
      case 'ecoindex':
        return ecoindex_badge_render_column(
          'Ecoindex',
          $item->ID,
          $hasRemaningMesure
        );
      case 'mesurer':
        return ecoindex_badge_render_column(
          'Mesurer',
          $item->ID,
          $hasRemaningMesure
        );
      default:
        return print_r($item, true); //Show the whole array for troubleshooting purposes
    }
  }
}

// Cette fonction ajoute un slash à la fin de l'URL si celui-ci n'existe pas.
function add_trailing_slash($url)
{
  if (substr($url, -1) !== '/') {
    $url .= '/';
  }
  return $url;
}

// Cette fonction, utilisant la méthode `get_remaining_daily_requests()` retourne un boolean indiquant si oui ou non, il en reste.
function has_remaining_daily_requests()
{
  $remaningMesure = get_remaining_daily_requests();
  $hasRemaningMesure = false;
  if (0 < $remaningMesure) {
    $hasRemaningMesure = true;
  }
  return $hasRemaningMesure;
}

// Cette fonction demande à l'API le nombre restant de mesure pour la journée
// changement à 00h00
function get_remaining_daily_requests()
{
  $host = $_SERVER['HTTP_HOST'];
  $url = "https://bff.ecoindex.fr/api/hosts/{$host}";
  $response = wp_remote_get($url);
  if (is_wp_error($response)) {
    return null;
  }
  $body = wp_remote_retrieve_body($response);
  $data = json_decode($body);
  if (!isset($data->remaining_daily_requests)) {
    return null;
  }
  return $data->remaining_daily_requests;
}

// Cette fonction gère la requête AJAX pour envoyer les données de mesure de l'empreinte environnementale d'une page ou d'un article, en utilisant l'API Ecoindex. Les données envoyées sont l'URL de la page, la largeur et la hauteur de la fenêtre de visualisation, et la fonction renvoie l'ID de la tâche Ecoindex correspondante.
function ecoindex_measure_click_handler()
{
  $url = $_POST['url'];
  $width = $_POST['width'];
  $height = $_POST['height'];

  $response = wp_remote_post('https://bff.ecoindex.fr/api/tasks', [
    'body' => json_encode([
      'url' => $url,
      'width' => $width,
      'height' => $height,
    ]),
    'headers' => [
      'Content-Type' => 'application/json',
    ],
  ]);

  $response_code = wp_remote_retrieve_response_code($response);
  if ($response_code == 429) {
    echo 'You have reached the daily limit';
  } elseif ($response_code == 422) {
    echo 'Validation Error';
  } else {
    $data = json_decode(wp_remote_retrieve_body($response));
    $taskId = $data;

    echo $taskId;
  }
  wp_die();
}

add_action('wp_ajax_ecoindex_measure', 'ecoindex_measure_click_handler');
add_action('wp_ajax_nopriv_ecoindex_measure', 'ecoindex_measure_click_handler');

// Cette fonction enregistre le script JavaScript qui permet de lancer une mesure Ecoindex pour chaque page ou article. Le script est localisé pour pouvoir envoyer des requêtes AJAX avec les paramètres nécessaires.
function ecoindex_measure_scripts()
{
  wp_enqueue_script(
    'ecoindex-measure',
    plugin_dir_url(__FILE__) . 'js/ecoindex-measure.js',
    ['jquery'],
    ECOINDEX_BADGE_VERSION,
    true
  );
  wp_localize_script('ecoindex-measure', 'ecoindex_measure_params', [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('ecoindex_measure_nonce'),
  ]);
}
add_action('admin_enqueue_scripts', 'ecoindex_measure_scripts');
add_action('wp_enqueue_scripts', 'ecoindex_measure_scripts');

// Cette fonction affiche soit un badge Ecoindex, soit un bouton "Mesurer" pour une colonne donnée dans une liste de publication WordPress.
function ecoindex_badge_render_column(
  $column_name,
  $post_id,
  $hasRemaningMesure
) {
  if ($column_name == 'Ecoindex') {
    $url = get_permalink($post_id);
    if (empty($url)) {
      $url = home_url();
    }
    $theme = get_option('ecoindex_badge_data_theme', 'light');
    $badge_code =
      '<a href="https://bff.ecoindex.fr/redirect/?url=' .
      add_trailing_slash($url) .
      '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' .
      $theme .
      '&url=' .
      add_trailing_slash($url) .
      '" alt="Ecoindex Badge" /></a>';
    echo $badge_code;
  } elseif ($column_name == 'Mesurer') {
    $url = get_permalink($post_id);
    if (empty($url)) {
      $url = home_url();
    }
    echo '<button class="button button-primary ecoindex-measure-button" ' .
      (!$hasRemaningMesure ? 'disabled' : '') .
      ' data-page-url="' .
      add_trailing_slash(esc_url($url)) .
      '">Mesurer</button>';
  }
}

// Cette fonction génère la page des paramètres du plugin Ecoindex Badge, affiche une liste de toutes les pages et articles du site avec leur titre, leur lien, leur score Ecoindex et un bouton pour mesurer leur impact environnemental.
function ecoindex_badge_settings_page()
{
  $remaningMesure = get_remaining_daily_requests();
  $hasRemaningMesure = false;
  if (0 < $remaningMesure) {
    $hasRemaningMesure = true;
  }
  $myListTable = new Ecoindex_Badge_List_Table();
  $myListTable->hasRemaningMesure = $hasRemaningMesure;
  ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <p><strong>Nombre de mesure encore possible aujourd'hui :</strong> <?php echo $remaningMesure; ?></p>
        <form method="post" action="options.php">
            <?php settings_fields('ecoindex_badge_settings_group'); ?>
            <?php do_settings_sections('ecoindex_badge_settings_page'); ?>
            <?php submit_button(); ?>
        </form>
         <h2>Liste des pages et articles</h2>
         <?php
         $myListTable->prepare_items($hasRemaningMesure);
         $myListTable->display();?>
    </div>
    <?php
}

// Ajoute la section et le champ pour le thème du badge Ecoindex dans la page de configuration
function ecoindex_badge_settings_init()
{
  register_setting(
    'ecoindex_badge_settings_group',
    'ecoindex_badge_data_theme'
  );
  add_settings_section(
    'ecoindex_badge_settings_section',
    'Options du badge',
    '',
    'ecoindex_badge_settings_page'
  );
  add_settings_field(
    'ecoindex_badge_data_theme',
    'Thème du badge',
    'ecoindex_badge_data_theme_callback',
    'ecoindex_badge_settings_page',
    'ecoindex_badge_settings_section'
  );
}
add_action('admin_init', 'ecoindex_badge_settings_init');

// Affiche le champ pour le thème du badge Ecoindex dans la page de configuration
function ecoindex_badge_data_theme_callback()
{
  $value = get_option('ecoindex_badge_data_theme', 'light'); ?>
    <select name="ecoindex_badge_data_theme">
        <option value="light"<?php selected($value, 'light'); ?>>Light</option>
        <option value="dark"<?php selected($value, 'dark'); ?>>Dark</option>
    </select>
    <?php
}

// Ajoute une page de configuration pour le badge Ecoindex
function ecoindex_badge_menu()
{
  add_menu_page(
    'Ecoindex Badge Settings',
    'Ecoindex Badge',
    'manage_options',
    'ecoindex-badge',
    'ecoindex_badge_settings_page',
    'dashicons-superhero'
  );
}
add_action('admin_menu', 'ecoindex_badge_menu');

// Ajoute le badge Ecoindex en bas à gauche de chaque page du site
function ecoindex_badge_add()
{
  $post_id = get_queried_object_id();
  if (get_post_status($post_id) == 'publish') {
    $data_theme = get_option('ecoindex_badge_data_theme', 'light');
    $url = get_permalink($post_id);
    if (empty($url)) {
      $url = home_url();
    }
    echo '<a id="ecoindex-badge" href="https://bff.ecoindex.fr/redirect/?url=' .
      add_trailing_slash($url) .
      '" target="_blank">';
    echo '<img src="https://bff.ecoindex.fr/badge/?theme=' .
      $data_theme .
      '&url=' .
      add_trailing_slash($url) .
      '" alt="Ecoindex Badge" />';
    echo '</a>';
    echo '<style>#ecoindex-badge {position: fixed; bottom: 10px; left: 10px;}</style>';
  }
}
add_action('wp_footer', 'ecoindex_badge_add');

// Ajoute une colonne Ecoindex dans la liste des articles
function ecoindex_badge_pages_column($columns)
{
  $columns['ecoindex_badge'] = 'Ecoindex';
  return $columns;
}
add_filter('manage_pages_columns', 'ecoindex_badge_pages_column');

// Cette fonction affiche le badge Ecoindex sur les pages en utilisant l'URL de la page courante avec la gestion de l'ajout du slash final et du thème personnalisé défini dans les paramètres.
function ecoindex_badge_pages_content($column_name, $post_id)
{
  if (
    $column_name == 'ecoindex_badge' &&
    get_post_status($post_id) == 'publish'
  ) {
    $url = get_permalink($post_id);
    $theme = get_option('ecoindex_badge_data_theme', 'light');
    $badge =
      '<a href="https://bff.ecoindex.fr/redirect/?url=' .
      add_trailing_slash($url) .
      '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' .
      urlencode($theme) .
      '&url=' .
      add_trailing_slash($url) .
      '" alt="Ecoindex Badge" /></a>';
    echo $badge;
  }
}
add_action('manage_pages_custom_column', 'ecoindex_badge_pages_content', 10, 2);

// La fonction "ecoindex_badge_posts_column" ajoute une colonne "Ecoindex" à la page d'administration des articles en utilisant le filtre "manage_posts_columns".
function ecoindex_badge_posts_column($columns)
{
  $columns['ecoindex_badge'] = 'Ecoindex';
  return $columns;
}
add_filter('manage_posts_columns', 'ecoindex_badge_posts_column');

// Cette fonction ajoute le badge Ecoindex à la colonne 'Ecoindex' dans la liste des articles de l'interface d'administration WordPress.
function ecoindex_badge_posts_content($column_name, $post_id)
{
  if (
    $column_name == 'ecoindex_badge' &&
    get_post_status($post_id) == 'publish'
  ) {
    $url = get_permalink($post_id);
    $theme = get_option('ecoindex_badge_data_theme', 'light');
    $badge =
      '<a href="https://bff.ecoindex.fr/redirect/?url=' .
      add_trailing_slash($url) .
      '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' .
      urlencode($theme) .
      '&url=' .
      add_trailing_slash($url) .
      '" alt="Ecoindex Badge" /></a>';
    echo $badge;
  }
}
add_action('manage_posts_custom_column', 'ecoindex_badge_posts_content', 10, 2);

// Cette methode ajoute le badge à la barre d'administration en front
function ecoindex_badge_in_admin_bar()
{
  $page_id = get_queried_object_id();
  if (get_post_status($page_id) == 'publish') {
    $url = get_permalink($page_id);
    if (empty($url)) {
      $url = home_url();
    }
    $theme = get_option('ecoindex_badge_data_theme', 'light');
    $badge_code =
      '<a href="https://bff.ecoindex.fr/redirect/?url=' .
      add_trailing_slash($url) .
      '" target="_blank"><img src="https://bff.ecoindex.fr/badge/?theme=' .
      $theme .
      '&url=' .
      add_trailing_slash($url) .
      '" alt="Ecoindex Badge" /></a>';
    $page_title = 'Ecoindex';
  } else {
    $badge_code = 'Ecoindex N/A';
    $page_title = 'Ecoindex non mesureable: page ou post non publié.';
  }
  if (is_admin()) {
    return null;
  }
  global $wp_admin_bar;

  $wp_admin_bar->add_menu([
    'id' => 'badge_actuel',
    'title' => $badge_code,
    'meta' => [
      'title' => $page_title,
      'target' => '_blank',
    ],
  ]);
}
add_action('wp_before_admin_bar_render', 'ecoindex_badge_in_admin_bar');

// Cette methode ajoute le bouton Mesurer à la barre d'administration en front, lorque l'on survol le badge
// présent dans la barre d'administration.
function add_mesure_to_ecoindex_badge_in_admin_bar()
{
  $page_id = get_queried_object_id();
  if (get_post_status($page_id) != 'publish') {
    return null;
  }
  if (is_admin()) {
    return null;
  }
  global $wp_admin_bar;
  $page_title = 'Ecoindex';
  $url = get_permalink($page_id);
  if (empty($url)) {
    $url = home_url();
  }
  $hasRemaningMesure = has_remaining_daily_requests();
  if ($hasRemaningMesure) {
    $mesurer_code =
      '<style>.ecoindex-measure-button--adminbar--wrapper .ab-item.ab-empty-item{padding:0!important;}</style>' .
      '<a role="link" style="cursor: pointer" class="ecoindex-measure-button" data-page-url="' .
      add_trailing_slash(esc_url($url)) .
      '">Mesurer</a>';
  } else {
    $mesurer_code =
      '<style>.ecoindex-measure-button--adminbar--wrapper .ab-item.ab-empty-item{padding:0!important;}</style>' .
      '<a role="link">Mesurer (nombre de mesure du jour dépassé)</a>';
  }
  $wp_admin_bar->add_node([
    'id' => 'mesurer_page',
    'parent' => 'badge_actuel',
    'title' => $mesurer_code,
    'meta' => [
      'title' => $page_title,
      'class' => 'ecoindex-measure-button--adminbar--wrapper',
    ],
  ]);
  $wp_admin_bar->add_node([
    'id' => 'admin_plugin',
    'parent' => 'badge_actuel',
    'title' => 'Administration du plugin',
    'href' => esc_url(admin_url('admin.php?page=ecoindex-badge')),
    'meta' => [
      'title' => 'Administration du plugin',
    ],
  ]);
}
add_action('admin_bar_menu', 'add_mesure_to_ecoindex_badge_in_admin_bar');
