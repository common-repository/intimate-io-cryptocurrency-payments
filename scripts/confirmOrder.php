<?php

add_action( 'wp_ajax_check_order_transactions', 'intimate_confirm_order' ); 
add_action( 'wp_ajax_nopriv_check_order_transactions', 'intimate_confirm_order' );

function intimate_confirm_order(){
  check_ajax_referer( 'thankyou'.$_POST['order_id'], 'thankyou_nonce' );
  $access_token = intimate_get_access_token();
  $confirmedWallets = intimate_get_transaction_status($access_token->access_token);

  if (count($confirmedWallets) > 0) {
    foreach($confirmedWallets as $confirmedWallet) {
      try{
        $wc_order = new WC_Order( $confirmedWallet->pooledWallet->orderId );
        $wc_order->set_status('completed');
        $wc_order->save();
        intimate_complete_transaction($confirmedWallet->pooledWallet->_id, $confirmedWallet->confirmedTransfer->transactionHash, $access_token->access_token);
      }
      catch(Exception $e){

      }
    }
    echo json_encode([
      'success'=>true,
      'status'=>'confirmed'
    ]);
  } else {
    echo json_encode(['success' => false]);
  }
	die();
}

?>