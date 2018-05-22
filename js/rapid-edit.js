var app = app || false;
var ajax = ajax || false;
var dialog = dialog || false;
var tables = tables || false;
var rapidChange = rapidChange || false;

var rapidEdit = {

  init: function () {
    rapidEdit.processElements();
  },

  processElements: function (parent) {
    parent = parent || document;
    rapidEdit.stockCells = parent.querySelectorAll('.display-row .field-stock');
    rapidEdit.verifiedCells = parent.querySelectorAll('.display-row .field-verified');
    rapidEdit.priceCells = parent.querySelectorAll('.display-row .field-price');
    rapidEdit.changeCells = parent.querySelectorAll('.display-row .field-change');
    rapidEdit.closedRequestCells = parent.querySelectorAll('.list-request .display-row .field-closed');
    rapidEdit._styleAllElements();
    rapidEdit._bindAllElements();
  },

  styleCell: function (cell) {
    var value = textToFloat(cell.textContent);
    var isPrice = cell.classList.contains('field-price');
    if (value !== 0 && isPrice) {
      cell.innerHTML = "&euro;&nbsp;" + value.toFixed(2);
    }
    if (!isPrice || (isPrice && value)) {
      cell.title = t('Click for rapid editing');
      cell.style.cursor = 'pointer';
    } else {
      cell.title = '';
      cell.style.cursor = 'default';
    }
    cell.classList.remove('negative-value');
    cell.classList.remove('zero-value');
    cell.classList.remove('positive-value');
    if (!cell.matches('.purchase,.selling')) {
      switch (true) {
        case value > 0 || lowerTrim(cell.textContent) === lowerTrim(t('yes')):
          cell.classList.add('positive-value');
          break;
        case value < 0 || lowerTrim(cell.textContent) === lowerTrim(t('no')):
          cell.classList.add('negative-value');
          break;
        case value === 0:
          cell.classList.add('zero-value');
      }
    }
    tables.updateZebra(cell);
    if (rapidChange && rapidChange.restart) {
      if (rapidEdit.styleCell.timeout) {
        clearTimeout(rapidEdit.styleCell.timeout);
      }
      rapidEdit.styleCell.timeout = setTimeout(rapidChange.restart, 50);
    }
  },

  _styleAllElements: function () {
    forEach(rapidEdit.stockCells, rapidEdit.styleCell);
    forEach(rapidEdit.verifiedCells, rapidEdit.styleCell);
    forEach(rapidEdit.priceCells, rapidEdit.styleCell);
    forEach(rapidEdit.changeCells, rapidEdit.styleCell);
    forEach(rapidEdit.closedRequestCells, rapidEdit.styleCell);
  },

  _bindElementsClick: function (elements, callback) {
    forEach(elements, function (element) {
      rapidEdit._bindElementClick(element, callback);
    });
  },

  _bindElementClick: function (element, callback) {
    rapidEdit._unbindElementClick(element);
    if (callback === rapidEdit.askPriceValue && !element.textContent.trim()) {
      return;
    }
    var handler = function () {
      callback(element);
    };
    element.rapidEditClickHandler = handler;
    element.classList.add('rapid-edit-owned');
    element.addEventListener('click', handler);
  },

  _unbindElementClick: function (element) {
    if (element.rapidEditClickHandler) {
      element.removeEventListener('click', element.rapidEditClickHandler);
    }
  },

  _bindAllElements: function () {
    rapidEdit._bindElementsClick(rapidEdit.verifiedCells, function (element) {
      var verified = lowerTrim(element.textContent) === lowerTrim(t('yes'));
      var text = t('Do you want to switch verification to "yes"?');
      if (verified) {
        text = t('Do you want to switch verification to "no"?');
      }
      dialog.confirm(text, function () {
        var verified = lowerTrim(element.textContent) === lowerTrim(t('yes'));
        var className = element.parentNode.dataset.class;
        var recordID = element.parentNode.dataset.id;
        ajax.run({
          action: 'edit',
          class: className,
          id: recordID,
          verified: verified ? 0 : 1
        }, function (response) {
          if (response.success) {
            element.innerHTML = response.fields.verified.displayed;
            rapidEdit.styleCell(element);
          }
        });
      });
      dialog.class('verified');
    });

    rapidEdit._bindElementsClick(rapidEdit.priceCells, rapidEdit.askPriceValue);

    rapidEdit._bindElementsClick(rapidEdit.stockCells, rapidEdit.askStockValue);

    rapidEdit._bindElementsClick(rapidEdit.changeCells, rapidEdit.askChangeValue);

    rapidEdit._bindElementsClick(rapidEdit.closedRequestCells, rapidEdit.toggleClosedRequest);
  },

  askChangeValue: function (changeCell) {
    var change = textToFloat(changeCell.textContent);
    var text = t('Insert new value for field') + ' ' + t('change');
    var className = changeCell.parentNode.dataset.class;
    var recordID = changeCell.parentNode.dataset.id;
    var fieldName = 'change';
    dialog.input('number', text, change, function (value) {
      rapidEdit.changeField(className, recordID, fieldName, value, function (response) {
        rapidEdit.changeChangedResponse(response, fieldName, changeCell);
      });
    });
    dialog.class('change');
  },

  changeChangedResponse: function (response, fieldName, changeCell) {
    if (response.success) {
      var value = response.fields[fieldName].displayed;
      changeCell.innerHTML = value;
      var sellingCell = changeCell.parentNode.querySelector('.field-price.selling');
      var purchaseCell = changeCell.parentNode.querySelector('.field-price.purchase');
      if (value > 0) {
        sellingCell.textContent = '';
        purchaseCell.innerHTML = response.fields['price'].displayed;
        rapidEdit._unbindElementClick(sellingCell);
        rapidEdit._bindElementsClick(purchaseCell, rapidEdit.askPriceValue);
      }
      if (value < 0) {
        purchaseCell.textContent = '';
        sellingCell.innerHTML = response.fields['price'].displayed;
        rapidEdit._unbindElementClick(purchaseCell);
        rapidEdit._bindElementsClick(sellingCell, rapidEdit.askPriceValue);
      }
      if (parseInt(value) === 0) {
        changeCell.innerHTML = t('Trial');
      }
      rapidEdit.styleCell(changeCell);
      rapidEdit.styleCell(purchaseCell);
      rapidEdit.styleCell(sellingCell);
      if (response.item
              && rapidChange
              && rapidChange.isHome) {
        rapidChange.updateItem(response.item);
      }
      if (response.personsBox) {
        person.updateBox(response.personsBox);
      }
    }
  },

  askStockValue: function (stockCell) {
    var stock = textToFloat(stockCell.textContent);
    var text = t('Insert new value for field') + ' ' + t('stock');
    dialog.input('number', text, stock, function (stockValue) {
      if (textToFloat(stockValue) < 0) {
        dialog.alert(
                t('Stock quantity cannot be negative'), function () {
          rapidEdit.askStockValue(stockCell);
        });
      } else {
        rapidEdit.askStockPrice(stockCell, stockValue);
      }
    });
    dialog.class('stock');
  },

  askStockPrice: function (stockCell, stockValue) {
    var priceCell = stockCell.parentNode.querySelector('.field-price');
    var price = textToFloat(priceCell.textContent);
    var text = t('Insert the change price');
    dialog.input('number', text, price, function (stockPrice) {
      if (textToFloat(stockPrice) <= 0) {
        dialog.alert(t('Price must be greater than zero'), function () {
          rapidEdit.askStockPrice(stockCell, stockValue);
        });
      } else {
        rapidEdit.changeStock(stockCell, stockValue, stockPrice);
      }
    });
  },

  changeStock: function (cell, stock, price) {
    var className = cell.parentNode.dataset.class;
    var recordID = cell.parentNode.dataset.id;
    ajax.run({
      action: 'change-stock',
      class: className,
      id: recordID,
      stock: stock,
      price: price
    }, function (response) {
      if (response.success) {
        cell.innerHTML = response.fields.stock.displayed;
        var verified = cell.parentNode.querySelector('.field-verified');
        verified.innerHTML = t('yes');
        rapidEdit.styleCell(cell);
        rapidEdit.styleCell(verified);
      }
    });
  },

  askPriceValue: function (priceCell) {
    var price = textToFloat(priceCell.textContent);
    var text = t('Insert new value for field') + ' ' + t('price');
    var className = priceCell.parentNode.dataset.class;
    var recordID = priceCell.parentNode.dataset.id;
    var fieldName = 'price';
    dialog.input('number', text, price, function (value) {
      rapidEdit.changeField(className, recordID, fieldName, value, function (response) {
        rapidEdit.priceChangedResponse(response, fieldName, priceCell);
      });
    });
    dialog.class('price');
  },

  priceChangedResponse: function (response, fieldName, priceCell) {
    if (response.success) {
      var value = response.fields[fieldName].displayed;
      priceCell.innerHTML = value;
      rapidEdit.styleCell(priceCell);
    }
  },

  toggleClosedRequest: function (closedCell) {
    var isClosed = closedCell.textContent.trim() !== '';
    var id = closedCell.parentNode.dataset.id;
    var updateClosedCell = function (response) {
      if (response.success) {
        closedCell.innerHTML = response.fields.closed.displayed;
        rapidEdit.styleCell(closedCell);
      }
    };
    if (isClosed) {
      var text = t('Do you really want to reopen request %s?', id);
      dialog.confirm(text, function () {
        rapidEdit.changeField('request', id, 'closed', '', updateClosedCell);
      });
      dialog.class('close-request');
    } else {
      var text = t('Do you really want to close request %s?', id);
      var date = new Date().toJSON().slice(0, 10);
      dialog.confirm(text, function () {
        rapidEdit.changeField('request', id, 'closed', date, updateClosedCell);
      });
      dialog.class('close-request');
    }
  },

  changeField: function (className, recordID, fieldName, fieldValue, callback) {
    var postValues = {
      action: 'edit',
      class: className,
      id: recordID
    };
    postValues[fieldName] = fieldValue;
    ajax.run(postValues, callback);
  },
  
  applySpecialCategory: function(select, className, recordID) {
    window.temp_select = select;
    var postValues = {
      action: 'apply-special-category',
      class: className,
      id: recordID,
      category: select.value
    };
    ajax.run(postValues, function(response) {
      if(!response.success) {
        return;
      }
      var tr = parentMatchingQuery(select, 'tr');
      var cat_cell = tr.querySelector('.field-category');
      cat_cell.innerHTML = '<a class="button" href="' + app.data.publicBase + '/category/' 
              + response.category.id + '/edit">'
              + response.category.name + '</a>';
    });
  }

};

onLoad(rapidEdit.init);
  