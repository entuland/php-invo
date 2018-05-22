

  var xdebug = {
    init: function(){
      var tables = document.querySelectorAll('table.xdebug-error');
      forEach(tables, function(table) {
        msg.staticContainer.appendChild(table);
      });
      var remove = document.querySelectorAll('body > br, body > font');
      forEach(remove, function(rem) {
        rem.parentNode.removeChild(rem);
      });
    }
  };
  
  onLoad(xdebug.init);