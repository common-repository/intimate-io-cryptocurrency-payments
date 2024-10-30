<?php

add_action( 'wp_footer', 'intimate_validate_transaction_ajax' );
function intimate_validate_transaction_ajax(){
 
	// exit if we are not on the Thank You page
	if( !is_wc_endpoint_url( 'order-received' ) ) return;
  $url = intimate_get_base_url();
  $queue = WC()->session->get('queue');
  
	echo "<script>
    $(document).ready(() => {

      window.queue = JSON.parse(`" . json_encode($queue->data) . "`)

      Pusher.logToConsole = true;

      var pusher = new Pusher('519165e8d6e89e1e0529', {
        cluster: 'ap1',
        forceTLS: true,
        authEndpoint: '" . $url . "/pusher/auth',
        // authEndpoint: 'http://localhost:3000/pusher/auth',
      });

      const itmChannel = pusher.subscribe('private-queue-' + window.queue._id)
      itmChannel.bind('transfers/completetransaction', data => {
        $('#timer').hide()
        $('#confirmation-p').hide()
        $('#confirmation-title').text('Order Complete')

        window.orderComplete = true
        if (toastr) {
          toastr.success('Order Complete!')
        }
      })

      
    })
	</script>";
 
}

?>