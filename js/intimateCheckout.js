class IntimateCheckout {
  constructor() {

  }

  static async initialize() {
    return new Promise(async (resolve, reject) => {
      console.log('INITIALIZING INTIMATE CHECKOUT PLUGIN...')

      let { res: data, err } = await _ajax('POST', itm_globals.ajaxUrl, $('#transaction-form').serialize())

      if (err) {
        console.log('Error intializing intimate checkout plugin...')
        this.displayError()
        return 
      }
      
      try {
        data = JSON.parse(data)
        console.log('Initial Transaction Data: ', data)

        // Maintenance Mode
        // let maintenance_mode = data.maintenance_mode
        // if (maintenance_mode) {
        //   if (maintenance_mode.value === true) {
        //     this.displayMaintenance()
        //     return
        //   }
        // }

        window.transaction = data.transaction;
        window.qrcode = data.qrcode

        // Update QR Code
        this.updateQRCode()

        if (data.transaction.currency === 'bitcoin') {
          window.walletAddressType = 'bitcoin'
        } else {
          window.walletAddressType = 'ethereum'
        }

        // Update UI
        if (data.transaction) {
          this.updatePluginUI(data.transaction)
        } else {
          this.displayError()
        }
    
        // Remove Loading Overlay
        jQuery('#payment .itm-fetching').remove()
        
    
        setTimeout(() => {
          this.initializeCopyButtons()
          resolve()
        }, 100)

      } catch (err) {
        this.displayError()
        reject()
      }
    })
  }

  static displayError() {
    $('.payment_box.payment_method_intimate_crypto_checkout').html('<span style=\"color: red;\">There is an error loading the intimate plugin.</span>')
    $('#payment .itm-fetching').remove()
    $('#place_order').prop('disabled', true)
  }

  static displayMaintenance() {
    $('.payment_box').html('<span style=\"color: red;\">The intimate plugin is on maintenance mode.</span>')
    $('#payment .itm-fetching').remove()
    $('#place_order').prop('disabled', true)
  }

  static initializeCopyButtons() {
    $('#btn-copy-total').off('click')
    $('#btn-copy-wallet-address').off('click')
    $('#btn-copy-total').on('click', e => {
      e.preventDefault()

      window.copyText($('#total').val().split(' ')[0])
      toastr.success('Copied!')
    })
    $('#btn-copy-wallet-address').on('click', e => {
      e.preventDefault()

      window.copyText($('#wallet-address').val().split(' ')[0])
      toastr.success('Copied!')
    })
  }

  static updateQRCode() {
    if (window.qrcode) {
      document.getElementById('qrcode').src = window.qrcode
    }
  }

  static updatePluginUI(transaction) {
    // console.log('Updating Plugin UI: ', transaction)
    console.log('Updating Plugin UI: ')
    let { totalAmounts, currency } = transaction

    // Update QR Code
    this.updateQRCode()

    $('#wallet-address').val(transaction.walletAddress)
    let options = ''

    // select options
    for (let token of totalAmounts) {
      options += `<option
        data-currency="${token.id}"
        data-symbol="${token.symbol}"
        data-value="${token.amountToPay}"
        data-name="${token.name}"
        value="${token.amountToPay}"
        ${currency === token.id && 'selected'}
      >
        ${token.amountToPay} ${token.symbol}
      </option>`
    }

    $('#select-currency').html(options)

    let selectedToken = totalAmounts.find(token => transaction.currency === token.id)
    $('#total').val(selectedToken.amountToPay + ' ' + selectedToken.symbol)

    setTimeout(() => {
      this.initializeCopyButtons()
    }, 100)
  }

  showLoadingOverlay(selector) {
    $(selector).css({ position: "relative" }).append(`
      <div class="itm-fetching" style="z-index: 1000; border: none; margin: 0px; padding: 0px; width: 100%; height: 100%; top: 0px; left: 0px; right: 0px; bottom: 0px; background: rgb(255, 255, 255); opacity: 0.6; cursor: default; position: absolute; display: flex; justify-content: center; align-items: center;">
        <i class="fa fa-spinner fa-spin fa-2x fa-fw"></i>
      </div>
    `)
  }

  listenToNetworkRequests() {
    console.log('Now Listening to WC Ajax...')
    const self = this
    const send = XMLHttpRequest.prototype.send
    XMLHttpRequest.prototype.send = function () {
      this.addEventListener('load', function () {
        if (this.responseURL.indexOf('wc-ajax=update_order_review') > -1) {
          // console.log(JSON.parse(this.response))

          /* START AJAX */
          self.requestUpdatedTotals()
          /* END AJAX */

        }

      })
      return send.apply(this, arguments)
    }
  }

  // Trigger a network request to update new totals
  async requestUpdatedTotals() {
    const self = this

    let postData = {
      action: 'handle_update_trx',
      ajax_nonce: itm_globals.nonce,
      data: {}
    }

    // show loading
    self.showLoadingOverlay('#payment')
    self.showLoadingOverlay('.shop_table.woocommerce-checkout-review-order-table')

    let { res, err } = await _ajax('POST', itm_globals.ajaxUrl, postData)
    if (err) {
      return
    }

    // remove loading
    $('.itm-fetching').remove()

    let response = JSON.parse(res)
    console.log('Updated Order Response: ', response)
    
    if (response.success) {
      IntimateCheckout.updatePluginUI(response.data)
    } else {
      this.displayError()
    }
  }

  // Change ERC Token handler
  static async handleSelectCurrency() {
    let showLoadingOverlay = selector => {
      $(selector).css({ position: "relative" }).append(`
        <div class="itm-fetching" style="z-index: 1000; border: none; margin: 0px; padding: 0px; width: 100%; height: 100%; top: 0px; left: 0px; right: 0px; bottom: 0px; background: rgb(255, 255, 255); opacity: 0.6; cursor: default; position: absolute; display: flex; justify-content: center; align-items: center;">
          <i class="fa fa-spinner fa-spin fa-2x fa-fw"></i>
        </div>
      `)
    }
    
    // show loading
    showLoadingOverlay('#payment')
    showLoadingOverlay('.shop_table.woocommerce-checkout-review-order-table')

    let selectedCurrency = document.getElementById('select-currency')
    let selectedOption = selectedCurrency[selectedCurrency.selectedIndex]
    let { currency, symbol, value, name } = selectedOption.dataset

    $('#total').val(value + ' ' + symbol)
    $('#place_order').prop('disabled', true)

    window.selectedCurrency = currency

    // let woocommerce make the PUT request to NODEjs
    $('#update-transaction-currency').val(currency)
    
    let postData = {
      action: 'handle_select_currency',
      ajax_nonce: itm_globals.nonce,
      data: $('#update-transaction-form').serializeObject(),
    }

    let { res, err } = await _ajax('POST', itm_globals.ajaxUrl, postData)
    
    if (err) {
      console.log(err)
      $('#place_order').prop('disabled', false)
      alert('An unknown error occurred')
      return
    }

    res = JSON.parse(res)
    let walletAddress
    let walletAddressType
    let qrcode
    if (symbol === 'BTC') {
      walletAddress = res.data.walletAddresses.find(w => w.type === 'bitcoin').walletAddress
      walletAddressType = 'bitcoin'
      qrcode = res.data.walletAddresses.find(w => w.type === 'bitcoin').qrcode
    } else {
      walletAddress = res.data.walletAddresses.find(w => w.type === 'ethereum').walletAddress
      walletAddressType = 'ethereum'
      qrcode = res.data.walletAddresses.find(w => w.type === 'ethereum').qrcode
    }

    if (window.walletAddressType !== walletAddressType) {
      toastr.warning(`You selected ${walletAddressType === 'bitcoin' ? 'Bitcoin' : 'ETH/ERC-20'} as currency. Your wallet address has been changed. Please scan the QR code again.`)
    }

    document.getElementById('qrcode').src = qrcode

    window.walletAddressType = walletAddressType

    $('#wallet-address').val(walletAddress)
    $('.itm-fetching').remove()

    $('#place_order').prop('disabled', false)

  }
}