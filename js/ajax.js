  var msg = msg || false;
  var dialog = dialog || false;
  var cover = cover || false;
  var app = app || false;
  
  var ajax = {
    
    syncRun: function(params, callback) {
      ajax.worker(params, callback, false);
    },
    
    run: function(params, callback) {
      app.log(params);
      ajax.worker(params, callback, true);      
    },
    
    worker: function(params, callback, async) {
      cover.show();
      var req = new XMLHttpRequest();
      req.onload = function(e) {
        cover.hide();
        var response = {};
        try {
          response = JSON.parse(e.target.response); 
        } catch (exception) {
          response = {
            success: false,
            messages: [
              e.target.response
            ]
          };
        }
        if(response.messages) {
          dialog.alert(response.messages, function() {
            callback(response);
          });
          dialog.moveToOldPosition();
        }
        else {
          callback(response);
        }
      };
      var form = new FormData();
      for(var i in params) {
        form.append(i, params[i]);
      }
      req.open('POST', app.data.publicBase + '/ajax', async);
      req.send(form);
    }
  };
  