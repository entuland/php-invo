  var forms = forms || false;
  var forEach = forEach || false;
  var forEachRev = forEachRev || false;
  var dialog = dialog || false;
  
  var keyboard = {
    
    init: function() {
      keyboard.initCharCounter();
      keyboard.redirectArrowKeys();
      keyboard.enrichDropdowns();
      keyboard.form = forms.form;
      if(!keyboard.form) {
        return;
      }
      keyboard.redirectEnterKey();
      keyboard.mirrorItemCodes();
    },
    
    enrichDropdowns: function() {
      var allDrops = document.querySelectorAll('.drop');
      forEach(allDrops, function(button) {
        button.addEventListener('mouseleave', function() {
          keyboard.closeDrops.skipClose = false;
          keyboard.closeDrops();
        });
      });
      var allButtons = document.querySelectorAll('.button');
      var firstButtons = document.querySelectorAll('.drop-first-button');
      var otherButtons = document.querySelectorAll('.drop .drop-item .button');
      forEach(allButtons, function(button) {
        button.addEventListener('blur', function() {
          setTimeout(keyboard.closeDrops, 10);
          setTimeout(function() {
            keyboard.closeDrops.skipClose = false;
          }, 20);
        });
      });
      forEach(firstButtons, function(button) {
        button.addEventListener('click', function() {
          keyboard.closeDrops(parentMatchingQuery(button, '.drop'));
        });
      });
      forEach(otherButtons, function(button) {
        button.addEventListener('focus', function() {
          keyboard.closeDrops(parent = parentMatchingQuery(button, '.drop'));
        });
      });
    },
    
    closeDrops: function(keepThisOpen) {
      if(keyboard.closeDrops.skipClose && !keepThisOpen) {
        return;
      }
      var drops = document.querySelectorAll('.drop');
      if(keepThisOpen) {
        keepThisOpen.classList.add('hover');
        keyboard.closeDrops.skipClose = true;
      }
      forEach(drops, function(drop) {
        if(drop !== keepThisOpen) {
          drop.classList.remove('hover');
        }
      });
    },
    
    redirectEnterKey: function() {
      var editFields = document.querySelectorAll(
        '.single-edit, .list-edit'
      );
      forEach(editFields, function(field) {
        field.addEventListener('keypress', keyboard.keyPress);
      });
    },
    
    redirectArrowKeys: function() {
      document.body.addEventListener('keydown', function(ev) {
        var tag = ev.target.tagName;
        if(tag === 'TEXTAREA') {
          return;
        }
        
        var key = ev.key;
        var type = ev.target.type;
        
        var up = key === 'ArrowUp';
        var left = key === 'ArrowLeft';
        var down = key === 'ArrowDown';
        var right = key === 'ArrowRight';
        
        var input = tag === 'INPUT';
        var select = tag === 'SELECT';
        var number = type === 'number';
        var date = type === 'date' || type === 'datetime-local';
        
        var list = ev.target.matches('[list]');
        
        var noUpDown = select || list || date || number;
        
        if(noUpDown && (up || down)) {
          return;
        }
        if(input && (left || right)) {
          return;
        }
        
        if(down || right) {
          keyboard.selectNextFocusable(ev.target);
        }

        if(up || left) {
          var reverse = true;
          keyboard.selectNextFocusable(ev.target, reverse);
        }        
      });
    },
    
    keyPress: function(ev) {
      var control = ev.ctrlKey;
      var enter = ev.keyCode === 13 || ev.keyCode === 10;
      var input = ev.target.tagName === 'INPUT';
      var select = ev.target.tagName === 'SELECT';
      var textarea = ev.target.tagName === 'TEXTAREA';
      var editable = input || select || textarea;
      if(editable && enter && !control) {
        ev.preventDefault();
        keyboard.selectNextFocusable(ev.target);          
      }
      if(control && enter && textarea) {
        ev.target.value += '\n';
      }
    },
    
    isFocusable: function(el) {
      var tag = el.tagName;
      var type = el.type;
      var disabled = el.disabled;
      var readonly = el.readOnly;
      var visible = function(elem) {
        return !!( elem.offsetWidth || elem.offsetHeight || elem.getClientRects().length );
      };
      var inertAnchor = tag === 'A' && !el.getAttribute('href');
      
      if(type === 'datetime-local' 
          || type === 'hidden'
          || disabled
          || readonly
          || !visible(el)
          || inertAnchor) {
        return false;
      }
      return true;
    },
    
    selectNextFocusable: function(current, reverse) {
      var found = false;
      var forEachFunction = forEach;
      if(reverse) {
        forEachFunction = forEachRev;
      }
      var parent = document;
      if(dialog.isOpen) {
        parent = dialog.container;
      }
      var focusables = parent.querySelectorAll(
        'a, button, input, select, textarea'
      );
      forEachFunction(focusables, function(element, index) {
        if(found) {
          return;
        }
        if(element === current) {
          var nextIndex = index;
          if(reverse) {
            --nextIndex;
          }
          else {
            ++nextIndex;
          }
          if(nextIndex === focusables.length) {
            nextIndex = focusables.length - 1;
          }
          if(nextIndex < 0) {
            nextIndex = 0;
          }
          var next = focusables[nextIndex];
          
          if(!keyboard.isFocusable(next)) {
            current = next;
          }
          else {
            next.focus();
            next.select && next.select();
            found = true;
          }
        }
      });
    },
    
    initCharCounter: function() {
      document.body.addEventListener('keyup', function(ev) {
        if(ev.target.hasAttribute('maxlength')) {
          keyboard.updateCharlimit(ev.target);
        }
      }, true);
    },
    
    updateCharlimit: function(element) {
      var max = element.getAttribute('maxlength');
      if(!max) {
        return;
      }
      var tooltip = document.querySelector('#count-tooltip');
      if(!tooltip) {
        tooltip = document.createElement('div');
        document.body.appendChild(tooltip);
        tooltip.id = 'count-tooltip';
      }
      tooltip.textContent = '' + element.value.length + '/' + max;
      if(element.tagName === 'TEXTAREA') {
        tooltip.innerHTML += '<br>' + t('Ctrl+Enter to insert a new line');
      }
      element.parentNode.appendChild(tooltip);
      setTimeout(function() {
        if(tooltip.parentNode) {
          tooltip.parentNode.removeChild(tooltip);
        }
      },2500);
    },
    
    mirrorItemCodes: function() {
      var article = document.querySelector('.field-article input');
      var code = document.querySelector('.field-producer_code input');
      var handler = function(ev) {
        var other = code;
        if(ev.target === code) {
          other = article;
        }
        if(other.value === '') {
          other.value = ev.target.value;
        }
      };
      if(article) {
        article.addEventListener('blur', handler);
      }
      if(code) {
        code.addEventListener('blur', handler);      
      }
    }
    
  };
    
  onLoad(keyboard.init);
  