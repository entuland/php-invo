
  var msg = {
    staticContainer: {},
    tempContainer: {},
    
    init: function() {
      msg.staticContainer = document.getElementById('static-js-messages');
      msg.tempContainer = document.getElementById('temp-js-messages');
      msg.tempContainer.title = t('Click to hide');
      msg.tempContainer.addEventListener('click', function(ev) {
        console.log(ev.target);
        msg.tempContainer.style.display = 'none';
      });
    },
    
    blink: function() {
      blink(msg.staticContainer);
      blink(msg.tempContainer);
      forEach(document.querySelectorAll('.static-js-message'), blink);
    },
    
    noMessages: function() {
      return document.querySelectorAll('.static-js-message').length === 0;
    },

    static: function(markup, parent, divClass, append) {
      var div = document.createElement('div');
      div.classList.add('message');
      div.classList.add('static-js-message');
      if(divClass) {
        div.classList.add(divClass);
      }
      div.innerHTML = markup;
      if(parent) {
        if(parent.children.length && !append) {
          parent.insertBefore(div, parent.firstChild);
        }
        else {
          parent.appendChild(div);
        }
      }
      else {
        msg.staticContainer.appendChild(div);
      }
    },
    
    staticNormal: function(markup, parent, append) {
      msg.static(markup, parent, 'normal', append);
    },
    
    staticError: function(markup, parent, append) {
      msg.static(markup, parent, 'error', append);
    },
    
    staticWarning: function(markup, parent, append) {
      msg.static(markup, parent, 'warning', append);
    },
    
    clear: function() {
      var divs = document.querySelectorAll('.static-js-message');
      forEach(divs, function(div) {
        div.parentNode.removeChild(div);
      });
    },
    
    temp: function(markup) {
      msg.tempContainer.innerHTML = markup;
      msg.tempContainer.style.display = 'block';
    }
    
  };
  
  onLoad(msg.init);
  