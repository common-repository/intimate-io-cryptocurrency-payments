<?php
/*
Plugin Name:  intimate.io Cryptocurrency Payments
Author URI:   https://intimate.io/
Description:  The intimate.io payment plugin allows merchants to accept a variety of cryptocurrencies without the headache of trying to understand crypto.
Version:      1.3.1
Author:       intimate.io
Contributor:  https://wordpress.org/support/users/intimatetoken
Tags:         cryptocurrency, eth, btc, intimate, adult, payments
Text Domain:  wporg
Domain Path:  /languages
Requires at least:  5.0.0
Tested up to:  5.0.3
Stable tag:    4.3
*/

defined('ABSPATH') or die();

require_once(dirname(__FILE__).'/base_url.php');
require_once(dirname(__FILE__).'/scripts/enqueue.php');
require_once(dirname(__FILE__).'/scripts/handleSelectCurrency.php');
require_once(dirname(__FILE__).'/scripts/handleUpdateTrx.php');
require_once(dirname(__FILE__).'/scripts/validateTransactionAjax.php');

//check to make sure WooCommerce is active
if ( in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ) {

  if (!function_exists('intimate_crypto_checkout_form')){
    add_action( 'woocommerce_thankyou_intimate_crypto_checkout', 'intimate_crypto_checkout_form', 10 );
    function intimate_crypto_checkout_form($order_id){
      $access_token = intimate_get_access_token();
      // Lets grab the order
      $order = wc_get_order( $order_id );  
      $selected_wallet_address = WC()->session->get('selected_wallet_address');
      $qrcode = WC()->session->get('qrcode');

      // bookmark
      $session_queue = WC()->session->get('queue');
      $queue = intimate_get_wallet($session_queue->data->_id, $access_token->access_token);

      echo "<h2>Send crypto to complete your order</h2>";

      $tokenIndex = array_search($queue->currency, array_column($queue->totalAmounts, 'id'));
      $selectedToken = $queue->totalAmounts[$tokenIndex];

      echo "<div class=\"payment_box payment_method_intimate_crypto_checkout\">";
      echo "<div style=\"intimate-form\">";
      echo '<div class="form-group"><label>Send:</label> <input type="text" class="inline-block" disabled="" value="' . $selectedToken->amountToPay . ' ' . $selectedToken->symbol . '" id="total"><button class="btn-copy" role="button" id="btn-copy-total" value=""><i class="fa fa-copy"></i></button></div>';
      echo '<div class="form-group"><label>To:</label> <input id="wallet-address" type="text" class="inline-block" disabled="" value="' . $selected_wallet_address .'"><button id="btn-copy-wallet-address" class="btn-copy" role="button" value=""><i class="fa fa-copy"></i></button></div>';
      echo '<div class="qr-confirmation-wrapper">';
      echo '<div class="form-group"><label>QR: </label>' . $qrcode . '</div>';
      echo '<div class="confirmation"><h4 id="confirmation-title">Awaiting Confirmation</h4><p id="confirmation-p">(Please allow up to 15 minutes for processing of your transaction.)</p><h3 id="timer"></h3></div>';
      echo '</div>';
      echo "</div></div>";
      echo '<form method="POST" id="check-form">
        <input type="hidden" name="action" value="check_order_transactions" />
        <input type="hidden" name="order_id" value="' . $order_id . '" />
        '. wp_nonce_field( 'thankyou'.$order_id, 'thankyou_nonce', true, false ) .'
      </form>';
      echo "<script>
        let $ = jQuery
        $(document).ready(() => {

          let orderStatus = '" . $order->get_status() . "'
          window.orderStatus = orderStatus;

          if (orderStatus !== 'completed') {
            let timer = 15 * 60

            let countdown = setInterval(() => {
              timer -= 1
              
              let m = Math.floor(timer / 60)
              let s = (timer % 60).toString().padStart(2, '0')

              document.getElementById('timer').innerText = m + 'm ' + s + 's'

              if (timer == 0 && !window.orderComplete) {
                clearInterval(countdown)
                $('#timer').hide()
                $('#confirmation-p').hide()
                $('#confirmation-title').text('Timeout Error')
              }
            }, 1000)
          } else {
            $('#timer').hide()
            $('#confirmation-p').hide()
            $('#confirmation-title').text('Order Complete')
          }

          let amt = '" . $selectedToken->amountToPay . "'
          
          document.querySelector('.total .amount').innerHTML += ' / ' + amt + ' " . $selectedToken->symbol . "'
          let payWithText = document.querySelector('.woocommerce-order-overview__payment-method.method strong').innerText
          document.querySelector('.woocommerce-order-overview__payment-method.method strong').innerText = payWithText.replace('Pay with ', '')

          let interval = setInterval(() => {
            let tablePayWithText = document.querySelector('.shop_table.order_details tfoot tr:nth-child(2) td').innerText
            if (tablePayWithText) {
              document.querySelector('.shop_table.order_details tfoot tr:nth-child(2) td').innerText = tablePayWithText.replace('Pay with ', '')
              clearInterval(interval)
            }
          }, 500)

          let amount = '" . $selectedToken->amountToPay .  "'
          let currency = '" . $selectedToken->symbol . "'
          let total = $('.woocommerce-order-details table tfoot tr:last-child td:nth-child(2)')
          total.text(total.text() + ' / ' + amt + ' ' + currency)

          IntimateCheckout.initializeCopyButtons()
        })
      </script>
      ";
    }
  }

add_action( 'wp_footer', 'intimate_queue_ajax' );
require_once('intimate_queue_ajax.php');

add_action( 'wp_ajax_queue_transactions', 'intimate_queue_transactions' ); 
add_action( 'wp_ajax_nopriv_queue_transactions', 'intimate_queue_transactions' );

function intimate_queue_transactions() {
  
  // WINDOW ON LOAD, INITIALIZE FORM DATA
  check_ajax_referer( 'action', 'name' );
  
  $access_token = intimate_get_access_token();
  
  $queue_id = WC()->session->get('queue_id');

  $total = WC()->cart->get_totals();
  $currency = get_option('woocommerce_currency'); 

  // bookmark
  if (empty($queue_id)) {
    $transaction = intimate_queue_transaction($total, $currency, $access_token->access_token);
    WC()->session->set('queue_id', $transaction->_id );
  } else {
    $transaction = intimate_update_transaction_totals($total, $access_token->access_token);

    // no transaction data
    if ($transaction->message) {
      $transaction = intimate_queue_transaction($total, $currency, $access_token->access_token);
      WC()->session->set('queue_id', $transaction->_id );
    }
  }

  // qr code
  $wallet_address = $transaction->data ? $transaction->data->walletAddress : $transaction->walletAddress;
  $qrcode = intimate_generate_qrcode($wallet_address, $access_token->access_token);
  
  // maintenance mode
  // $maintenance_mode = intimate_get_maintentance_mode($access_token->access_token);

  $output = array(
    "transaction" => $transaction->data ? $transaction->data : $transaction,
    "test" => $transaction,
    "qrcode" => $qrcode,
    "queue_id" => $queue_id,
    "total" => $total,
    "w_currency" => get_option('woocommerce_currency'),
    "maintenance_mode" => null
  );

  echo json_encode($output);
  wp_die();
}

add_action( 'wp_ajax_update_transaction', 'intimate_update_transaction' ); 
add_action( 'wp_ajax_nopriv_update_transaction', 'intimate_update_transaction' );
function intimate_update_transaction() {
  check_ajax_referer( 'action', 'name' );
  
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
        'amount' => $_POST['amount']
      ],
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    // echo $_POST['currency'];
    echo $body;
  } catch(Exception $e) {

    echo json_encode($e);
  }
  wp_die();
}

