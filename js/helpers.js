var $ = jQuery

const _ajax = (type, url, data) => {
  return new Promise((resolve, reject) => {
    $.ajax({
      type,
      url,
      data,
      timeout: 20000,
      success: (res) => {
        resolve({ res, err: null })
      },
      error: err => {
        resolve({ res: null, err })
      }
    })
  })
}

window.Clipboard = (function (window, document, navigator) {
  var textArea,
    copy;

  function isOS() {
    return navigator.userAgent.match(/ipad|iphone/i);
  }

  function createTextArea(text) {
    textArea = document.createElement('textArea');
    textArea.value = text;
    textArea.id = 'copyText';
    textArea.style.fontSize = '16px';
    jQuery('#btn-copy-total').append(textArea);
  }

  function selectText() {
    var range,
      selection;

    if (isOS()) {
      range = document.createRange();
      range.selectNodeContents(textArea);
      selection = window.getSelection();
      selection.removeAllRanges();
      selection.addRange(range);
      textArea.setSelectionRange(0, 999999);
    } else {
      textArea.select();
    }
  }

  function copyToClipboard() {
    document.execCommand('copy');
    jQuery('#copyText').remove();
  }

  copy = function (text) {
    createTextArea(text);
    selectText();
    copyToClipboard();
  };

  return {
    copy: copy
  };
})(window, document, navigator);

const copyText = text => {
  Clipboard.copy(text);
}

window.copyText = copyText

$.fn.serializeObject = function () {
  var o = {};
  var a = this.serializeArray();
  $.each(a, function () {
    if (o[this.name]) {
      if (!o[this.name].push) {
        o[this.name] = [o[this.name]];
      }
      o[this.name].push(this.value || '');
    } else {
      o[this.name] = this.value || '';
    }
  });
  return o;
};