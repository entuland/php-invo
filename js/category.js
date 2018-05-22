
  var app = app || false;

  var category = {
    level: 1,
    controls: [],
    datalists: [],
    
    init: function() {
      category.input = document.querySelector('input[name="category"]');
      if(!category.input) {
        return;
      }
      category.input.style.display = 'none';
      var obj = app.data.unique.category.category.values;
      category.values = Object.keys(obj).map(key => obj[key]);
      category.parse();      
    },
    
    parse: function() {
      category.deleteControls();
      var value = category.input.value;
      var parts = value.split('|');
      category.level = parts.length;
      if(value !== '') {
        ++category.level;
      }
      category.createControls();
      category.fillControls(parts);
      category.restoreDatalists();
    },
    
    deleteControls: function() {
      forEach(category.controls, function(control) {
        control.parentNode.removeChild(control);
      });
      category.controls = [];
    },
    
    createControls: function(setFocus) {
      var count = category.controls.length;
      if(count > 4) {
        return;
      }
      for(var i = count; i < category.level; ++i) {
        var control = document.createElement('input');
        control.type = 'text';
        control.setAttribute('maxlength', 32);
        control.placeholder = t('category') + ' ' + (i+1); 
        category.input.parentNode.appendChild(control);
        control.addEventListener('change', category.controlChanged);
        category.controls.push(control);
        control.dataset.level = i;
        if(setFocus) {
          setTimeout(function() {
            control.focus();
            control.select();
          }, 50);
        }
      }
    },
    
    controlChanged: function(ev) {
      if(parseInt(ev.target.dataset.level) === category.level - 1) {
        ++category.level;
        category.createControls(true);
      }
      ev.target.value = ev.target.value
              .replace(/\|/g, ' ')
              .replace(/\s+/g, ' ')
              .trim();
      category.restoreDatalists();
      category.updateItem();
    },
    
    updateItem: function() {
      var parts = [];
      forEach(category.controls, function(control) {
        var value = control.value.trim().toUpperCase();
        if(value) {
          parts.push(value);
        }
      });
      category.input.value = parts.join('|');
    },
    
    fillControls: function(parts) {
      forEach(category.controls, function(control, index) {
        if(parts[index]) {
          control.value = parts[index];
          control.defaultValue = parts[index]; 
        }
      });
    },
    
    restoreDatalists: function() {
      if(category.restoreTimeoutId) {
        clearTimeout(category.restoreTimeoutId);
      }
      category.restoreTimeoutId = setTimeout(function() {
        forEach(category.controls, function(control, index) {
          var listId = category.createDatalist(index);
          control.setAttribute('list', listId);
        });
      }, 250);
    },
    
    createDatalist: function(level) {
      var id = 'category-datalist-' + level;
      var datalist = false;
      if(!category.datalists[level]) {
        datalist = document.createElement('datalist');
        datalist.id = id;
        document.body.appendChild(datalist);
        category.datalists[level] = datalist;
      } else {
        datalist = category.datalists[level];
      }
      datalist.innerHTML = category.getOptions(level);
      return id;
    },
    
    getOptions: function(level) {
      var cats = [];
      for(var i = 0; i < level; ++i) {
        var value = category.controls[i].value.trim().toUpperCase();
        if(value) {
          cats.push(value);
        }
      }
      var parentCat = cats.join('|');
      var matching = category.values.filter(function(value) {
        return parentCat === '' || value.indexOf(parentCat) === 0;
      });
      var options = matching.map(function(value) {
        var parts = value.split('|');
        return parts[level];
      });
      options = options.filter(uniqueFilter);
      return '<option>' + options.join('</option><option>') + '</option>';
    }
    
  };
  
  onLoad(category.init);


