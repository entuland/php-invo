  var app = app || false;
  var cover = cover || false;
  var msg = msg || false;
  var keyboard = keyboard || false;
  var ajax = ajax || false;
  
  var forms = {
    form: null,
    submitted: false,
    doUniqueValidation: false,
    
    init: function() {
      forms.form = document.forms.length ? document.forms[0] : null;
      if(!forms.form) {
        return;
      }
      var focused = false;
      forEach(forms.form.elements, function(element) {
        if(!focused && keyboard.isFocusable(element)) {
          element.focus();
          element.select && element.select();
          focused = true;
        }
      });
      if(window.location.pathname !== '/') {
        forms.bindClearButtons();
        forms.prepareValidator();
      }
      forms.bindInputSelection();
      forms.enrichClosedRequestField();
      forms.autofitTextareas();
      window.addEventListener('beforeunload', forms.aboutToLeave);
    },
    
    aboutToLeave: function(ev) {
      if(forms.submitted) {
        return;
      }
      var dirty = false;
      forEach(forms.form.elements, function(el) {
        if(!dirty && forms.checkDirty(el)) {
          dirty = true;
        }
      });
      if(dirty) {
        cover.hide();
        ev.returnValue = t('There are unsaved changes in this page, do you really want leave?');
        ev.preventDefault();
      }
    },
    
    bindInputSelection: function () {
      var inputs = document.querySelectorAll('input');
      forEach(inputs, function(input) {
        input.addEventListener('focus', function() {
          input.select();
        });
      });
    },
    
    enrichClosedRequestField: function() {
      var closedField = document.querySelector('.record-request input[name="closed"]');
      if(!closedField) {
        return;
      }
      var closeButton = document.createElement('a');
      var openButton = document.createElement('a');
      openButton.className = 'button';
      closeButton.className = 'button';
      openButton.textContent = t('open');
      closeButton.textContent = t('close');
      closedField.parentNode.appendChild(openButton);
      closedField.parentNode.appendChild(closeButton);
      openButton.addEventListener('click', function() {
        closedField.value = '';
      });
      closeButton.addEventListener('click', function() {
        closedField.value = new Date().toJSON().slice(0, 10);
      });
    },
    
    autofitTextareas: function() {
      var textareas = document.querySelectorAll('textarea');
      var margin = 15;
      forEach(textareas, function(textarea) {
        textarea.style.minHeight = '3em';
        var autofit = function() {
          textarea.style.height = (textarea.scrollHeight + margin) + 'px';
          for(var i = 0; i < 50; ++i) {
            textarea.style.height = (textarea.scrollHeight - 1) + 'px';
            if(textarea.scrollHeight > textarea.clientHeight) {
              break;
            }
          }
          textarea.style.height = (textarea.scrollHeight + margin * 2) + 'px';
        };
        textarea.addEventListener('keydown', autofit);
        autofit();
      });
    },
    
    bindClearButtons: function () {
      var clears = document.getElementsByClassName('button clear');
      forEach(clears, function(clear) {
        clear.addEventListener('click', forms.clearForm);
      });
    },
    
    clearForm: function() {
      cover.hide();
      var elements = forms.form.elements;
      forEach(elements, function(el) {
        var invalid = [
          'hidden',
          'datetime-local',
          'date',
          'reset',
          'submit'
        ];
        var valid = invalid.indexOf(el.type) === -1;
        var writeable = !el.readOnly;
        if(valid && writeable) {
          if(el.tagName === 'SELECT') {
            el.value = el.children[0].value;
          }
          else {
            el.value = "";
          }
        }
      });
    },

    prepareValidator: function() {
      document.forms[0].addEventListener('submit', function(ev) {
        if(forms.submitted || !forms.validate()) {
          ev.preventDefault();
          return false;
        }
        forms.submitted = true;
      });
      
      forEach(forms.form.elements, function(el) {
        el.addEventListener('blur', forms.validate);
      });
      
      forms.class = forms.form.dataset.uniqueClass;
      forms.field = forms.form.dataset.uniqueField;
      
      if(!forms.class || !forms.field) {
        return;
      }
            
      forms.input = document.querySelector('[name="'+forms.field+'"]');
      forms.id = document.querySelector('[name="id"]');
      
      if(app.data.unique[forms.class] && app.data.unique[forms.class][forms.field]) {
        forms.values = app.data.unique[forms.class][forms.field].values;
        forms.doUniqueValidation = 
          forms.class
          && forms.field
          && forms.values
          && forms.input
          && forms.id;
      }
      if(forms.doUniqueValidation) {
        var notice = document.createElement('div');
        notice.textContent = '[unique: ' + forms.class + ' ' + forms.field + ']';
        notice.style.textAlign = 'right';
        notice.style.opacity = '0.5';
        notice.style.fontSize = '12px';
        forms.form.appendChild(notice);
      }
    },
    
    idFromValue: function(value) {
      for(var i in forms.values) {
        if(value === forms.values[i]) {
          return i;
        }
      }
      return false;
    },
    
    checkDirty: function(el) {
      if(el.classList.contains('never-dirty')) {
        return;
      }
      var type = el.type;
      var dirty = false;
      if (type === "checkbox" || type === "radio") {
        if (el.checked !== el.defaultChecked) {
          dirty = true;
        }
      }
      else if(['hidden', 
                'number',
                'password',
                'text',
                'textarea',
                'datetime-local',
                'date'
              ].indexOf(type) !== -1) {
        if (el.value !== el.defaultValue) {
          dirty = true;
        }
      }
      else if (type === "select-one" || type === "select-multiple") {
        for (var j = 0; j < el.options.length; j++) {
          if (el.options[j].selected !==
              el.options[j].defaultSelected) {
            dirty = true;
          }
        }
      }
      el.classList.toggle('dirty', dirty);
      return dirty;
    },
    
    validateFields: function() {
      var errors = [];
      forEach(forms.form.elements, function(el) {
        forms.checkDirty(el);
        if(!el.checkValidity()) {
          errors.push({
            parent: el.parentNode, 
            message: t('This field is required') + ': ' + t(el.name)
          });
        }
      });
      return errors;
    },
    
    validateUnique: function() {
      if(forms.doUniqueValidation) {
        var value = forms.input.value.toUpperCase().trim();
        var newcodes = [
          'NEWCODE', '*', '-', '/', '+'
        ];
        if(forms.class === 'item' && forms.field === 'barcode' && newcodes.indexOf(value) !== -1) {
          ajax.syncRun({
            action: 'get-new-item-code'
          }, function(response) {
            if(response.newcode) {
              value = response.newcode;
              forms.input.value = value;
            }
          });
        }
        var curID = forms.id.value;
        var storedID = forms.idFromValue(value);
        var differentID = curID !== storedID;
        if(value && storedID && differentID) {
          var error = 
            t("Duplicated value found")
              + ": <strong>"
              + '<a class="button" href="' + app.data.publicBase + '/' + forms.class + '/' + storedID + '/edit">'
              + t(forms.class) + ' ' + value
              + "</a>"
              + "</strong>";
          return {
            parent: forms.input.parentNode,
            message: error
          };
        }
      }
      return false;
    },
    
    validate: function() {
      // msg.clear();
      var errors = forms.validateFields();
      var uniqueError = forms.validateUnique();
      if(uniqueError) {
        errors.push(uniqueError);
      }
      if(errors.length) {
        forEach(errors, function(error) {
          msg.staticError(error.message, error.parent, true); 
        });
        cover.hide();
        return false;
      }
      return true;
    }
    
  };
    
  onLoad(forms.init);
  