
  var cover = cover || false;

  var pager = {
    init: function() {
      pager.curpageElement = document.querySelector('.pager .curpage');
      
      if(!pager.curpageElement) {
        return;
      }
      
      pager.container = document.querySelector('.pager');

      pager.sorting = {};
      pager.sorting.orderby = getQueryVariable('orderby');
      pager.sorting.direction = getQueryVariable('direction');
      pager.enrichButtons();

      pager.start = parseInt(pager.curpageElement.dataset.start);
      pager.count = parseInt(pager.curpageElement.dataset.count);
      pager.page = parseInt(pager.curpageElement.dataset.page);
      pager.pages = parseInt(pager.curpageElement.dataset.pages);
      pager.enrichCurrentPage();
      
    },
    
    enrichCurrentPage: function() {
      pager.curpageElement.innerHTML = '';
      
      var locationTrail = '';
      if(pager.sorting.orderby) {
        locationTrail += '&orderby=' + pager.sorting.orderby;
      }
      if(pager.sorting.direction) {
        locationTrail += '&direction=' + pager.sorting.direction;
      }
      
      var select = document.createElement('select');
      for(var i = 1; i <= pager.pages; ++i) {
        var option = document.createElement('option');
        option.value = i;
        option.textContent = t('Page %d', i) + '/' + pager.pages;
        if(i === pager.page) {
          option.selected = true;
        }
        select.appendChild(option);
      }
      pager.curpageElement.appendChild(select);
            select.addEventListener('change', function() {
        var start = (select.value - 1) * pager.count;
        var newLocation = '?start=' + start + '&count=' + pager.count;
        cover.show();
        window.location = newLocation + locationTrail;
      });

      var countSelect = document.createElement('select');
      var pageCounts = [
        10, 25, 50, 100, 500, pager.count
      ];
      pageCounts = pageCounts.sort(compareNumeric).filter(uniqueFilter);
      forEach(pageCounts, function(count) {
        var option = document.createElement('option');
        option.value = count;
        option.textContent = t('%s/page', count);
        if(count === pager.count) {
          option.selected = true;
        }
        countSelect.appendChild(option);
      });
      pager.curpageElement.appendChild(countSelect);
      
      countSelect.addEventListener('change', function() {
        var start = pager.start;
        var newLocation = '?start=' + start + '&count=' + countSelect.value;
        cover.show();
        window.location = newLocation + locationTrail;
      });      
    },
    
    enrichButtons: function() {
      var buttons = document.querySelectorAll('.pager .button');
      forEach(buttons, function(button) {
        var href = button.href;
        if(button.dataset.originalHref) {
          href = button.dataset.originalHref;
        } else {
          button.dataset.originalHref = href;
        }
        if(pager.sorting.orderby) {
           href += '&orderby=' + pager.sorting.orderby;
        }
        if(pager.sorting.direction) {
           href += '&direction=' + pager.sorting.direction;
        }
        button.href = href;
      });
    }

  };
  
  onLoad(pager.init);


