  var app = app || {};

  app.log = function(obj) {
    if(app.settings.debugJavascript) {
      console.log(obj);
    }
  };
  