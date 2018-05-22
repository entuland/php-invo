  var dialog = dialog || false;
  var ajax = ajax || false;
  var cover = cover || false;

  var deleter = {
    init: function() {
      deleter.bind(document);
    },
    
    bind: function(parent) {
      var buttons = parent.querySelectorAll('a.button.delete');
      forEach(buttons, deleter.process);
    },
    
    process: function(button) {
      button.onclick = function() {
        return false;
      };
      button.addEventListener('click', function() {
        cover.hide();
        deleter.askConfirmation(button);
      });
      button.dataset.deleterBound = true;
    },
    
    askConfirmation: function(button) {
      var className = button.dataset.class;
      var id = button.dataset.id;
      var text = t('Confirm deletion of')
        + ' ' + t(className)
        + ' ' + id
        + '?';
      dialog.confirm(text, function() {
        deleter.deleteRecord(className, id, function(response) {
          deleter.processAjaxResponse(response, button);
        });
      });
      dialog.class('warning');
    },
    
    deleteRecord: function(className, recordID, callback) {
      var postValues = {
        action: 'delete',
        class: className,
        id: recordID
      };
      ajax.run(postValues, callback);
    },
  
    processAjaxResponse: function(response, button) {
      if(response.success) {
        cover.show();
        var className = button.dataset.class;
        var id = button.dataset.id;
        var badpath = '/' + className + '/' + id;
        if(window.location.pathname.indexOf(badpath) === 0) {
          window.location = '/' + className;
        }
        else {
          window.location.reload();
        }
      }
    }
    
  };
  
  onLoad(deleter.init);
