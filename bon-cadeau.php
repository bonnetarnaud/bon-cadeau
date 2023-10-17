<?php

/**
 * Plugin Name: Bon cadeau
 * Description: Permet l'achat de bon cadeau relier à un produit
 * Version: 1.2
 * Author: Oplus 
 * Author URI: https://oplus.digital
 */


if (!defined('ABSPATH')) {
  exit;
}

require __DIR__ . '/vendor/autoload.php';


add_action('admin_enqueue_scripts', 'cstm_css_and_js');

function cstm_css_and_js($hook)
{
  if ('post.php' != $hook) {
    return;
  }
  wp_enqueue_script('gift-js', plugins_url('js/gift.js', __FILE__));
  wp_script_add_data('script-js', 'async', true);
  wp_localize_script(
    'gift-js',
    'script_ajax_object',
    array('ajaxurl' => admin_url('admin-ajax.php'))
  );
}

add_filter('product_type_options', 'add_gift_card_checkbox');
function add_gift_card_checkbox($actions)
{
  global $product_object;

  $wrapper_classes = array();
  foreach (wc_gc_get_product_types_allowed() as $type) {
    $wrapper_classes[] = 'show_if_' . $type;
  }

  $wrapper_classes[] = 'hide_if_bundle';
  $wrapper_classes[] = 'hide_if_composite';

  $actions['carte_cadeau'] = array(
    'id'            => '_carte_cadeau',
    'wrapper_class' => implode(' ', $wrapper_classes),
    'label'         => 'carte cadeau',
    'description'   => '',
    'default'       => is_gift_card($product_object) ? 'yes' : 'no'
  );

  /*
   $actions[ 'gift_card' ] = array(
            'id'            => '_gift_card',
            'wrapper_class' => implode( ' ', $wrapper_classes ),
            'label'         => __( 'Gift Card', 'woocommerce-gift-cards' ),
            'description'   => __( 'Gift cards are virtual products that can be purchased by customers and gifted to one or more recipients. Gift card code holders can redeem and use them as store credit.', 'woocommerce-gift-cards' ),
            'default'       => is_gift_card( $product_object ) || ( isset( $_GET[ 'todo' ] ) && 'giftcard' === $_GET[ 'todo' ] ) ? 'yes' : 'no'
        );
   */

  return $actions;
}
function wc_gc_get_product_types_allowed()
{
  return array(
    'simple',
    'variable'
  );
}

function is_gift_card($product)
{

  if (!is_a($product, 'WC_Product')) {
    return false;
  }

  if ($product->is_type('variation')) {
    $product = wc_get_product($product->get_parent_id());

    // Check for orphaned variations.
    if (!is_a($product, 'WC_Product')) {
      return false;
    }
  }

  return $product->meta_exists('_carte_cadeau') && 'yes' === $product->get_meta('_carte_cadeau', true);
}



add_filter('woocommerce_product_data_tabs', 'carte_cadeau_product_tab');
function carte_cadeau_product_tab($tabs)
{

  $tabs['carte_cadeau_tab'] = array(
    'label'     => 'Carte cadeau options',
    'target' => 'carte_cadeau_product_options',
    'class'  => 'show_if_carte_cadeau',
  );
  return $tabs;
}




add_action('woocommerce_product_data_panels', 'carte_cadeau_product_tab_product_tab_content');
function carte_cadeau_product_tab_product_tab_content()
{
  $current_value = get_post_meta(get_the_ID(), 'carte_cadeau_product_link', true);
  $options = array();
  $args = array(
    'post_type'             => 'product',
    'post_status'           => 'publish',
    'ignore_sticky_posts'   => 1,
    'posts_per_page'        => -1,
    /*
    'tax_query'             => array(
        array(
            'taxonomy'      => 'product_cat',
            'field' =>          'slug', 
            'terms'         => array('abonnements'),
            'operator'      => 'IN' 
        )
    )
    */
  );
  $loop = new WP_Query($args);
  $posts = $loop->get_posts();
  foreach ($posts as $post) {
    $options[$post->ID] = $post->post_title;
  }
  wp_reset_query();


  echo '<div id="carte_cadeau_product_options" class="panel woocommerce_options_panel">';

  echo '<div class="options_group">';

  woocommerce_wp_select(array(
    'id'      => 'carte_cadeau_product_link',
    'label'   => 'Produit lié à la carte cadeau',
    'options' =>  $options, //this is where I am having trouble
    'value'   => $current_value,

  ));
  echo '</div>';
  echo '</div>';
}



