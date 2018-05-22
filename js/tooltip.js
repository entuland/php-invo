
  var movableSupport = movableSupport || false;

  var tooltip = {
    
    init: function() {
      document.addEventListener('mouseenter', tooltip.mouseenter, true);
      document.addEventListener('mousemove', tooltip.mousemove, true);
      document.addEventListener('mouseleave', tooltip.mouseleave, true);
    },
    
    valid: function(ev) {
      return ev 
              && ev.target 
              && ev.target.dataset 
              && (ev.target.title || ev.target.dataset.tooltipTitle);
    },
    
    mouseenter: function(ev) {
      if(!tooltip.valid(ev)) {
        return;
      }
      if(ev.target.title) {
        ev.target.dataset.tooltipTitle = ev.target.title;
        ev.target.removeAttribute('title');
      }
      tooltip.kill();
      var el = document.createElement('div');
      el.textContent = ev.target.dataset.tooltipTitle;
      el.className = 'follow-tooltip';
      document.body.appendChild(el);
      tooltip.element = el;
      tooltip.update();
    },
    
    mousemove: function(ev) {
      if(!tooltip.valid(ev)) {
        return;
      }
      tooltip.update();
    },
    
    mouseleave: function(ev) {
      if(!tooltip.valid(ev)) {
        return;
      }
      tooltip.kill();
    },
    
    update: function() {
      var el = tooltip.element;
      if(!el) {
        return;
      }
      el.classList.remove('fade-out');
      el.classList.add('fade-in');
      var w = document.documentElement.clientWidth;
      var h = document.documentElement.clientHeight;
      var _w = w / 2;
      var _h = h / 2;
      var x = movableSupport.x;
      var y = movableSupport.y;
      var shift = 20;
      switch(true) {
        case x <= _w && y <= _h:
          el.style.left = (x+shift) + 'px';
          el.style.right = '';
          el.style.top = (y+shift) + 'px';
          el.style.bottom = '';
          break;
        case x > _w && y <= _h:
          el.style.left = '';
          el.style.right = (w-x+shift) + 'px';
          el.style.top = (y+shift) + 'px';
          el.style.bottom = '';
          break;
        case x > _w && y > _h:
          el.style.left = '';
          el.style.right = (w-x+shift) + 'px';
          el.style.top = '';
          el.style.bottom = (h-y+shift) + 'px';
          break;
        case x <= _w && y > _h:
          el.style.left = (x+shift) + 'px';
          el.style.right = '';
          el.style.top = '';
          el.style.bottom = (h-y+shift) + 'px';
          break;
      }
      if(tooltip.update.timeout) {
       clearTimeout(tooltip.update.timeout);
      }
      tooltip.update.timeout = setTimeout(function() {
        el.classList.remove('fade-in');
        el.classList.add('fade-out');
      }, 2000);
    },
    
    kill: function() {
      if(tooltip.element) {
        document.body.removeChild(tooltip.element);
        tooltip.element = false;
      }
    }
    
  };
  
  onLoad(tooltip.init);

