  var app = app || false;
  var ajax = ajax || false;
  var cover = cover || false;
  var msg = msg || false;
  var rapidEdit = rapidEdit || false;
  var deleter = deleter || false;
  var dialog = dialog || false;
  var gallery = gallery || false;  
  var collapsible = collapsible || false;
  var listSelect = listSelect || false;
  var tables = tables || false;
  var autoprice = autoprice || false;
  var keyboard = keyboard || false;
  var person = person || false;
  
  var rapidChange = {
    form: {},
    isHome: false,
    preparationResponse: {},
    
    init: function() {
      if(window.location.pathname !== app.data.publicBase + '/') {
        return;
      }
      rapidChange.alternateMode = !!document.querySelector('#main.alternate');
      rapidChange.isHome = true;
      rapidChange.form = document.forms[0];
      rapidChange.barcode = rapidChange.form.querySelector('.field-barcode input');
      rapidChange.barcode.classList.add('never-dirty');
      rapidChange.updateSearchDropdown();
      var timeoutID = 0;
      rapidChange.form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        if(timeoutID) {
          clearTimeout(timeoutID);  
        }
        timeoutID = setTimeout(rapidChange.performSearch, 100);
        return false;
      });
      var barcode = getQueryVariable('barcode');
      if(barcode) {
        rapidChange.barcode.value = barcode;
        rapidChange.performSearch();
      }
    },
    
    performSearch: function() {
      var barcode = rapidChange.barcode.value.trim();
      var customer_prefix = app.settings.customer_number_prefix;
      if(customer_prefix) {
        var pattern = new RegExp('^' + customer_prefix + '\\d+', 'i');
        if(barcode.match(pattern) || barcode.match(/^[pc]\d+/i)) {
          person.setActive(barcode);
          rapidChange.restart();
          return;
        }
        var old_pattern = new RegExp('z\\d+', 'i');
        if(barcode.match(old_pattern)) {
          var number = barcode.replace(/\D/m, '');
          window.location = '/item/filter/barcode/z%25' + number;
          return;
        }
      }
      if(!barcode) {
        msg.clear();
        rapidChange.restart();
        return;
      }
      if(rapidChange.latestSearch === barcode) {
        if(msg.noMessages()) {
          if(!rapidChange.firstWarningSkipped) {
            rapidChange.firstWarningSkipped = true;
            return;
          }
          msg.staticWarning(
            t('You have just searched this barcode, the item is displayed below'), 
            rapidChange.barcode.parentNode,
            true
          );
        }
        else {
          msg.blink();
        }
        return;
      }
      rapidChange.firstWarningSkipped = false;
      rapidChange.latestSearch = barcode;
      rapidChange.closeDropdown();
      rapidChange.cleanup();
      msg.clear();
      rapidChange.prepareRapidChange(barcode, rapidChange.processPreparationResponse);       
    },
    
    prepareRapidChange: function(barcode, callback) {
      var postValues = {
        action: 'prepare-rapid-change',
        class: 'item',
        barcode: barcode,
        id_document: app.data.document.id.stored,
      };
      ajax.run(postValues, callback);
    },

    processPreparationResponse: function(response) {
      if(response.success) {
        rapidChange.addToSearchHistory(response.item);
        rapidChange.preparationResponse = response;
        rapidChange.outputItem();
        if(rapidChange.alternateMode) {
          rapidChange.askLoadCombo();
        } else {
          rapidChange.askUnloadCombo();
        }
        return;
      }
      
      var barcode = rapidChange.barcode.value.trim();
      if(response.newcode) {
        barcode = response.newcode;
      }
      
      var newLink = app.data.publicBase + '/item/new?barcode=' + barcode + '&cur_doc_id=' + app.data.document.id.stored;
      var newButton = '<a class="button" href="' + newLink + '">'
        + t('Create new item for %s', barcode)
        + '</a>';
     
      if(rapidChange.alternateMode) {
        cover.show();
        window.location = newLink;
        return;
      }
      else {
        msg.staticError(
          response.error + newButton, 
          rapidChange.barcode.parentNode,
          true
        );
        cover.bindAnchors(rapidChange.barcode.parentNode);
        setTimeout(function() {
          rapidChange.barcode.focus();
          rapidChange.barcode.select();
        }, 100);
      }
    },
    
    // =========================================================================
    // alternate combo (load) mode
    
    askLoadCombo: function(values) {
      var priceCell = rapidChange.itemDiv.querySelector('.field-price');
      var price = textToFloat(priceCell.textContent);
      var text = rapidChange.getOriginalPriceText(price);
      dialog.input('number', text, price,
        rapidChange.checkLoadCombo,
        rapidChange.restart
      );
      dialog.class('load-combo');
      rapidChange.enrichLoadDialog(values);
      rapidChange.fixDialogPosition();
    },
        
    checkLoadCombo: function(values) {
      var errors = [];
      if(values.price <= 0) {
        errors.push(t('Selling price must be positive'));
      }
      if(values.purchase <= 0) {
        errors.push(t('Purchase price must be positive'));
      }
      if(values.load <= 0) {
        errors.push(t('New load must be positive'));
      }
      if(errors.length) {
        dialog.alert(errors.join('<br>'), function() {
          rapidChange.askLoadCombo(values);
        });
        dialog.class('error');
        return;
      }
      rapidChange.executeLoadComboRequest(values);
    },
    
    executeLoadComboRequest: function(values) {
      var priceCell = document.querySelector('.display-item .field-price');
      var preparation = rapidChange.preparationResponse;
      var className = priceCell.parentNode.dataset.class;
      var recordID = priceCell.parentNode.dataset.id;
      var fieldName = 'price';
      rapidEdit.changeField(className, recordID, fieldName, values.price, function(response) {
        if(response.success) {
          var value = response.fields[fieldName].displayed;
          priceCell.innerHTML = value;
          rapidEdit.styleCell(priceCell);
          rapidChange.rapidLoadUnload(
            values.load, 
            values.purchase,
            preparation.item.id,
            preparation.document.id,
            rapidChange.processLoadComboResponse
          );
        }
      });
    },
    
    processLoadComboResponse: function(response) {
      rapidChange.processLoadUnloadUnifiedResponse(response, t('Alternate load performed successfully'));
    },
    
    // =========================================================================
    // normal combo (unload) mode
    
    askUnloadCombo: function(values) {
      var priceCell = rapidChange.itemDiv.querySelector('.field-price');
      var price = textToFloat(priceCell.textContent);
      var discount = rapidChange.getDiscountData(price);
      dialog.input('number', discount.text, discount.price,
        rapidChange.checkUnloadCombo,
        rapidChange.restart
      );
      dialog.class('unload-combo');
      rapidChange.enrichUnloadDialog(values);
      rapidChange.addDiscountButtonsToDialog(discount);
      rapidChange.fixDialogPosition();
    },
        
    checkUnloadCombo: function(values) {
      var errors = [];
      if(values.price <= 0) {
        errors.push(t('Selling price must be positive'));
      }
      if(values.unload.trim() === '' || values.unload > 0) {
        errors.push(t('New unload must be negative or zero (for trial items)'));
      }
      var personData = document.getElementById('current-person');
      if(values.unload === '0' && (!personData || !personData.dataset.currentPersonName.trim())) {
        errors.push(t('Cannot set an item in trial without assigning a person to it'));
      }
      if(errors.length) {
        dialog.alert(errors.join('<br>'), function() {
          rapidChange.askUnloadCombo(values);
        });
        dialog.class('error');
        return;
      }
      rapidChange.executeUnloadComboRequest(values);
    },
    
    executeUnloadComboRequest: function(values) {
      var preparation = rapidChange.preparationResponse;
      rapidChange.rapidLoadUnload(
        values.unload, 
        values.price,
        preparation.item.id,
        preparation.document.id,
        rapidChange.processUnloadComboResponse
      );
    },
    
    processUnloadComboResponse: function(response) {
      rapidChange.processLoadUnloadUnifiedResponse(response, t('Regular unload performed successfully'));
    },
    
    // =========================================================================
    // unified query & response handling
    
    rapidLoadUnload: function(new_change, new_price, id_item, id_document, callback) {
      var postValues = {
        action: 'load-unload',
        change: new_change,
        price: new_price,
        id_item: id_item,
        id_document: id_document,
        customer_number: person.activeNumber()
      }; 
      ajax.run(postValues, callback);
    },
    
    processLoadUnloadUnifiedResponse: function(response, message) {
      if(response.success) {
        var stockCell = rapidChange.itemDiv.querySelector('.field-stock');
        stockCell.innerHTML = response.new.stock;
        rapidEdit.styleCell(stockCell);
        rapidChange.updateHomeContainer(response.new.markup);
        dialog.alert(message, rapidChange.restart);
        dialog.moveToOldPosition();
        if(response.new.personsBox) {
          person.updateBox(response.new.personsBox);
        }
      }
      else {
        var text = '';
        forEach(response.errors, function(error) {
          text += error;
        });
        if(text !== '') {
          dialog.alert(text, rapidChange.restart);
          dialog.moveToOldPosition();
        }
      }
    },
    
    updateItem: function(item) {
      var response = rapidChange.preparationResponse;
      if(!item || !response || !response.item) {
        return;
      }
      if(response.item.id === item.id) {
        rapidChange.cleanup();
        response.item = item;
        rapidChange.outputItem();
      }
    },
    
    outputItem: function() {
      var response = rapidChange.preparationResponse;
      var div = document.createElement('div');
      rapidChange.itemDiv = div;
      div.innerHTML = response.item.display;
      rapidChange.form.parentNode.insertBefore(div, rapidChange.form.nextSibling);
      rapidChange.addUnloadButton(div);
      rapidChange.addLoadButton(div);
      rapidEdit.processElements(rapidChange.itemDiv);
      cover.bindAnchors(rapidChange.itemDiv);
      deleter.bind(rapidChange.itemDiv);
      setTimeout(gallery.init, 100);
    },
    
    addLoadButton: function(parent) {
      if(rapidChange.alternateMode || app.settings.allowDailyLoads) {
        var loadButton = document.createElement('a');
        parent.appendChild(loadButton);
        loadButton.className = 'button load';
        loadButton.innerHTML = t('rapid load');
        loadButton.addEventListener('click', function() {
          rapidChange.askLoadCombo();
        });
      }
    },

    addUnloadButton: function(parent) {
      if(!rapidChange.alternateMode || app.settings.allowAlternateUnloads) {
        var unloadButton = document.createElement('a');
        parent.appendChild(unloadButton);
        unloadButton.className = 'button unload';
        unloadButton.innerHTML = t('rapid unload');
        unloadButton.addEventListener('click', function() {
          rapidChange.askUnloadCombo();
        });
      }
    },

    updateHomeContainer: function(markup) {
      var homeContainer = document.querySelector('#home-list .collapsible-content');
      if(homeContainer) {
        homeContainer.innerHTML = markup;
        rapidEdit.processElements(homeContainer);
        cover.bindAnchors(homeContainer);
        listSelect.bindAll(homeContainer);
        deleter.bind(homeContainer);
        collapsible.bind(homeContainer);
        tables.processTablesOf(homeContainer);
      }
    },
    
    // =========================================================================
    // helper functions
    
    restart: function() {
      if(rapidChange.barcode) {
        rapidChange.barcode.value = '';
        rapidChange.barcode.focus();
      }
    },
    
    cleanup: function() {
      if(rapidChange.itemDiv) {
        rapidChange.itemDiv.parentNode.removeChild(rapidChange.itemDiv);
        rapidChange.itemDiv = null;
      }
    },
    
    fixDialogPosition: function() {
      var reference = document.querySelector('td.additional');
      var rect = reference.getBoundingClientRect();
      if(reference) {
        dialog.movable.target.style.top = 
          (rect.top + rect.height + 5) + 'px';
      }
      dialog.fixDialogPosition();
    },
    
    getOriginalPriceText: function(sellingPrice) {
      var lines = [];
      var productCaption = document.querySelector('.display-item caption');
      if(productCaption) {
        lines.push(t('item') + ': <span class="contrast">' + productCaption.textContent + '</span>');
      }
      lines.push(t('Original price') + ': <span class="contrast price">&euro; ' + sellingPrice.toFixed(2) + '</span>');
      return lines.join('<br>');
    },
    
    // =========================================================================
    // search history

    updateSearchDropdown: function() {
      var history = rapidChange.loadSearchHistory();
      var id = 'search-history';
      var dropdown = document.getElementById(id);
      if(!dropdown) {
        dropdown = document.createElement('select');
        dropdown.classList.add('never-dirty');
        rapidChange.barcode.parentNode.appendChild(dropdown);
        dropdown.addEventListener('change', function() {
          rapidChange.barcode.value = dropdown.value;
          rapidChange.performSearch();
        });
        dropdown.id = id;
      } else {
        dropdown.innerHTML = '';
      }
      var option = document.createElement('option');
      option.textContent = '-- ' + t('Latest searches') + ' --';
      option.setAttribute('selected', true);
      option.setAttribute('disabled', true);
      dropdown.appendChild(option);
      forEachRev(history, function(item) {
        option = document.createElement('option');
        option.value = item.barcode;
        option.textContent = item.barcode + ' ' + item.name;
        dropdown.appendChild(option);
      });
    },
    
    addToSearchHistory: function(item) {
      var history = rapidChange.loadSearchHistory();
      if(history.length && history[history.length-1].barcode === item.barcode) {
        return;
      }
      history.push({
        barcode: item.barcode,
        name: item.name
      });
      if(history.length > app.settings.searchHistoryLimit) {
        history = history.slice(-app.settings.searchHistoryLimit);
      }
      rapidChange.saveSearchHistory(history);
      rapidChange.updateSearchDropdown();
    },
    
    loadSearchHistory: function() {
      var history = [];
      try {
        history = JSON.parse(localStorage.getItem('searchHistory'));
      } catch(e) {
        // do nothing on purpose
      }
      if(!history) {
        return [];
      }
      return history;
    },
    
    saveSearchHistory: function(history) {
      localStorage.setItem('searchHistory', JSON.stringify(history));
    },
    
    closeDropdown: function() {
      rapidChange.barcode.style.display = "none";
      setTimeout(function () {
          rapidChange.barcode.style.display = "block";
      }, 10);
    },
        
    // =========================================================================
    // discount handling

    getDiscountData: function(originalPrice) {
      var result = {
        originalPrice: originalPrice,
        price: originalPrice,
        text: t('Insert the change price'),
        default: [] 
      };
      var discountDataElement = document.getElementById('discount-data');
      if(!discountDataElement) {
        return result;
      }
      var jsonDiscounts = discountDataElement.dataset.discounts;
      var discounts = JSON.parse(jsonDiscounts);
      if(discounts === null 
        || typeof discounts !== 'object') {
        return result;
      }
      result.default = discounts.default;
      var lines = [];
      
      var personData = document.getElementById('current-person');
      var personDiscount = 0;
      if(personData) {
        personDiscount = personData.dataset.currentPersonDiscount;
        if(personData.dataset.currentPersonName.trim()) {
          lines.push(
            t('Person') 
            + ': <span class="contrast">' 
            + personData.dataset.currentPersonName
            + '</span>'
          );
          lines.push(
            t('Insert zero for the new unload to register the item as trial')
          );
        }
      }
      
      var productCaption = document.querySelector('.display-item caption');
      if(productCaption) {
        lines.push(t('item') + ': <span class="contrast">' + productCaption.textContent + '</span>');
      }
      lines.push(t('Original price') + ': <span class="contrast price">&euro; ' + originalPrice.toFixed(2) + '</span>');
            
      var personWins = true;
      if(discounts.category.length) {
        var currentCategory = discounts.category[0].name;
        var currentDiscount = discounts.category[0].percent;
        result.default = discounts.default;
        if(currentDiscount > personDiscount) {
          lines.push(t('Discount') 
                  + ': <span class="contrast">' 
                  + currentDiscount + '% ' 
                  + currentCategory 
                  + '</span>');
          result.price = rapidChange.discountAndRound(originalPrice, currentDiscount);
          personWins = false;
        }
      }
      if(personDiscount && personWins) {
        result.default.push(personDiscount);
        lines.push(t('Personal discount') 
                + ': <span class="contrast">' 
                + personDiscount + '%'
                + '</span>');
        result.price = rapidChange.discountAndRound(originalPrice, personDiscount);
      }
      if(discounts.fixedprices) {
        for(var cat in discounts.fixedprices) {
          var fixedPrice = discounts.fixedprices[cat];
          result.price = fixedPrice;
          lines.push(t('fixedprice') 
                  + ': <span class="contrast">' 
                  + cat
                  + '</span>');
          break;
        }
      }
      result.text = lines.join('<br>');
      return result;
    },
    
    discountAndRound: function(price, discount) {
      var rounding = app.settings.defaultDiscountRounding;
      price *= (100 - discount)/100;
      return moneyRound(price, rounding);
    },
    
    addDiscountButtonsToDialog: function(discount) {
      var container = document.getElementById('dialog-input-container');
      var sellingPrice = container.querySelector('input[name="price"]');
      if(!container || !sellingPrice) {
        return;
      }
      forEach(discount.default, function(percent) {
        var button = document.createElement('button');
        button.innerHTML = '' + percent + '%';
        container.appendChild(button);
        button.addEventListener('click', function() {
          sellingPrice.value = rapidChange.discountAndRound(discount.originalPrice, percent);
        });
      });
    },
    
    // =========================================================================
    // dialog manipulation

    enrichLoadDialog: function(values) {
      var inputContainer = document.getElementById('dialog-input-container');
      
      var currentLoadWrapper = document.createElement('div');
      var purchasePriceWrapper = document.createElement('div');
      var multiplierWrapper = document.createElement('div');
      var sellingWrapper = inputContainer.querySelector('.input-wrapper');
      
      var currentLoad = document.createElement('input');
      var purchasePrice = document.createElement('input');
      var multiplier = document.createElement('input');
      var sellingPrice = inputContainer.querySelector('input');
      
      currentLoad.name = 'load';
      purchasePrice.name = 'purchase';
      multiplier.name = 'multiplier';
      sellingPrice.name = 'price';
            
      currentLoadWrapper.textContent = t('New load');
      purchasePriceWrapper.textContent = t('Purchase price');
      multiplierWrapper.textContent = t('Multiplier');
      
      var inputText = document.createTextNode(t('Selling price'));
      sellingWrapper.insertBefore(inputText, sellingPrice);
      
      currentLoadWrapper.appendChild(currentLoad);
      purchasePriceWrapper.appendChild(purchasePrice);
      multiplierWrapper.appendChild(multiplier);
      
      inputContainer.insertBefore(currentLoadWrapper, sellingWrapper);
      inputContainer.insertBefore(purchasePriceWrapper, sellingWrapper);
      inputContainer.insertBefore(multiplierWrapper, sellingWrapper);
      
      currentLoad.addEventListener('keypress', keyboard.keyPress);
      purchasePrice.addEventListener('keypress', keyboard.keyPress);
      multiplier.addEventListener('keypress', keyboard.keyPress);
      
      var themAll = [currentLoad, purchasePrice, multiplier, sellingPrice];
      
      forEach(themAll, function(it) {
        it.type = 'number';
        it.setAttribute('step', '0.01');
        it.addEventListener('focus', function() {
          it.select();
        });
      });
            
      autoprice.bindToParent(inputContainer, rapidChange.preparationResponse.company);
      
      if(values !== null && typeof values === 'object') {
        currentLoad.value = values.load;
        purchasePrice.value = values.purchase;
        multiplier.value = values.multiplier;
        sellingPrice.value = values.price;
      }
            
      setTimeout(function() {
        currentLoad.focus();
      }, 250);     
    },
    
    enrichUnloadDialog: function(values) {
      var inputContainer = document.getElementById('dialog-input-container');
      
      var currentLoadWrapper = document.createElement('div');
      var sellingWrapper = inputContainer.querySelector('.input-wrapper');
      
      var currentUnload = document.createElement('input');
      var sellingPrice = inputContainer.querySelector('input');
      
      currentUnload.name = 'unload';
      sellingPrice.name = 'price';
      
      currentLoadWrapper.textContent = t('New unload');
      
      var inputText = document.createTextNode(t('Selling price'));
      sellingWrapper.insertBefore(inputText, sellingPrice);
      
      currentLoadWrapper.appendChild(currentUnload);
      
      inputContainer.insertBefore(currentLoadWrapper, sellingWrapper);
      
      currentUnload.addEventListener('keypress', keyboard.keyPress);
      
      var themAll = [currentUnload, sellingPrice];
      
      forEach(themAll, function(it) {
        it.type = 'number';
        it.setAttribute('step', '0.01');
        it.addEventListener('focus', function() {
          it.select();
        });
      });
      
      if(values !== null && typeof values === 'object') {
        currentUnload.value = values.unload;
        sellingPrice.value = values.price;
      }
      
      setTimeout(function() {
        currentUnload.focus();
      }, 250);     
    }
    
  };
  
  onLoad(rapidChange.init);
  