add_action('woocommerce_admin_process_product_object', 'action_save_product_meta');
function action_save_product_meta($product)
{

  $carte_cadeau_product_link = $_POST['carte_cadeau_product_link'];
  //dd($carte_cadeau_product_link);
  if (isset($_POST['_carte_cadeau'])) {
    $product->update_meta_data('_carte_cadeau', 'yes');
  } else {
    $product->delete_meta_data('_carte_cadeau');
  }

  if (isset($carte_cadeau_product_link) && !empty($carte_cadeau_product_link)) {
    $product->update_meta_data('carte_cadeau_product_link', $carte_cadeau_product_link);
  }
}

add_action('init', 'wpm_custom_post_type', 0);
function wpm_custom_post_type()
{

  register_post_type('bons_cadeaux', array(
    'label'               => __('bons cadeaux'),
    'description'         => __(''),
    'capability_type' => 'post',
    'supports'            => array('title', 'custom-fields'),
    'public' => true,
    'publicly_queryable' => false,
    'has_archive'         => false,
    'menu_icon'      => 'dashicons-buddicons-community',

  ));
}




add_action('add_meta_boxes', 'maboxpatisserie_add_meta_boxes_order');
function maboxpatisserie_add_meta_boxes_order()
{

  $order = wc_get_order(get_the_ID());

  if (!$order) return;

  $bons_cadeaux_id_array = get_post_meta($order->get_id(), 'bons_cadeaux_id_array', true);

  if (empty($bons_cadeaux_id_array)) return;
  add_meta_box('regenerated_gift_card_box', 'Bon cadeaux', 'regenerated_gift_card_box_callback', 'shop_order', 'side', 'core');
}


function regenerated_gift_card_box_callback()
{
  $order = wc_get_order(get_the_ID());
  $cart_items = $order->get_items();
  $bons_cadeaux_id_array = get_post_meta($order->get_id(), 'bons_cadeaux_id_array', true);

  if (empty($bons_cadeaux_id_array)) return;
  foreach ($bons_cadeaux_id_array as $bon_cadeau_id) {
    $used = get_post_meta($bon_cadeau_id, 'used', true);
    $upload_dir = wp_upload_dir();
    $code =  get_the_title($bon_cadeau_id);
    $download_url = $upload_dir['baseurl'] . "/bons-cadeaux/bon-cadeau-" . $code . ".pdf";
    echo '<p>';
    echo '<a href="' . $download_url . '" target="_blanck" style="text-decoration:none"><span class="dashicons dashicons-download"></span></a>';
    echo '<b>' . get_the_title($bon_cadeau_id) . '</b>';
    if (checkIfUsed($used)) {
      echo '<span style="margin-left:15px" class="dashicons dashicons-yes-alt"></span>   ';
    }


    echo '</p>';
  }
  $count_gif_code_generated = 0;
  foreach ($cart_items as $item) {
    $product_id = $item->get_product_id();
    if (has_term('cartes-cadeaux', 'product_cat',  $product_id)) {
      $qt_item = $item->get_quantity();
      for ($i = 1; $i <= $qt_item; $i++) {
        $count_gif_code_generated++;
      }
    }
  }

  echo '<hr/>';
  echo '<img id="regeneratedgiftcardspinner" src="' . esc_url(get_admin_url('', "images/loading.gif")) . '" style="display:none"/>';
  echo '<a href="#" class="button button-primary"  id="callajaxregeneratedgiftcard" data-order="' . get_the_ID() . '" >Regénérer les bons cadeaux</a>';

  //}
}

// Save the data of the Meta field
add_action('wp_ajax_regenerated_gift_card', 'regenerated_gift_card');
add_action('wp_ajax_nopriv_regenerated_gift_card', 'regenerated_gift_card');

function regenerated_gift_card($order_id)
{
  if (!isset($_POST['orderid'])) wp_send_json_error('probleme de $_POST');
  $order_id = $_POST['orderid'];
  $order = wc_get_order($order_id);
  $bons_cadeaux_id_array = get_post_meta($order->get_id(), 'bons_cadeaux_id_array', true);

  //remove les ancien créer 
  if (!empty($bons_cadeaux_id_array)) {
    $i = 0;
    foreach ($bons_cadeaux_id_array as $id) {
      wp_delete_post($id, true);
    }

    update_post_meta($order_id, 'bons_cadeaux_id_array', array());
  }
  //et generer les nouveaux
  $cart_items = $order->get_items();

  $all_new_gift_id_array = array();
  foreach ($cart_items as $item) {

    $array_ids = create_new_gift_ids_array($item, $order_id);
    $all_new_gift_id_array = array_merge($all_new_gift_id_array, $array_ids);
  }
  update_post_meta($order_id, 'bons_cadeaux_id_array', $all_new_gift_id_array);
  wp_send_json_success($all_new_gift_id_array);
}