add_action('woocommerce_checkout_update_order_review', 'intimate_checkout_update_order_review');
function intimate_checkout_update_order_review() {
  global $woocommerce;
  $woocommerce->cart->calculate_totals();
  $totals = $woocommerce->cart->get_totals();
  WC()->session->set('totals', $totals);
}

add_action('woocommerce_checkout_update_order_meta', 'intimate_woocommerce_checkout_update_order_meta');
function intimate_woocommerce_checkout_update_order_meta() {
  echo "<h1>hello</h1>";
}

// hide coupon field on checkout page
function intimate_hide_coupon_field_on_checkout( $enabled ) {
	if ( is_checkout() ) {
		$enabled = false;
	}
	return $enabled;
}

// add_filter( 'woocommerce_coupons_enabled', 'intimate_hide_coupon_field_on_checkout' );

function intimate_queue_transaction($totals, $currency, $access_token){
  // WC()->session->set( 'total_amount_itm', $total_amount_itm );
  WC()->session->set( 'totals', $totals );

  try {
    $request = wp_remote_post(intimate_get_base_url() . '/transactions/queue/',array(
      'method' => 'POST',
      'timeout' => 10000,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
      'body' => [
        'totals' => $totals,
        'clientType' => 'woocommerce',
        'currency' => $currency
      ],
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    return json_decode($body);
  } catch(Exception $e) {
    print_r($e);
  }
}

function intimate_get_maintentance_mode($access_token) {
  try {
    $request = wp_remote_post(intimate_get_base_url() . '/configurations/maintenanceMode', array(
      'method' => 'GET',
      'timeout' => 10000,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    return json_decode($body);
  } catch(Exception $e) {
    print_r($e);
  }
}

function intimate_get_wallet($queue_id, $access_token){
  $url = intimate_get_base_url() . '/transactions/wallet/' . $queue_id;
  try {
    $request = wp_remote_post($url, array(
      'method' => 'GET',
      'timeout' => 10000,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    return json_decode($body);
  } catch(Exception $e) {
    print_r($e);
  }
}

function intimate_update_transaction_totals($totals, $access_token){
  try {
    $queue_id = WC()->session->get( 'queue_id');
    $request = wp_remote_post(intimate_get_base_url() . '/transactions/queue/' . $queue_id, array(
      'method' => 'PUT',
      'timeout' => 10000,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
      'body' => [
        'totals' => $totals
      ],
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    return json_decode($body);
  } catch(Exception $e) {
    print_r($e);
  }
}

function intimate_complete_transaction($walletId, $transactionHash, $access_token){
  $request = wp_remote_post(intimate_get_base_url() . '/transactions/complete/',array(
    'method' => 'POST',
    'timeout' => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking' => true,
    'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
    'body' => [
      'id' => $walletId,
      'transactionHash' => $transactionHash
    ],
    'cookies' => array()
  ));
  $body = wp_remote_retrieve_body( $request );
  return json_decode($body);
}

function intimate_get_transaction_status($access_token){
  $request = wp_remote_get(intimate_get_base_url() . '/transactions/confirmed/',array(
    'method' => 'GET',
    'timeout' => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking' => true,
    'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
    'cookies' => array()
  ));
  $body = wp_remote_retrieve_body( $request );
  return json_decode($body);
}
 
function intimate_get_access_token(){
  $publishable_key = WC()->session->get( 'publishable_key');
  $private_key = WC()->session->get( 'private_key' );
  $request = wp_remote_post(intimate_get_base_url() . '/oauth/token/',array(
    'method' => 'POST',
    'timeout' => 45,
    'redirection' => 5,
    'httpversion' => '1.0',
    'blocking' => true,
    'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8"),
    'body' => [
      'client_id' => $publishable_key,
      'client_secret' => $private_key,
      'grant_type' => 'client_credentials',
    ],
    'cookies' => array()
  ));
  $body = wp_remote_retrieve_body( $request );
  return json_decode($body);
}

function intimate_generate_qrcode($key, $access_token) {
  try {
    $request = wp_remote_post(intimate_get_base_url() . '/wallet/qrcode/',array(
      'method' => 'POST',
      'timeout' => 10000,
      'redirection' => 5,
      'httpversion' => '1.0',
      'blocking' => true,
      'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
      'body' => [
        'key' => $key
      ],
      'cookies' => array()
    ));
    
    $body = wp_remote_retrieve_body( $request );
    return $body;
  } catch(Exception $e) {
    print_r($e);
  } 
}

  require_once('intimate-crypto.php');

}

/*
if (is_admin()){
  require_once plugin_dir_path(__FILE__).'admin/admin-menu.php';
  require_once plugin_dir_path(__FILE__).'admin/settings-page.php';
  require_once plugin_dir_path(__FILE__).'admin/settings-register.php';
  require_once plugin_dir_path(__FILE__).'admin/settings-callback.php';
}
*/

