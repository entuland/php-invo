  var dialog = dialog || false;
  var cover = cover || false;

  var listSelect = {
    shiftPressed: false,
    controlPressed: false,
    focusIndex: 0,
    
    init: function() {
      document.onkeydown = listSelect.keyDown;
      document.onkeyup = listSelect.keyUp;
      listSelect.bindAll();
    },
    
    bindAll: function() {
      listSelect.cells = document.querySelectorAll('#list td');
      listSelect.checkers = document.querySelectorAll('#list input[type="checkbox"]');
      listSelect.bindButtons();
      listSelect.bindCheckers();
      listSelect.bindCells();
      listSelect.highlightChecked();
    },
    
    keyDown: function(key) {
      if(key.key === 'Shift') {
        listSelect.shiftPressed = true;
      }
      if(key.key === 'Control') {
        listSelect.controlPressed = true;
      }
    },

    keyUp: function(key) {
      if(key.key === 'Shift') {
        listSelect.shiftPressed = false;
      }
      if(key.key === 'Control') {
        listSelect.controlPressed = false;
      }
    },
        
    bindButtons: function() {

      var selectAll = document.querySelectorAll('.list-select-all');
      var selectNone = document.querySelectorAll('.list-select-none');
      var selectInvert = document.querySelectorAll('.list-select-invert');

      forEach(selectAll, function(el) {
        el.addEventListener('click', listSelect.select.all);
      });
      
      forEach(selectNone, function(el) {
        el.addEventListener('click', listSelect.select.none);
      });

      forEach(selectInvert, function(el) {
        el.addEventListener('click', listSelect.select.invert);
      });
    },

    bindCheckers: function() {
      forEach(listSelect.checkers, function(ch) {
        ch.addEventListener('change', function() {
          listSelect.checkerClicked(ch);
        });
      });
    },
    
    bindCells: function() {
      forEach(listSelect.cells, function(cell) {
        if(cell.classList.contains('rapid-edit-owned')) {
          return;
        }
        var ch = cell.parentNode.querySelector('input');
        cell.addEventListener('click', function(evt) {
          if(evt.target === cell) {
            ch.checked = !ch.checked;
            listSelect.checkerClicked(ch);
          }
        });
      });
    },
        
    checkerClicked: function(ch) {
      document.getSelection().empty();
      
      var control = listSelect.controlPressed;
      var shift = listSelect.shiftPressed;
      var current = listSelect.findCheckerIndex(ch);
      var focus = listSelect.focusIndex;
      
      if(control && shift) {
        listSelect.select.range(focus, current);
        listSelect.focusIndex = current;
      }
      
      else if(control) {
        listSelect.focusIndex = current;
      }
      
      else if(shift) {
        listSelect.select.none();
        listSelect.select.range(focus, current);
      }
      
      else {
        listSelect.select.none();
        ch.checked = true;
        listSelect.focusIndex = current;
      }

      listSelect.highlightChecked();
    },
    
    highlightChecked: function() {
      forEach(listSelect.checkers, function(ch) {
        ch.parentNode.parentNode.classList.toggle('selected', ch.checked);
      });
    },
    
    findCheckerIndex: function(ch) {
      if(!ch) {
        return -1;
      };
      for(var i = 0; i < listSelect.checkers.length; ++i) {
        if(ch === listSelect.checkers[i]) {
          return i;
        }
      }
      return -1;
    },
    
    select: {
      
      all: function() {
        forEach(listSelect.checkers, function(ch) {
          ch.checked = true;
        });
        listSelect.highlightChecked();
      },
    
      none:function() {
        forEach(listSelect.checkers, function(ch) {
          ch.checked = false;  
        });
        listSelect.highlightChecked();
      },
    
      invert: function() {
        forEach(listSelect.checkers, function(ch) {
          ch.checked = !ch.checked;  
        });
        listSelect.highlightChecked();
      },
      
      range: function(start, stop) {
        if(start > stop) {
          var pass = start;
          start = stop;
          stop = pass;
        }
        for(var i = start; i <= stop; ++i) {
          listSelect.checkers[i].checked = true;
        }
      }
      
    }
    
  };
  
  onLoad(listSelect.init);
  