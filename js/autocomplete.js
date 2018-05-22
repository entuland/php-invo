  var app = app || false;
  var rapidChange = rapidChange || false;

  var autocomplete = {
    ajaxParams: [],
    
    init: function() {
      var fields = document.querySelectorAll('[data-autocomplete]');
      forEach(fields, function(field) {
        autocomplete.initField(field);
      });
    },
    
    initField: function(field) {
      var data = JSON.parse(field.dataset.autocomplete);
      if(!data || typeof data !== 'object') {
        return;
      }
      if(data.class === 'category') {
        return;
      }
      var unique = app.data.unique[data.class][data.field];
      field.autoMinLen = unique.autolimit;
      field.uniqueValues = unique.values;
      var timeoutID = 0;
      field.addEventListener('input', function() {
        if(timeoutID) {
          clearTimeout(timeoutID);
        }
        timeoutID = setTimeout(function() {
          timeoutID = 0;
          autocomplete.fieldChanged(field);
        }, 250);
      });
      autocomplete.markFieldReady(field);
    },
        
    markFieldReady: function(field) {
      var tr = parentMatchingQuery(field, 'tr');
      if(tr && tr.children) {
        var header = tr.children[0];
        header.classList.add('autocomplete-ready');
        header.setAttribute('title', t('Ready for autocompletion'));
      }
    },
        
    fieldChanged: function(field) {
      app.log('autocomplete.fieldChanged(): field');
      app.log(field);
      if(field.value.length >= field.autoMinLen) {
        var upperValue = field.value.toUpperCase();
        if(field === rapidChange.barcode) {
          if(autocomplete.exactMatch(field.uniqueValues, upperValue)) {
              rapidChange.performSearch();
          }
        }
        var matches = autocomplete.findMatches(
          field.uniqueValues, upperValue
        );
        if(matches.length) {
          autocomplete.addDataList(field, matches);
        }
        var start = field.selectionStart;
        var stop = field.selectionEnd;
        var direction = field.selectionDirection;
        field.value = upperValue;
        field.setSelectionRange(start, stop, direction);
      } 
      else {
        app.log('^^^ autocomplete skipped for minumum length not reached');
      }
    },
    
    findMatches: function(list, test) {
      var result = [];
      for(var i in list) {
        var item = list[i];
        if(item && item.indexOf && item.indexOf(test) === 0) {
          result.push(item);
        }
      };
      app.log('autocomplete.findMatches(): list, test, result');  
      app.log(list);
      app.log(test);
      app.log(result);
      return result;
    },
    
    exactMatch: function(list, test) {
      for(var i in list) {
        if(list[i] === test) {
          return true;
        }
      };
      return false;
    },
    
    addDataList: function(input, options) {
      if(input.value.indexOf(input.dataset.test) === 0) {
        app.log('autocomplete.addDataList() exited for beginmatch');
        return;
      }
      else {
        input.dataset.test = input.value;
      }
      if(!input.autoDataList) {
        input.autoDataList = document.createElement('datalist');
        input.autoDataList.id = input.name + '-datalist';
        document.body.appendChild(input.autoDataList);
      }
      input.removeAttribute('list');
      input.autoDataList.innerHTML = '';
      forEach(options, function(option) {
        var node = document.createElement('option');
        node.value = option;
        input.autoDataList.appendChild(node);
      });
      input.setAttribute('list', input.autoDataList.id);
    }
    
  };
  
  onLoad(autocomplete.init);
  