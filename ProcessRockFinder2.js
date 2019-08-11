$(document).ready(function() {
  // get current finder name
  var $field = $('#wrap_Inputfield_code');
  var $debug = $('#debuginfo');
  var name = $field.data('name');

  // load code from browser, but only when no name is set
  // this makes sure that if a name is set, the code is always
  // the same as in the corresponding file
  var code;
  if(!name) code = localStorage.getItem('RockFinder2_code');

  // get ace editor
  var editor;

  // setup spinner
  var $spinner = $('<i class="fa fa-spin fa-spinner" style="margin-left: 10px;"></i>');

  // ajax function
  var sendAJAX = function() {
    $spinner.hide().appendTo($debug.closest('.Inputfield').find('>.InputfieldHeader')).fadeIn();

    // get value
    if(typeof ace != "undefined") {
      code = ace.edit('InputfieldAceExtended_code_editor').getSession().getValue();
    }
    else {
      code = $('#Inputfield_code').val();
    }

    // save code to localStorage
    if(!name) localStorage.setItem('RockFinder2_code', code);

    // get data and log it to console
    $.post(RockFinder2.conf.url + " #output", {
      code: code,
      type: 'debug',
    }).done(function(data) {
      // update div
      $debug.fadeOut(function() {
        $debug.html(data);
        $debug.find('.tracy-dump-object').click();
      }).fadeIn();
    }).fail(function(data) {
      alert('Rquest failed, see console');
      console.error(data);
    }).always(function() {
      $spinner.fadeOut();
    });
  }

  // onload
  $(window).load(function() {
    // early exit on overview page
    if(!$('#sandboxform').length) return;

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