
  var collapsible = {
    init: function() {
      collapsible.bind(document);
    },
    
    bind: function(parent) {
      var boxes = parent.querySelectorAll('.collapsible');
      forEach(boxes, collapsible.bindChild);
    },
    
    bindChild: function(box) {
      var content = box.querySelector('.collapsible-content');
      if(anyParentHasClass(box, 'collapsible')) {
        content.classList.remove('collapsed');
        return;
      }
      if(!content) {
        return;
      }
      var button = document.createElement('a');
      var title = box.dataset.title;
      button.className = 'button collapsible-toggle';
      var updateButton = function() {
        if(content.classList.contains('collapsed')) {
          button.innerHTML = t('Open') + ' ' + title;
          if(pager) {
            pager.style.display = 'none';
          }
        }
        else {
          button.innerHTML = t('Close') + ' ' + title;
          if(pager) {
            pager.style.display = 'block';
          }
        }
      };
      var pager = false;
      if(parseInt(box.dataset.affectPager)) {
        pager = document.querySelector('.pager');
      }
      var toggleVisibility = function() {
        if(content.classList.contains('collapsed')) {
          content.classList.remove('collapsed');
        }
        else {
          content.classList.add('collapsed');
        }
        updateButton();
      };
      var isSearch = box.querySelector('.search-form');
      var keepOpen = getQueryVariable('count') || getQueryVariable('orderby');
      if(keepOpen && !isSearch) {
        content.classList.remove('collapsed');
      }
      updateButton();
      box.insertBefore(button, content);
      button.addEventListener('click', toggleVisibility);
    }
  };
  
  onLoad(collapsible.init);
  