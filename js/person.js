  var ajax = ajax || false;
  var dialog = dialog || false;
  var deleter = deleter || false;

  var person = {
    
    init: function() {
      person.updateBox();
    },
    
    activeNumber: function() {
      var currentPersonElement = document.getElementById('current-person');
      if(!currentPersonElement) {
        return 0;
      }
      return currentPersonElement.dataset.currentPersonNumber;
    },
    
    updateBox: function(markup) {
      var personsBox = document.getElementById('persons-box-container');
      if(!personsBox) {
        return;
      }
      if(markup) {
        personsBox.innerHTML = markup;
      }
      person.bindBoxes(personsBox);
      deleter.bind(personsBox);
    },
    
    setActive: function(number) {
      number = (''+number).replace(/\D/g, '');
      ajax.run({
        action: 'activate-person',
        number: number
      }, function(response) {
        if(response.success) {
          person.updateBox(response.personsBox);
        }
      });
    },
    
    bindBoxes: function(personsBox) {
      var pending = personsBox.querySelectorAll('.pending-person-box');
      forEach(pending, function(box) {
        var boxNumber = box.dataset.pendingPersonNumber;
        if(boxNumber) {
          box.style.cursor = 'pointer';
          box.title = t('Activate this person');
          box.addEventListener('click', function() {
            person.setActive(boxNumber);
          });
        }
      });
    },
    
    deposit: function(personID, amount) {
      var msg = t('Enter the payment amount');
      var defaultNotes = t('Payment on purchase');
      var depositError = t('The payment must be zero (to use existing credit) or positive');
      if(amount < 0) {
        amount = 0;
        msg += '<br>' + t('Existing credit will be used too');
        defaultNotes = t('existing credit');
      }
      dialog.input('number', msg, amount, function(deposit) {
        if(!deposit || deposit < 0) {
          dialog.alert(depositError);
          dialog.class('error');
          return;
        }
        msg = t('Enter the payment notes');
        dialog.input('text', msg, defaultNotes, function(notes) {
          ajax.run({
            action: 'create-deposits',
            personID: personID,
            deposit: deposit,
            notes: notes
          }, function(response) {
            if(response.personsBox) {
              person.updateBox(response.personsBox);
            }
          });
        });
      });
    }
    
  };
  
  onLoad(person.init);