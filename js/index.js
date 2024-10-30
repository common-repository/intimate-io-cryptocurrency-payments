window.onload = async function () {
  console.log('INTIMATE CHECKOUT PLUGIN GLOBALS', itm_globals)
  itm_globals.isCheckoutPage == !!itm_globals.isCheckoutPage
  itm_globals.isThankyouPage == !!itm_globals.isThankyouPage

  let { isCheckoutPage, isThankyouPage } = itm_globals

  let itmCheckout = new IntimateCheckout()

  // Only on Checkout Page
  if (isCheckoutPage && !isThankyouPage) {
    setTimeout(function () {
      IntimateCheckout.initialize().then(function() {
        itmCheckout.listenToNetworkRequests()
      }).catch(function(err) {
        console.log('Error Initializing Intimate Checkout Plugin')
      })
    }, 2000)
  }

}
