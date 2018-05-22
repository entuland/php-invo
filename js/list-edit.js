  var dialog = dialog || false;
  var cover = cover || false;

  var listEdit = {
    
    init: function() {
      listEdit.bindForms();
      listEdit.bindFieldToggles();
      listEdit.bindListCheckers();
    },
        
    bindForms: function() {
      var listForms = document.querySelectorAll('form.list');
      forEach(listForms, function(form) {
        form.addEventListener('submit', function(ev) {
          if(form.dataset.confirmed === 'confirmed') {
            return true;
          }
          var msg = t('Confirm mass edit?');
          if(form.classList.contains('list-delete')) {
            msg = t('Confirm mass delete?');
          }
          dialog.confirm(msg, function() {
            form.dataset.confirmed = 'confirmed';
            form.submit();
          });
          dialog.class('list');
          ev.preventDefault();
          cover.hide();
          return false;
        });
      });
    },

    bindFieldToggles: function() {
      var editFieldToggles = document.querySelectorAll('input[type="checkbox"].list-toggle');  
      forEach(editFieldToggles, function(toggle) {
        var fieldName = toggle.dataset.controlledField;
        var field = document.querySelector('[name="' + fieldName + '"]');
        toggle.addEventListener('change', function() {
          listEdit.updateFieldVisibility(field, toggle);
        });
        listEdit.updateFieldVisibility(field, toggle);
      });
    },
    
    updateFieldVisibility: function(field, toggle) {
      field.parentNode.style.visibility = toggle.checked ? 'visible' : 'hidden';
      if(toggle.checked) {
        field.focus();
        field.select && field.select();
      }
    },
    
    bindListCheckers: function() {
      var checkers = document.querySelectorAll('.countable-checkbox');
      forEach(checkers, function(ch) {
        var containerId = ch.dataset.containerId;
        var countId = ch.dataset.countId;
        var containerDiv = document.getElementById(containerId);
        var countableSubset = containerDiv.querySelectorAll('.countable-checkbox');
        ch.addEventListener('change', function() {
          var countSpan = document.getElementById(countId);
          var sum = 0;
          forEach(countableSubset, function(countable) {
            if(countable.checked) {
              ++sum;
            }
          });
          countSpan.innerHTML = sum;
          ch.parentNode.parentNode.parentNode.classList.toggle('selected', ch.checked);
        });
      });
    }
    
  };
  
  onLoad(listEdit.init);
  