// add form in front

/**
 * @link : https://mosaika.fr/creer-formulaire-wordpress-sur-mesure/
 */

add_shortcode('active_gift_card', 'shortcode_active_gift_card');

function shortcode_active_gift_card()
{
  ob_start(); ?>


  <form method="POST">
    <?php wp_nonce_field('active-gift-card-nonce', 'verif-gift-card'); ?>
    <h4>Activer une carte cadeau</h4>
    <?php if (isset($_GET['erreur'])) : ?>
      <div class="alert" style="font-weight: 300">
        <p>
          <?php echo sanitize_text_field($_GET['erreur']); ?>
        </p>
      </div>
    <?php endif; ?>
    <div class="">
      <input type="text" name="gift_card_code" placeholder="Entrer le code" />
      <button name="sender_active_gift_card" value="" class="" style="margin-top:10px">Activer</button>
    </div>
  </form>

<?php $html = ob_get_clean();
  return $html;
}

function do_form_active_gift_card()
{
  //dd($_POST['sender_active_gift_card']);
  if (isset($_POST['sender_active_gift_card']) && isset($_POST['verif-gift-card'])) {

    if (wp_verify_nonce($_POST['verif-gift-card'], 'active-gift-card-nonce')) {
      $gift_card_code = sanitize_text_field($_POST['gift_card_code']);

      if (empty($gift_card_code)) {
        $url = add_query_arg('erreur', 'Veuillez saisir un code cadeau', wp_get_referer());
        wp_safe_redirect($url);
        exit();
      }
      $args = array("post_type" => "bons_cadeaux", "s" => $gift_card_code);
      $query = get_posts($args);


      if (empty($query)) {
        $url = add_query_arg('erreur', 'Veuillez saisir un code cadeau valide', wp_get_referer());
        wp_safe_redirect($url);
        exit();
      }

      $gift_id = get_post_meta($query[0]->ID, 'gift_id', true);
      $related_product_id = get_post_meta($query[0]->ID, 'related_product_id', true);
      $used = get_post_meta($query[0]->ID, 'used', true);

      if (checkIfUsed($used)) {
        $url = add_query_arg('erreur', 'Votre bon cadeau a déja été utilisé', wp_get_referer());
        wp_safe_redirect($url);
        exit();
      }
      WC()->cart->empty_cart();
      WC()->cart->add_to_cart($related_product_id);

      WC()->session->__unset('gift_card_code');
      WC()->session->set('gift_card_code', sanitize_text_field($gift_card_code));

      wp_safe_redirect(wc_get_checkout_url());


      exit();
    }
  }
}
add_action('template_redirect', 'do_form_active_gift_card');


function checkIfUsed($used)
{
  if ($used == "1") {
    return true;
  }
  return false;
}

add_action('woocommerce_before_calculate_totals', 'addDiscount');
function addDiscount($cart_object)
{

  $gift_card_code = WC()->session->get('gift_card_code');

  if (isset($gift_card_code)) {
    $args = array("post_type" => "bons_cadeaux", "s" => $gift_card_code);
    $query = get_posts($args);

    if (!empty($query)) {

      $related_product_id = get_post_meta($query[0]->ID, 'related_product_id', true);

      foreach ($cart_object->get_cart() as $hash => $value) {

        if ($value['product_id'] == $related_product_id) {
          $used = get_post_meta($query[0]->ID, 'used', true);

          if (checkIfUsed($used)) {
            exit();
          }
          $product = wc_get_product($related_product_id);

          //if is _subscription_ productuc
          if ($product->get_type()  == "subscription") {

            $discount = $product->get_sign_up_fee();
          } else {
            $discount = $product->get_regular_price();
          }



          //dd( WC()->cart);

        }
        if (isset($discount)) {

          WC()->cart->add_fee('Bon cadeau', -$discount);
          //add_filter( 'woocommerce_cart_needs_payment', 'my_check_needs_payement',110, 2 );
        }
      }
    }
  }
}

