$(document).ready(function() {

  // ajax function
  var sendAJAX = function() {
    var $el = $('#result');
    $el.html('<i class="fa fa-spin fa-spinner"></i>');

    // get data
    $.post(RockFinder2.conf.url, {
      code: ace.edit('InputfieldAceExtended_code_editor').getSession().getValue(),
    }).done(function(data) {
      console.log(data);
    }).fail(function() {
      alert('request failed');
    });
  }

  // submit form on ctrl+enter
  $('#wrap_Inputfield_code').keydown(function (e) {
    if ((e.ctrlKey || e.altKey) && e.keyCode == 13) {
      // send ajax request
      sendAJAX();
    }
  });
});