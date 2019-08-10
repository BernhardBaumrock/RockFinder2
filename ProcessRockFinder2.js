$(document).ready(function() {
  var code = localStorage.getItem('rockfinder2_code');

  // get ace editor
  var editor;

  // setup spinner
  var $el = $('#wrap_Inputfield_code > .InputfieldHeader');
  var $spinner = $('<i class="fa fa-spin fa-spinner" style="margin-left: 10px;"></i>');

  // ajax function
  var sendAJAX = function() {
    $spinner.hide().appendTo($el).fadeIn();

    // get value
    if(typeof ace != "undefined") {
      code = ace.edit('InputfieldAceExtended_code_editor').getSession().getValue();
    }
    else {
      code = $('#Inputfield_code').val();
    }

    // save code to localStorage
    localStorage.setItem('rockfinder2_code', code);

    // get data and log it to console
    $.post(RockFinder2.conf.url, {
      code: code,
    }).done(function(data) {
      console.log(data);
    }).fail(function(data) {
      alert('Rquest failed, see console');
      console.error(data);
    }).always(function() {
      $spinner.fadeOut();
    });
  }

  // onload
  $(window).load(function() {
    if(typeof ace != 'undefined') {
      editor = ace.edit('InputfieldAceExtended_code_editor');
    }

    // set code from localstorage
    if(code) {
      editor.setValue(code, 1);
    }

    // fire ajax request on first load
    sendAJAX();
  });

  // submit form on ctrl+enter
  $('#wrap_Inputfield_code').keydown(function (e) {
    if ((e.ctrlKey || e.altKey) && e.keyCode == 13) sendAJAX();
  });
});