function my_check_needs_payement($needs_payment, $cart)
{
  dd($cart);
}

// if achat carte cadeau in panier 


// -> ajouter champ l'offrir à

add_action('woocommerce_checkout_after_customer_details', 'add_info_client_offer_to', 20);
function add_info_client_offer_to()
{
  $cart_obj = WC()->cart;
  //dump($cart_obj->get_cart());

  $gift_card_code = WC()->session->get('gift_card_code');

  if (isset($gift_card_code) && !empty($gift_card_code)) {
    woocommerce_form_field('gift_card_code_field', array(
      'type'          => 'hidden',
      'class'         => array('hide'),
      'input_class'   => array('type-field-hidden'),
      'required'      => false,
    ), $gift_card_code);
  }

  /*
  foreach( $cart_obj->get_cart() as $key=>$cart_item ) {
    $product_id = $cart_item['product_id'];
    $product = wc_get_product( $product_id );

    if(is_gift_card($product)) {

      echo '<h2>Carte cadeau</h2>';
      echo '<div class="customer_more_info_wrapper">';
      echo '<h3 style="margin-bottom:0px">J\'offre mon cadeau à</h3>';
      woocommerce_form_field( 'offer_to_prenom', array(
          'type'          => 'text',
          'class'         => array( 'form-row-first' ),
          'input_class'   => array('input-text'),
          'label_class'   => array('woocommerce-form__label'),
          'label'         => '<span class="label">Prénom</span>',
          'placeholder'   => '',
          'required'      => false,
      ), '');
      woocommerce_form_field( 'offer_to_name', array(
          'type'          => 'text',
          'class'         => array( 'form-row-last' ),
          'input_class'   => array('input-text'),
          'label_class'   => array('woocommerce-form__label'),
          'label'         => '<span class="label">Nom</span>',
          'placeholder'   => '',
          'required'      => false,
      ), '');
      echo '<div class="clearfix"></div>';
      echo '<h3 style="margin-bottom:0px">Message personnalisé</h3>';
      woocommerce_form_field( 'message_to_offer', array(
        'type'          => 'textarea',
        'class'         => array( '' ),
        'input_class'   => array('input-text'),
        'label_class'   => array('woocommerce-form__label'),
        'label'         => '<span class="label">Message personnalisé</span>',
        'placeholder'   => '',
        'required'      => false,
    ), '');
      echo'</div>';
    }
  }
  */
}

add_action('woocommerce_checkout_update_order_meta', 'cw_checkout_order_meta');
function cw_checkout_order_meta($order_id)
{
  if (!empty($_POST['message_to_offer'])) {
    update_post_meta($order_id, 'message_to_offer', sanitize_text_field($_POST['message_to_offer']));
  }
  if (!empty($_POST['offer_to_name']) || !empty($_POST['offer_to_prenom'])) {
    update_post_meta($order_id, 'offer_to', sanitize_text_field($_POST['offer_to_prenom'] . ' ' . $_POST['offer_to_name']));
  }

  if (isset($_POST['gift_card_code_field']) && !empty($_POST['gift_card_code_field'])) {
    update_post_meta($order_id, 'gift_card_code', sanitize_text_field($_POST['gift_card_code_field']));
  }
}

