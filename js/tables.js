  var cover = cover || false;

  var tables = {
    init: function() {
      tables.processAllTables();
    },
    
    processAllTables: function() {
      tables.processTablesOfParents(document.querySelectorAll('table'));
    },
        
    processTablesOfParents: function(parents) {
      forEach(parents, tables.processTablesOf);
    },
    
    processTablesOf: function(parent) {
      if(parentMatchingQuery(parent, '#settings')) {
        return;
      }
      tables.processZebrasOfParent(parent);
      tables.processSortingOfParent(parent);
    },
    
    processZebrasOfParent: function(parent) {
      var cells = parent.querySelectorAll('tr:nth-child(odd) td');
      forEach(cells, function(cell) {
        tables.applyZebra(cell);
      });
    },

    processSortingOfParent: function(parent) {
      var sortables = parent.querySelectorAll('th[data-sortable="1"]');
      var rows = parent.querySelectorAll('tr');
      if(rows.length < 3) {
        return;
      }
      var curColumn = getQueryVariable('orderby');
      var curDirection = getQueryVariable('direction');
      var curStart = getQueryVariable('start');
      var curCount = getQueryVariable('count');
      curDirection = curDirection !== 'asc' ? 'desc' : 'asc';
      forEach(sortables, function(sortable) {
        sortable.classList.add('sortable-header');
        var column = sortable.dataset.column;
        var isDefault = column === 'id' && !curColumn;
        var isCurrent = curColumn === column;
        if(isDefault || isCurrent) {
          sortable.classList.add('current-sorting');
          sortable.classList.add('direction-' + curDirection);
        }
        if(sortable.clickSortHandler) {
          sortable.removeEventListener('click', sortable.clickSortHandler);
        }
        var newDirection = 
          sortable.classList.contains('direction-asc')
          ? 'desc'
          : 'asc';
        var chunks = [
          curStart ? 'start='+curStart : '',   
          curCount ? 'count='+curCount : '',   
          'orderby=' + column,
          'direction=' + newDirection
        ];
        chunks = chunks.filter(function(chunk) {
          return chunk !== '';
        });
        var order = newDirection === 'asc' ? t('ascending') : t('descending');
        sortable.title = t('Click to sort by this column in %s order', order);
        var newLocation = chunks.join('&');
        var sortHandler = function() {
          cover.show();
          window.location = '?' + newLocation;
        };
        sortable.addEventListener('click', sortHandler);
      });
    },
    
    darkenOrLighten: function(rgb) {
      var hsv = rgbToHsv(rgb.r, rgb.g, rgb.b);
      var variation = 0.05;
      hsv.v = hsv.v > 0.5 ? hsv.v - variation : hsv.v + variation;
      rgb = hsvToRgb(hsv.h, hsv.s, hsv.v);
      return rgbToHex(rgb.r, rgb.g, rgb.b);
    },
    
    applyZebra: function(element) {
      element.style.backgroundColor = '';
      element.style.color = '';
      var computed = window.getComputedStyle(element);
      var background = computed.getPropertyValue('background-color');
      var color = computed.getPropertyValue('color');
      element.style.backgroundColor = tables.darkenOrLighten(parseRGB(background));
      element.style.color = tables.darkenOrLighten(parseRGB(color));
    },
    
    updateZebra: function(cell) {
      if(cell && cell.parentNode && cell.parentNode.matches('tr:nth-child(odd)')) {
        tables.applyZebra(cell);
      }
    }
  };
  
  onLoad(tables.init);
  