<?php
function intimate_queue_ajax(){
 
  // if( !is_wc_endpoint_url( 'checkout' ) ) return;
  if (!is_checkout()) return;
  if( is_wc_endpoint_url( 'order-received' ) ) return;

  // if (is_)
  $queue_id = WC()->session->get('queue_id');
  $access_token = intimate_get_access_token();

}

?>