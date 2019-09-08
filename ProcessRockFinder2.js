var grid;
$(document).ready(function() {
  // get current finder name
  var $field = $('#wrap_Inputfield_code');
  var $debug = $('#debuginfo');
  var name = $field.data('name');
  var hasTabulator = $('.RockTabulatorWrapper').length;

  // load code from browser, but only when no name is set
  // this makes sure that if a name is set, the code is always
  // the same as in the corresponding file
  var code;
  if(!name) code = localStorage.getItem('RockFinder2_code');

  // get ace editor
  var editor;

  // setup spinner
  var $spinner = $('<i class="fa fa-spin fa-spinner" style="margin-left: 10px;"></i>');

  // get code from textarea/ace
  var getCode = function() {
    if(typeof ace != "undefined") {
      code = ace.edit('InputfieldAceExtended_code_editor').getSession().getValue();
    }
    else {
      code = $('#Inputfield_code').val();
    }
    return code;
  }

  // ajax function
  var sendAJAX = function() {
    $spinner.hide().appendTo($field.closest('.Inputfield').find('>.InputfieldHeader')).fadeIn();

    // save code to localStorage
    if(!name) localStorage.setItem('RockFinder2_code', code);
    
    var $tabulator = $('.RockTabulatorWrapper');
    $tabulator.find('.loading').fadeIn();

    // get data and log it to console
    $.post(RockFinder2.conf.url, {
      code: getCode(),
      type: 'debug',
    }).done(function(data) {
      // check for errors
      if(data.error) {
        alert(data.error);
        $debug.find('.tracy-inner').fadeOut();
        return;
      }

      // update div
      $debug.fadeOut(function() {
        $debug.html(data.html);
        // unfold tracy dump object
        $debug.find('.tracy-dump-object').click();
      }).fadeIn();

      // update tabulator
      if(hasTabulator) {
        grid = RockTabulator.getGrid("rockfinder2_sandbox");
        if(!grid.table) grid.initTable({data:data.finder.data});
        else grid.table.setData(data.finder.data);
        $tabulator.find('.loading').fadeOut();
      }

    }).fail(function(data) {
      alert('Rquest failed, see console');
      console.error(data);
    }).always(function() {
      $spinner.fadeOut();
    });
  }

  // save code to file
  var saveFile = function() {
    $.post("#", {
      code: getCode(),
      action: 'save',
    }).done(function(data) {
      UIkit.notification({
        message: data,
      });
    }).fail(function(data) {
      alert('Rquest failed: ' + data);
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
  
  // keyboard shortcuts
  $('#wrap_Inputfield_code').keydown(function (event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
      switch (event.keyCode) {
        // ctrl/alt + enter
        case 13:
          event.preventDefault();
          sendAJAX();
          return false;
      }
    }
  });
  $(window).keydown(function (event) {
    if (event.ctrlKey || event.metaKey || event.altKey) {
      switch (event.keyCode) {
        // ctrl/alt + s
        case 83:
          event.preventDefault();
          saveFile();
          return false;
      }
    }
  });

  // confirm deletion of finders
  $(document).on('click', '.delFinder', function(event) {
    var $link = $(event.target).closest('a');
    var name = $link.data('name');
    ProcessWire.confirm('Are you sure you want to delete Finder ' + name + ' ?', function() {
      // yes, do not abort
      window.location.href = $link.attr('href');
    },
    function() {
      // on abort
      return false;
    });
    return false;
  });
});