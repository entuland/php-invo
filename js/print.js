
  var cover = cover || false;
  var ajax = ajax || false;
  var dialog = dialog || false;

  var print = {
    
    askCardNumbers: function(value) {
      var msg = t('Enter a single customer number or a range of numbers to be printed (e.g. "10-20")');
      dialog.input('text', msg, value, function(response) {
        var parts = response.split('-');
        var numbers = [];
        forEach(parts, function(part) {
          var number = part.replace(/\D/g, '');
          if(number) {
            numbers.push(number);
          }
        });
        if(numbers.length < 1 || numbers.lenght > 2) {
          dialog.alert(t('Invalid customer number range'), function() {
            print.askCardNumbers(response);
          });
          dialog.class('error');
          return;
        }
        var start = numbers[0];
        var stop = numbers[1] || false;
        print.cards(start, stop);
      });
    },
    
    multipleLabels: function(id) {
      dialog.input(
        'number',
        t('Enter the amount of labels to print'), 
        1, 
        function(amount) {
          if(amount <= 0) {
            return;
          }
          var ids = [];
          while(amount--) {
            ids.push(id);
          }
          print.getPrintCacheUrl('item', ids, 'labels', print.processResponse);
        });
    },
     
    cards: function(start, stop) {
      if(!start && !stop) {
        print.askCardNumbers();
        return;
      }
      var ids = [];
      if(!stop) {
        ids.push(start);
      } else {
        if(stop < start) {
          var pass = stop;
          stop = start;
          start = pass;
        }
        for(var i = start; i <= stop; ++i) {
          ids.push(i);
        }
      }
      print.getPrintCacheUrl('person', ids, 'cards', print.processResponse);
    },
    
    selected: function(context) {
      var checkers = document.querySelectorAll('tr.selected .list-column [type="checkbox"]');
      print.checkers(checkers, context);
    },
    
    all: function(context) {
      var checkers = document.querySelectorAll('.list-column [type="checkbox"]');
      print.checkers(checkers, context);
    },
    
    checkers: function(checkers, context) {
      var ids = [];
      var classInput = document.querySelector('input[name="list-class"]');
      if(!classInput || !classInput.value) {
        cover.hide();
        return;
      }
      var className = classInput.value;
      forEach(checkers, function(ch) {
        var id = ch.name.replace(/\D/g, '');
        var quantity = 0;
        var itemID = 0;
        var forceChangeLabels = false;
        
        if(classInput.value === 'change' && context === 'labels') {
          forceChangeLabels = true;
          var tr = parentMatchingQuery(ch, 'tr');
          var quantityElement = tr.querySelector('.field-change');
          var itemIdElement = tr.querySelector('.field-id_item [href]');
          quantity = parseInt(quantityElement.textContent);
          itemID = parseInt(itemIdElement.href.replace(/\D/g, ''));
          if(!quantity || quantity < 0) {
            quantity = 0;
          }
        }

        if(context === 'cards') {
          var tr = parentMatchingQuery(ch, 'tr');
          var td = tr.querySelector('.field-customer_number');
          var customer_number = td.textContent.replace(/\D/g, '');
          if(customer_number) {
            ids.push(customer_number);
          }
        }
        else if(forceChangeLabels) {
          if(quantity && itemID) {
            while(quantity--) {
              ids.push(itemID);
            }
          }
        }
        else if(id) {
          ids.push(id);
        }
        
      });
      
      if(className === 'change' && context === 'labels') {
        className = 'item';
      }
      print.getPrintCacheUrl(className, ids, context, print.processResponse);
    },
    
    getPrintCacheUrl: function(className, ids, context, callback) {
      var postValues = {
        action: 'get-print-cache-url',
        class: className,
        ids: ids.join(','),
        context: context
      };
      ajax.run(postValues, callback);    
    },

    processResponse: function(response) {
      if(response.success) {
        window.open(response.url, '_blank');
      }
      cover.hide();
    },
    
    balance: function(personID, context) {
      ajax.run({
        action: 'get-print-balance-cache-url',
        personID: personID,
        context: context
      }, print.processResponse);
    }
    
  };