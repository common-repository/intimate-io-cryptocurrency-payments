<?php

if ( ! class_exists('WC_Intimate_Checkout_Form')){
  add_action( 'plugins_loaded', 'init_itm_gateway_class' );
  function init_itm_gateway_class(){
    class WC_Intimate_Crypto_Checkout_Form extends WC_Payment_Gateway {
      public function __construct(){
        $this->id = 'intimate_crypto_checkout';
        $this->has_fields = false;
        $this->method_title = 'Pay with ITM';
        $this->method_description = 'Accept crypto payments from your customers today. Say goodbye to expensive bank fees. ';
        $this->init_form_fields();
        $this->init_settings();
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
      }

      public function init_form_fields(){
        $this->form_fields = array(
            'enabled' => array(
              'title'       => 'Enable/Disable',
              'label'       => 'Enable intimate Payment Gateway',
              'type'        => 'checkbox',
              'description' => '',
              'default'     => 'no'
            ),
            'title' => array(
              'title'       => 'Title',
              'type'        => 'text',
              'description' => 'This controls the title which the user sees during checkout.',
              'default'     => 'Enable intimate.io Cryptocurrency Payment Gateway',
              'desc_tip'    => true,
            ),
            'description' => array(
              'title'       => 'Description',
              'type'        => 'textarea',
              'description' => 'This controls the description which the user sees during checkout.',
              'default'     => 'Pay with a variety of popular cryptocurrencies via intimate.io'
            ),
            'publishable_key' => array(
              'title'       => 'Client ID',
              'type'        => 'text'
            ),
            'private_key' => array(
              'title'       => 'Client Secret',
              'type'        => 'password'
            )
        );
      }

      public function init_settings(){
        parent::init_settings();
        $this->title = 'Pay with intimate (ITM)';
        $this->title = $this->get_option( 'title' );
        $this->description = $this->get_option( 'description' );
        $this->enabled = $this->get_option( 'enabled' );
        $this->testmode = 'yes' === $this->get_option( 'testmode' );
        $this->private_key = $this->get_option( 'private_key' );
        $this->publishable_key = $this->get_option( 'publishable_key' );
      }
      
      private function intimate_get_access_tokens(){
        $this->testmode = 'yes' === $this->get_option( 'testmode' );
        $this->private_key = $this->get_option( 'private_key' );
        $this->publishable_key = $this->get_option( 'publishable_key' );
        
        WC()->session->set( 'publishable_key' , $this->publishable_key );
        WC()->session->set( 'private_key' , $this->private_key );
        

        $request = wp_remote_post(intimate_get_base_url() . '/oauth/token/',array(
          'method' => 'POST',
          'timeout' => 45,
          'redirection' => 5,
          'httpversion' => '1.0',
          'blocking' => true,
          'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8"),
          'body' => [
            'client_id' => $this->publishable_key,
            'client_secret' => $this->private_key,
            'grant_type' => 'client_credentials',
          ],
          'cookies' => array()
        ));
        $body = wp_remote_retrieve_body( $request );
        return json_decode($body);
      }

      private function intimate_queue_transaction($total_amount_itm, $access_token){
        WC()->session->set( 'total_amount_itm', $total_amount_itm );
        try {
          $request = wp_remote_post(intimate_get_base_url() . '/transactions/queue/',array(
            'method' => 'POST',
            'timeout' => 10000,
            'redirection' => 5,
            'httpversion' => '1.0',
            'blocking' => true,
            'headers' => array("Content-type" => "application/x-www-form-urlencoded;charset=UTF-8", 'Authorization' => "Bearer ".$access_token),
            'body' => [
              'amount' => $total_amount_itm,
              'clientType' => 'woocommerce',
            ],
            'cookies' => array()
          ));
          
          $body = wp_remote_retrieve_body( $request );
          return json_decode($body);
        } catch(Exception $e) {
          print_r($e);
        }
      }

      private function intimate_get_wallet($queue_id, $access_token){
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

      private function intimate_update_transaction_order_id($order_id, $access_token){
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
              'orderId' => $order_id
            ],
            'cookies' => array()
          ));
          
          $body = wp_remote_retrieve_body( $request );
          return json_decode($body);
        } catch(Exception $e) {
          print_r($e);
        }
      }
      
      private function intimate_generate_qrcode($key, $access_token) {
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

      public function payment_fields() {

        $access_token = $this->intimate_get_access_tokens();

        $headerText = "Select your preferred cryptocurrency from the list and send the required balance to the address displayed below.";
        $footerText = "Once you have sent your funds, click 'Place order' below.";
        
        echo wpautop( $headerText );
        echo "<script>
        jQuery('li.payment_method_intimate_crypto_checkout > label').html(`<div id=\"itm-title-wrapper\" style=\"display: inline-block; width: calc(100% - 50px);\"><div id=\"itm-title\" style=\"display: flex; justify-content: space-between;\"><span>Pay with intimate.io</span><img src=\"https://intimate.io/_nuxt/img/intimate-io-horizontal_blue.2074357.svg\" width=\"100\" height=\"100\"> <a href=\"https://intimate.io\" target=\"_blank\" id=\"itm-title-link\">What is intimate.io?</a></div></div>`)

        </script>";
        echo "<div style=\"intimate-form\">";
        echo "<select class=\"address-select\" id=\"select-currency\" onchange=\"IntimateCheckout.handleSelectCurrency()\">";
        echo "</select>".
          '<div class="form-group"><label>Send:</label> <input type="text" class="inline-block" disabled value="" id="total"><button class="btn-copy" role="button" id="btn-copy-total"><i class="fa fa-copy"></i></button></div>' .
          '<div class="form-group"><label>To:</label> <input id="wallet-address" type="text" class="inline-block" disabled value="' . '' . '"><button id="btn-copy-wallet-address" class="btn-copy" role="button"><i class="fa fa-copy"></i></button></div>' .
          '<div class="form-group"><label>QR: </label> ' .
          '<img id="qrcode" style="max-height: unset; max-width: 300px;" /></div>' .
          "</div>";
          echo '<form method="POST" id="transaction-form">
            <input type="hidden" name="action" value="queue_transactions" />
            <input type="hidden" name="publishable_key" value="' . $this->publishable_key . '" />
            '. wp_nonce_field( 'action', 'name', true, false ) . '</form>';
            echo '<form method="POST" id="update-transaction-form">
              <input type="hidden" name="action" value="update_transaction" />
              <input type="hidden" id="update-transaction-currency" name="currency" value="" />
              '. wp_nonce_field( 'action', 'name', true, false ) . '</form>';
        echo wpautop( $footerText );
        echo '<script>
          if(!window.transaction){
            jQuery("#payment").css({position: "relative"}).append(`
              <div class="itm-fetching" style="z-index: 1000; border: none; margin: 0px; padding: 0px; width: 100%; height: 100%; top: 0px; left: 0px; right: 0px; bottom: 0px; background: rgb(255, 255, 255); opacity: 0.6; cursor: default; position: absolute; display: flex; justify-content: center; align-items: center;">
                <i class="fa fa-spinner fa-spin fa-2x fa-fw"></i>
              </div>
            `)
          }
        </script>';

        echo "<script>

        jQuery(document).ready(() => {
          jQuery('form.woocommerce-checkout').on('submit', function() {
            $('html, body').animate({ scrollTop: 0 }, 'slow');
          });
        })
        
        </script>
        ";
      }

      public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        $access_token = $this->intimate_get_access_tokens();
        // $queue = $this->intimate_queue_transaction($order_id, $access_token->access_token);
        $queue = $this->intimate_update_transaction_order_id($order_id, $access_token->access_token);

        // $queue_id = WC()->session->get('queue_id');
        // $queue = $this->intimate_get_wallet($queue_id, $access_token->access_token);
        $qrcode = $this->intimate_generate_qrcode($queue->data->walletAddress, $access_token->access_token);

        WC()->session->set('selected_wallet_address', $queue->data->walletAddress );

        WC()->session->set('queue', $queue);
        WC()->session->set('qrcode', '<img src="' . $qrcode . '" />');

        WC()->session->set('queue_id', null);
        
        // Mark as on-hold (we're awaiting the payment)
        $order->update_status( 'on-hold', __( 'Awaiting crypto payment', 'wc-gateway-offline' ) );
                
        // Reduce stock levels
        $order->reduce_order_stock();
                
        // Remove cart
        WC()->cart->empty_cart();
                
        // Return thankyou redirect
        return array(
            'result'    => 'success',
            'redirect'  => $this->get_return_url( $order ),
        );
      } 
    }

    $GLOBALS['intimate-crypto-checkout'] = new WC_Intimate_Crypto_Checkout_Form();
  }
  

  function add_itm_to_payment_gateways( $methods ) {
      $methods[] = 'WC_Intimate_Crypto_Checkout_Form'; 
      return $methods;
  }
  
  add_filter( 'woocommerce_payment_gateways', 'add_itm_to_payment_gateways' );
}

?>