/**
 * Display field value on the order edit page
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'add_offer_to_infos_after_billing_address', 10, 1);

function add_offer_to_infos_after_billing_address($order)
{
  //dd(get_post_meta( $order->get_id()));
  $offer_to = get_post_meta($order->get_id(), 'offer_to', true);
  $message_to_offer = get_post_meta($order->get_id(), 'message_to_offer', true);
  $bons_cadeaux_id_array = get_post_meta($order->get_id(), 'bons_cadeaux_id_array', true);
  $gift_card_code = get_post_meta($order->get_id(), 'gift_card_code', true);

  if (isset($gift_card_code) && !empty($gift_card_code)) {
    echo '<h3 style="margin-bottom:10px">Code cadeau utilisé :</h3>';
    echo $gift_card_code;
  }

  if (empty($offer_to) && empty($message_to_offer)) {
    return;
  }

  echo '<h3 style="margin-bottom:10px">Informations carte cadeau :</h3>';
  echo '<b >J\'offre mon cadeau à :</b><br/>';
  echo $offer_to . ' <br/><br/>';
  echo '<b>Message personnalisé :</b> <br/>';
  echo $message_to_offer . '<br/><br/>';
  echo '<b>Bon cadeau :</b> <br/>';
  foreach ($bons_cadeaux_id_array as $bon_cadeau_id) {

    echo get_the_title($bon_cadeau_id);
  }
}



//-> un fois payé -> créer un bon cadeau


add_action('woocommerce_payment_complete', 'update_bons_cadeaux');
function update_bons_cadeaux($order_id)
{
  if (!$order_id) {
    return;
  }
  $gift_card_code = get_post_meta($order_id, 'gift_card_code', true);

  if (isset($gift_card_code) && !empty($gift_card_code)) {
    $args = array("post_type" => "bons_cadeaux", "s" => $gift_card_code);
    $query = get_posts($args);
    $gift_id = $query[0]->ID;
    if (isset(WC()->session)) {
      WC()->session->__unset('gift_card_code');
    }

    update_post_meta($gift_id, 'used', 1);
    update_post_meta($gift_id, 'used_order_id', $order_id);
  }
}

add_action('woocommerce_order_status_processing', 'create_post_after_order');
function create_post_after_order($order_id)
{

  if (!$order_id) {
    return;
  }

  $order = wc_get_order($order_id);
  $cart_items = $order->get_items();
  $all_new_gift_id_array = array();
  foreach ($cart_items as $item) {

    $array_ids = create_new_gift_ids_array($item, $order_id);
    $all_new_gift_id_array = array_merge($all_new_gift_id_array, $array_ids);
  }

  update_post_meta($order_id, 'bons_cadeaux_id_array', $all_new_gift_id_array);
}

function create_new_gift_ids_array($item, $order_id)
{
  $product_id = $item->get_product_id();
  $product_name = $item->get_data()['name'];
  $new_gift_ids_array = array();

  if (has_term('cartes-cadeaux', 'product_cat',  $product_id)) {
    $qt_item = $item->get_quantity();



    for ($i = 1; $i <= $qt_item; $i++) {
      $new_gift_id = generate_post_bon_cadeau($order_id, $product_id);

      generate_pdf_bon_cadeau($new_gift_id, $product_name);
      $new_gift_ids_array[] = $new_gift_id;
    }
  }

  return $new_gift_ids_array;
}


function  generate_post_bon_cadeau($order_id, $product_id)
{

  $related_product_id = get_post_meta($product_id, 'carte_cadeau_product_link', true);

  $titre = substr("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ", mt_rand(0, 51), 1) . substr(md5(time()), 24);
  $new_gift = array(
    'post_title'    => $titre,
    'post_name'    => $titre,
    'post_status'   => 'publish',
    'post_author'   => 1,
    'post_type'     => 'bons_cadeaux',
    'post_content'  => '',  // Content
    'meta_input' => array(
      'order_id' => $order_id,
      'gift_id' => $product_id,
      'related_product_id' => $related_product_id,
      'used' => 0
    )
  );


  if (post_exists($titre, '', '', 'bons_cadeaux', 'publish')) return;
  $new_gift_id = wp_insert_post($new_gift);
  return $new_gift_id;
}

// display custom post meta in admin panel

function wpc_custom_table_head($defaults)
{
  $defaults['bon_cadeau']    = 'Bon cadeau';
  $defaults['order']    = 'Acheté par';
  //$defaults['offer_to']   = 'Offert à';
  //$defaults['message']   = 'Message';
  $defaults['used']  = 'Utilisé';
  return $defaults;
}
// change the _event_ part in the filter name to your CPT slug  
add_filter('manage_bons_cadeaux_posts_columns', 'wpc_custom_table_head');


// now let's fill our new columns with post meta content  
function wpc_custom_table_content($column_name, $post_id)
{
  if ($column_name == 'bon_cadeau') {
    $bon_cadeau_id = get_post_meta($post_id, 'gift_id', true);
    if (isset($bon_cadeau_id) && !empty($bon_cadeau_id)) {
      $product = wc_get_product($bon_cadeau_id);
      echo $product->get_title();
    }
  }

  if ($column_name == 'order') {
    $order_id = get_post_meta($post_id, 'order_id', true);
    $order = wc_get_order($order_id);
    $billing_first_name = $order->get_billing_first_name();
    $billing_last_name  = $order->get_billing_last_name();
    $url = admin_url('post.php?post=' . $order_id) . '&action=edit';
    echo '<a href="' . $url . '">Acheté par ' . $billing_first_name . ' ' . $billing_last_name . '</a>';
  }
  /*
  if ($column_name == 'offer_to') { 
      $offer_to = get_post_meta( $post_id, 'offer_to', true ); 
      echo $offer_to; 
  } 

  if ($column_name == 'message') { 
    $message = get_post_meta( $post_id, 'message', true ); 
    echo $message; 
} 
*/

  if ($column_name == 'used') {

    if (checkIfUsed(get_post_meta($post_id, 'used', true))) {
      echo '<span class="dashicons dashicons-yes-alt"></span>   ';
      if (null !== get_post_meta($post_id, 'used_order_id', true) && !empty(get_post_meta($post_id, 'used_order_id', true))) {
        $order_id = get_post_meta($post_id, 'used_order_id', true);
        $order = wc_get_order($order_id);
        $billing_first_name = $order->get_billing_first_name();
        $billing_last_name  = $order->get_billing_last_name();
        $url = admin_url('post.php?post=' . $order_id) . '&action=edit';
        echo '<a href="' . $url . '">Utilisé par ' . $billing_first_name . ' ' . $billing_last_name . '</a>';
      }
    }
  }
}
// change the _event_ part in the filter name to your CPT slug 
add_action('manage_bons_cadeaux_posts_custom_column', 'wpc_custom_table_content', 10, 2);


