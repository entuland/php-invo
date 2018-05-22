
  var cover = {
    element: {},
    
    init: function() {
      cover.element = document.getElementById('working-cover');
      cover.selfBind();
      cover.bindSubmitButtons();
      cover.bindAnchors();
    },
    
    bindSubmitButtons: function() {
      if(window.location.pathname === '/inventory') {
        return;
      }
      var submits = document.querySelectorAll('[type="submit"]');
      forEach(submits, cover.bind);
    },

    bindAnchors: function(parent) {
      parent = parent || document;
      var anchors = parent.querySelectorAll('a[href]:not(.nocover)');
      forEach(anchors, cover.bind);
    },
    
    selfBind: function() {
      cover.element.addEventListener('dblclick', function() {
        cover.hide();
      });
    },
    
    hide: function() {
      this.element.style.display = 'none';
    },
    
    show: function() {
      this.element.style.display = 'block';
    },
    
    bind: function(el) {
      if(el.matches('.home .search-form button')) {
        return;
      }
      el.addEventListener('click', function(ev) {
        var form = parentMatchingQuery(ev.target, 'form');
        if(form && form.getAttribute('target') === '_blank') {
          return;
        }
        if(ev.button === 0) {
          cover.show();
        }
      });
    }
    
  };
  
  onLoad(cover.init);
