  var dialog = {
    movable: false,
    buttons: {},
    
    init: function() {

      var dialogContainer = document.getElementById('dialog-container');
      var dialogCover = document.getElementById('dialog-cover');
      
      if(!dialogContainer || !dialogCover) {
        return;
      }
      
      document.addEventListener('focus', function(ev) {
        setTimeout(function() {
          if(dialog.isOpen && !dialog.owns(ev.target)) {
            dialog.focus();
          }
        }, 100);
      }, true);
      
      dialog.container = dialogContainer;
      
      if(!dialog.movable) {
        dialog.movable = new movableInstance(dialogContainer);
      }
            
      document.addEventListener('keyup', function(ev) {
        if(ev.key === 'Escape') {
          if(dialog.escapeKeyHandler instanceof Function) {
            dialog.escapeKeyHandler();
          }
        }
      }, true);
      
      dialogCover.addEventListener('click', function(ev) {
        if(ev.target !== dialogCover) {
          return;
        }
        blink(dialog.movable.target);
      });
      
    },
            
    alert: function(message, doneCallback) {
      this._helper(message, doneCallback, doneCallback, 'alert');
    },
    
    input: function(inputType, message, defaultValue, confirmCallback, cancelCallback) {
      this._helper(message, confirmCallback, cancelCallback, 'input', defaultValue, inputType);
    },
  
    confirm: function(message, confirmCallback, cancelCallback) {
      this._helper(message, confirmCallback, cancelCallback);
    },
    
    focus: function() {
      dialog.lastFocus && dialog.lastFocus.focus();
      dialog.lastFocus && dialog.lastFocus.select && dialog.lastFocus.select();
    },
    
    focusHandler: function(ev) {
      dialog.lastFocus = ev.target;
    },
    
    owns: function(element) {
      return null !== parentMatchingQuery(element, '#dialog-container');
    },

    _helper: function(message, 
                      confirmCallback, 
                      cancelCallback, 
                      dialogType, 
                      defaultValue, 
                      inputType
        ) {
      dialog.storeOldPosition();
      dialog.isOpen = true;
      var dialogScreen = document.getElementById('dialog-cover');
      var dialogContainer = document.getElementById('dialog-container');
      var dialogMessage = document.getElementById('dialog-message');
      var dialogInputContainer = document.getElementById('dialog-input-container');
      var dialogConfirmButton = document.getElementById('dialog-confirm-button');
      var dialogCancelButton = document.getElementById('dialog-cancel-button');
      
      dialogScreen.style.display = 'block';

      var dialogInput = false;
      if(dialogType === 'input') {
        var inputWrapper = document.createElement('div');
        inputWrapper.className = 'input-wrapper';
        var inputValue = defaultValue || '';
        dialogInput = document.createElement('INPUT');
        inputWrapper.appendChild(dialogInput);
        dialogInputContainer.appendChild(inputWrapper);

        dialogInput.type = inputType || 'text';

        if(dialogInput.type === 'number') {
          inputValue = textToFloat(inputValue);
          dialogInput.step = '0.01';
        }
        else {
          inputValue = inputValue.trim();
        }
        
        dialogInput.value = inputValue;
        
        dialogInput.addEventListener('keypress', function(ev) {
          if(ev.key === 'Enter') {
            if(typeof dialog.enterKeyHandler === 'function') {
              dialog.enterKeyHandler();
            }
          }
        });
      }
      
      var focusables = [
        dialogInputContainer,
        dialogConfirmButton,
        dialogCancelButton
      ];
      
      forEach(focusables, function(focusable) {
        if(focusable) {
          focusable.addEventListener('focus', dialog.focusHandler);
        }
      });

      if(dialogType === 'alert') {
        dialogCancelButton.style.display = 'none';
      }
      else {
        dialogCancelButton.style.display = 'inline-block';
      }
      
      var confirmHandler = function() {
        if(typeof confirmCallback === 'function') {
          if(!dialogInput) {
            cleanup();
            confirmCallback();
            return;
          } 
          var inputs = dialogInputContainer.querySelectorAll('input');
          if(inputs.length === 1 && inputs[0] === dialogInput) {
            var value = dialogInput.value;
            cleanup();
            confirmCallback(value);
            return;
          }
          var values = {
            unnamedDialogInputs: []
          };
          forEach(inputs, function(input) {
            if(input.name) {
              values[input.name] = input.value;
            }
            else {
              values.unnamedDialogInputs.push(input.value);
            }
          });
          cleanup();
          confirmCallback(values);
        }
        else {
          cleanup();
        }
      };

      var cancelHandler = function() {
        cleanup();
        if(typeof cancelCallback === 'function') {
          cancelCallback();
        } 
      };

      dialog.enterKeyHandler = confirmHandler;
      dialog.escapeKeyHandler = cancelHandler;

      var cleanup = function() {
        dialog.isOpen = false;
        dialogConfirmButton.removeEventListener('click', confirmHandler);
        dialogCancelButton.removeEventListener('click', cancelHandler);
        dialogScreen.style.display = 'none';
        forEach(focusables, function(focusable) {
          if(focusable) {
            focusable.removeEventListener('focus', dialog.focusHandler);
          }
        });
        if(dialogType === 'input') {
          dialogInputContainer.innerHTML = '';
        }
        dialogContainer.className = '';
        dialog.enterKeyHandler = null;
        dialog.escapeKeyHandler = null;
        dialog.storeOldPosition();
      };
      dialogConfirmButton.addEventListener('click', confirmHandler);
      dialogCancelButton.addEventListener('click', cancelHandler);
      dialogMessage.innerHTML = message;
      var x = dialog.movable.mouse.x - dialogContainer.clientWidth / 2;
      var y = dialog.movable.mouse.y + 20;
      dialogContainer.style.left = x + 'px';
      dialogContainer.style.top = y + 'px';
      dialog.fixDialogPosition();
      
      if(dialogType === 'input') {
        dialogInput.focus();
        dialogInput.select();
      }
      else {
        dialogConfirmButton.focus();
      }
    },
    
    fixDialogPosition: function() {
      dialog.movable.fixPosition();
    },
    
    storeOldPosition: function() {
      dialog.movable.oldX = dialog.movable.curX;
      dialog.movable.oldY = dialog.movable.curY;
    },
    
    moveToOldPosition: function() {
      dialog.movable.target.style.left = dialog.movable.oldX + 'px';
      dialog.movable.target.style.top = dialog.movable.oldY + 'px';
      dialog.fixDialogPosition();
    },
    
    class: function(newClass) {
      dialog.movable.target.className = newClass; 
    }
    
  };
  
  onLoad(dialog.init);
  