/**
 * amil info code cadeau
 * @link
 * 
 */

add_action('woocommerce_email_order_details', 'add_email_order_meta_gift', 5, 4);

function add_email_order_meta_gift($order, $sent_to_admin, $plain_text, $email)
{
  if ($sent_to_admin) {
    return;
  }

  $bons_cadeaux_id_array = get_post_meta($order->get_id(), 'bons_cadeaux_id_array', true);

  // we won't display anything if it is not a gift
  if (empty($bons_cadeaux_id_array)) {
    return;
  }

  ob_start(); ?>


  <h2>Code cadeau</h2>
  <?php foreach ($bons_cadeaux_id_array as $bon_cadeau_id) : ?>
    <?php
    $upload_dir = wp_upload_dir();
    $code =  get_the_title($bon_cadeau_id);
    $download_url = $upload_dir['baseurl'] . "/bons-cadeaux/bon-cadeau-" . $code . ".pdf";
    ?>
    <div style="border:1px solid #000; padding: 30px; margin:30px 0;">
      <p style="margin:0px; text-align:center; font-size:2em; line-height: 1em; font-weight:bold">
        <?php echo get_the_title($bon_cadeau_id); ?><br />
        <a class="" style="font-size: 14px;" href="<?php echo $download_url ?>" target="_blanck" style="text-decoration:none">Télécharger le bon cadeau</a>
      </p>
    </div>

  <?php endforeach; ?>


  <?php echo ob_get_clean();
}



add_action('woocommerce_before_thankyou', 'test_before_tankyou');

function test_before_tankyou($order_id)
{



  $bons_cadeaux_id_array = get_post_meta($order_id, 'bons_cadeaux_id_array', true);
  if (empty($bons_cadeaux_id_array)) {
    return;
  }
  echo '<h2>Code cadeau</h2>';
  foreach ($bons_cadeaux_id_array as $bon_cadeau_id) : ?>
    <?php
    $upload_dir = wp_upload_dir();
    $code =  get_the_title($bon_cadeau_id);
    $download_url = $upload_dir['baseurl'] . "/bons-cadeaux/bon-cadeau-" . $code . ".pdf";
    ?>
    <div style="border:1px solid #000; padding: 30px; margin:30px 0;">
      <p style="margin:0px; text-align:center; font-size:2em; line-height: 1em; font-weight:bold">
        <?php echo get_the_title($bon_cadeau_id); ?>
        <a class="elementor-button elementor-size-xs" href="<?php echo $download_url ?>" target="_blanck" style="text-decoration:none"><span class="dashicons dashicons-download"></span> Télécharger le bon cadeau</a>
      </p>
    </div>
  <?php endforeach;
}

/**
 * @snippet       File Attachment @ WooCommerce Emails
 * @how-to        Get CustomizeWoo.com FREE
 * @author        Rodolfo Melogli
 * @testedwith    WooCommerce 4.5
 * @donate $9     https://businessbloomer.com/bloomer-armada/
 */

add_filter('woocommerce_email_attachments', 'pdf_bon_cadeau_attach_to_emails', 10, 3);

