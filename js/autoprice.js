
  var app = app || false;
  var category = category || false;
  var rapidChange = rapidChange || false;
  var uniqueFilter = uniqueFilter || false;

  var autoprice = {
    elements: {},
    
    init: function() {
      var form = document.querySelector('form.new-item');
      if(form) {
        var company = {};
        if(app.data.company) {
          company.name = app.data.company.name.displayed;
          company.multiplier = app.data.company.multiplier.displayed;
        }
        autoprice.bindToParent(form, company);
      }
    },
    
    processMultipliers: function(company) {
      var multipliers = app.settings.autoPriceMultipliers;
      multipliers = multipliers.split(';');
      if(company && company.multiplier > 0) {
        multipliers.push(company.multiplier);
      }      
      var parent = autoprice.multiplier.parentNode;
      multipliers.sort().filter(uniqueFilter);
      if(multipliers.length) {
        forEach(multipliers, function(mul, index) {
          var text = 'x' + mul;
          if(company && company.multiplier === mul) {
            text += ' (' + company.name + ')';
          }
          if(index === 0 && app.settings.autoPriceDefaultFirstMultiplier) {
            autoprice.multiplier.value = mul;
          }
          autoprice.createMultiplierButton(mul, text, parent);
        });
        if(company && company.multiplier > 0) {
          autoprice.multiplier.value = company.multiplier;
        }
      }
      var categoryMultipliers = app.data.categoryMultipliers;
      var categoryInput = document.querySelector('input[name="category"]');
      var curCategory = categoryInput ? categoryInput.value : '';
      if(rapidChange
            && rapidChange.preparationResponse
            && rapidChange.preparationResponse.item
            && rapidChange.preparationResponse.item.category
          ) {
        curCategory = rapidChange.preparationResponse.item.category;
      }
      if(categoryMultipliers) {
        for(var cat in categoryMultipliers) {
          var mul = categoryMultipliers[cat];
          autoprice.createCategoryMultiplierButton(mul, cat, parent, categoryInput);
          if(curCategory === cat) {
            autoprice.multiplier.value = mul;
          }
        }
      }
      if(autoprice.multiplier.value && autoprice.selling.value) {
        var computedPurchase = autoprice.selling.value /autoprice. multiplier.value;
        autoprice.purchase.value = computedPurchase.toFixed(2);
      }
    },
    
    createCategoryMultiplierButton: function(mul, cat, parent, categoryInput) {
      var text = 'x' + mul + ' (' + cat + ')';
      var button = autoprice.createMultiplierButton(mul, text, parent);
      if(categoryInput) {
        button.addEventListener('click', function() {
          categoryInput.value = cat;
          category.parse();
        });
      }
    },
    
    createMultiplierButton: function(mul, text, parent) {
      var button = document.createElement('a');
      button.dataset.multiplier = mul;
      button.className = 'button button-multiplier';
      button.textContent = text;
      parent.appendChild(button);
      button.addEventListener('click', function() {
        autoprice.multiplier.value = button.dataset.multiplier;
        autoprice.updateSellingPrice();
      });
      return button;
    },
    
    bindToParent: function(parent, company) {
      autoprice.selling = parent.querySelector('input[name=price]');
      autoprice.purchase = parent.querySelector('input[name=purchase]');
      autoprice.multiplier = parent.querySelector('input[name=multiplier]');
      if(!autoprice.selling
          || !autoprice.purchase      
          || !autoprice.multiplier      
        ) {
        return;
      }
      autoprice.processMultipliers(company);
      autoprice.bindElements();
    },
    
    bindElements: function() {
      autoprice.purchase.addEventListener('change', autoprice.updateSellingPrice);
      autoprice.multiplier.addEventListener('change', autoprice.updateSellingPrice);
    },
    
    updateSellingPrice: function() {
      var purchase = autoprice.purchase.value;
      var multiplier = autoprice.multiplier.value;
      if(!purchase || !multiplier) {
        return;
      }
      var selling = purchase * multiplier;
      var rounding = app.settings.autoPriceRounding;
      autoprice.selling.value = moneyRound(selling, rounding);
    }
    
  };
    
  onLoad(autoprice.init);

