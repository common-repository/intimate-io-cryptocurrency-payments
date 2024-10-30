<?php

add_action('wp_ajax_handle_select_currency', 'handle_select_currency');
add_action('wp_ajax_nopriv_handle_select_currency', 'handle_select_currency');
function handle_select_currency() {
  check_ajax_referer('itm-nonce', $_REQUEST['ajax_nonce'], false);

  $access_token = intimate_get_access_token();
  
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
        'currency' => $_POST['data']['currency']
      ],
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    $body = json_decode($body);

    for($i = 0; $i < count($body->data->walletAddresses); $i++) {
      $body->data->walletAddresses[$i]->qrcode = $qrcode = intimate_generate_qrcode($body->data->walletAddresses[$i]->walletAddress, $access_token->access_token);
    }

    $output = $body;
    echo json_encode($output);
  } catch(Exception $e) {
    echo json_encode($e);
  }

  wp_die();
}

?>