function pdf_bon_cadeau_attach_to_emails($attachments, $email_id, $order)
{
  $email_ids = array('customer_processing_order');


  if (in_array($email_id, $email_ids)) {
    $upload_dir = wp_upload_dir();
    $bons_cadeaux_id_array = get_post_meta($order->get_id(), 'bons_cadeaux_id_array', true);
    if (empty($bons_cadeaux_id_array)) {
      return;
    }
    foreach ($bons_cadeaux_id_array as $bon_cadeau_id) {
      $code =  get_the_title($bon_cadeau_id);
      $attachments[] = $upload_dir['basedir'] . "/bons-cadeaux/bon-cadeau-" . $code . ".pdf";
    }
  }

  return $attachments;
}


function generate_pdf_bon_cadeau($bon_cadeau_id, $product_name)
{
  $upload_dir = wp_upload_dir();
  $code = get_the_title($bon_cadeau_id);
  $offer_to = get_post_meta($bon_cadeau_id, 'offer_to', true);
  $message = get_post_meta($bon_cadeau_id, 'message', true);
  $related_product_id = get_post_meta($bon_cadeau_id, 'related_product_id', true);

  $defaultConfig = (new Mpdf\Config\ConfigVariables())->getDefaults();
  $fontDirs = $defaultConfig['fontDir'];

  $defaultFontConfig = (new Mpdf\Config\FontVariables())->getDefaults();
  $fontData = $defaultFontConfig['fontdata'];

  $mpdf = new \Mpdf\Mpdf(
    [
      'fontDir' => array_merge($fontDirs, [
        __DIR__ . '/fonts',
      ]),
      'fontdata' => $fontData + [ // lowercase letters only in font key
        'fraunces' => [
          'R' => 'fraunces-regular.ttf',
          'B'  => "fraunces-black.ttf",
          'I' => 'fraunces-italic.ttf',
        ],
        "lato" => array(
          'R'  => "Lato-Regular.ttf",
          'B'  => "Lato-Bold.ttf",
          'I' => 'Lato-Italic.ttf',
        ),
      ]
    ]
  ); // Create new mPDF Document



  // Beginning Buffer to save PHP variables and HTML tags
  ob_start();
  ?>
  <style>
    @page {
      background-color: #FEFDF0;
    }
  </style>

  <div style="text-align:center; font-size:32px; font-family: fraunces; color: #AD5018; position:relative; min-height:300px; font-weight:900">
    <h1 style="text-transform:uppercase"><?php echo $product_name ?> </h1>
  </div>
  <div style="position: fixed; right:15px; top:100px">
    <?php $image = wp_get_attachment_image_src(get_post_thumbnail_id($related_product_id), 'single-post-thumbnail'); ?>
    <img src="<?php echo $image[0]; ?>" width="160px">
  </div>
  <div style=" width:65%; margin:10px auto;">
    <img src="<?php echo  __DIR__ . '/img/illustration.png' ?>">
  </div>
  <h2 style="font-family: lato; text-align:center; font-size:2em; color: #AD5018; text-transform:uppercase; margin-top:50px;">
    Active ta carte cadeau<br />
    avec ton code ci-dessous
  </h2>
  <div style=" font-family: lato; text-align:center;">
    <p style="margin:20px 0;  font-size:3em; line-height: 1em; font-weight:bold">
      <?php echo $code; ?>
    </p>

    <div style=" font-family: lato; margin-top:10px; text-transform:uppercase; font-size:2em; color: #AD5018; font-weight:bold">Sur www.maboxpatisserie.fr</div>
  </div>
  </div>


  <?php
  $html = ob_get_contents();
  ob_end_clean();


  ob_start(); ?>
  <div style="border-top:1px solid #000; padding-top:30px; text-align:center; font-weight:normal">
    <b>Ma Box Pâtisserie</b>, 40 rue de Bruxelles, 69100 Villeurbanne, FRANCE
  </div>
<?php
  $footer = ob_get_contents();
  ob_end_clean();
  $mpdf->SetFooter($footer);
  $mpdf->SetDisplayMode('fullpage');
  $mpdf->pdf_version = '1.5';
  $mpdf->WriteHTML($html);

  $dir = trailingslashit($upload_dir['basedir']) . 'bons-cadeaux';

  $file_location = $dir . '/bon-cadeau-' . $code . '.pdf';
  $mpdf->Output($file_location, 'F');
  return $file_location;
}

?>