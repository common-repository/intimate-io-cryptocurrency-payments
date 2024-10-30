<?php

add_action('wp_ajax_handle_update_trx', 'handle_update_trx');
add_action('wp_ajax_nopriv_handle_update_trx', 'handle_update_trx');
function handle_update_trx() {
  check_ajax_referer('itm-nonce', $_REQUEST['ajax_nonce'], false);
  $access_token = intimate_get_access_token();
  global $woocommerce;
  $woocommerce->cart->calculate_totals();
  $totals = $woocommerce->cart->get_totals();
  $queue_id = WC()->session->get('queue_id');

  try {
    $request = wp_remote_post(intimate_get_base_url() . '/transactions/queue/' . $queue_id, array(
      'method' => 'PUT',
      'timeout' => 10000,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token->access_token),
      'body' => [
        'totals' => $totals,
      ],
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    echo $body;
  } catch(Exception $e) {
    echo json_encode($e);
  }

  wp_die();
}

?>