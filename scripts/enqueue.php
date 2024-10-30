<?php

add_action('wp_enqueue_scripts', 'intimate_style_load');
function intimate_style_load() {
  wp_enqueue_style('itm-checkout', plugin_dir_url(__FILE__) . '../css/index.css');
  wp_enqueue_style('fontawesome', plugin_dir_url(__FILE__) . '../css/font-awesome.min.css');
  wp_enqueue_style('toastr', plugin_dir_url(__FILE__) . '../css/toastr.min.css');
}

add_action('wp_enqueue_scripts', 'intimate_script_load');
function intimate_script_load() {
  
  // wp_enqueue_script('jQuery', 'https://code.jquery.com/jquery-2.2.4.min.js');
  wp_enqueue_script( 'jquery' );
  wp_enqueue_script('axios', plugin_dir_url(__FILE__) . '../js/axios.min.js');
  wp_enqueue_script('toastr', plugin_dir_url(__FILE__) . '../js/toastr.min.js');
  wp_enqueue_script('pusher', plugin_dir_url(__FILE__) . '../js/pusher.min.js');

  // register the script
  wp_enqueue_script('itm_global_script_helpers', plugin_dir_url(__FILE__) . '../js/helpers.js');
  wp_enqueue_script('itm_global_script_intimate_checkout', plugin_dir_url(__FILE__) . '../js/intimateCheckout.js');
  wp_register_script('itm_global_script', plugin_dir_url(__FILE__) . '../js/index.js');
  wp_localize_script('itm_global_script', 'itm_globals', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('itm-nonce'),
    'isCheckoutPage' => is_checkout(),
    'isThankyouPage' => is_wc_endpoint_url('order-received')
  ]);

  wp_enqueue_script('itm_global_script', 'itm_global_script');
}

?>