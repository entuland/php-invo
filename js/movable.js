
  var movableSupport = {
    x: 0,
    y: 0,
    movable: false,
    initialized: false,
    
    init: function() {
      if(movableSupport.initialized) {
        return;
      }
      document.onmousemove = function(e){
        movableSupport.x = e.clientX;
        movableSupport.y = e.clientY;
      };
      
      document.addEventListener('mousemove', movableSupport.mousemove);
      document.addEventListener('mouseup', movableSupport.mouseup);
      movableSupport.initialized = true;
    },
    
    mousemove: function(ev) {
      if(movableSupport.movable) {
        var movable = movableSupport.movable;
        movable.target.style.left = (ev.screenX - movable.startX) + 'px';
        movable.target.style.top = (ev.screenY - movable.startY) + 'px';
        movable.fixPosition();
      }
    },
    
    mouseup: function() {
      movableSupport.movable = false;
    }
        
  };
  
  function movableInstance(target) {    
    this.mouse = movableSupport;
    this.target = target;
    this.skipChildren = null;
    this.startX = 0;
    this.startY = 0;
    this.curX = 0;
    this.curY = 0;
    this.oldX = 0;
    this.oldY = 0;
    this.allowOverflow = false;
    
    var instance = this;
    
    target.style.display = 'none';
    target.style.display = 'block';
    target.style.position = 'fixed';
    
    this.centerScreen = function() {
      var w = document.documentElement.clientWidth;
      var h = document.documentElement.clientHeight;
      target.style.left = (w / 2 - target.clientWidth / 2) + 'px';
      target.style.top = (h / 2 - target.clientHeight / 2) + 'px';
    };
    
    this.centerScreen();
    
    this.skipThis = function(target) {
      if(!instance.skipChildren) {
        return false;
      }
      var skip = false;
      forEach(instance.skipChildren, function(child) {
        if(skip) return;
        if(child === target) {
          skip = true;
        }
      });
      
      return skip;
    };
            
    this.mousedown = function(ev) {
      if(instance.skipThis(ev.target)) {
        return;
      }
      if(ev.button === 0) {
        instance.startX = ev.screenX - target.offsetLeft;
        instance.startY = ev.screenY - target.offsetTop;
        movableSupport.movable = instance;
      }
    };
    
    this.fixPosition = function() {
      var x = parseInt(target.style.left, 10);
      var y = parseInt(target.style.top, 10);
      
      var w = document.documentElement.clientWidth;
      var h = document.documentElement.clientHeight;
      
      if(instance.allowOverflow) {
        if(x + 100 > w) {
          x = w - 100;
        }
      }
      else {
        if(x + target.clientWidth > w) {
          x = w - target.clientWidth;
        }
      }
      if(x < 0) {
        x = 0;
      }
      if(instance.allowOverflow) {
        if(y + 100 > h) {
          y = h - 100;
        }
      }
      else {
        if(y + 20 + target.clientHeight > h) {
          y = h - target.clientHeight;
        }
      }
      if(y < 0) {
        y = 0;
      }
      instance.curX = x;
      instance.curY = y;
      target.style.left = x + 'px';
      target.style.top = y + 'px';
    };
    
    target.addEventListener('mousedown', this.mousedown);
    
  }
  
  onLoad(movableSupport.init);
