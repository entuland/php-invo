  function uniqueFilter(value, index, self) { 
    return self.indexOf(value) === index;
  }
  
  function compareNumeric(a, b) {
    return a - b;
  }
  
  function forEach(array, callback) {
    for(var i = 0; i < array.length; i++) {
      callback(array[i], i);
    }
  }
  
  function forEachRev(array, callback) {
    for(var i = array.length - 1; i >= 0; --i) {
      callback(array[i], i);
    }
  }

  function textToFloat(text) {
    return Number(('' + text).replace(',', '.').replace(/[^\d\.-]/gm, ''));
  }
  
  function lowerTrim(text) {
    return text.toLowerCase().trim();
  }
  
  function anyParentHasClass(element, className) {
    if(!element || !element.parentNode) {
      return false;
    }
    if(element.parentNode.classList 
        && element.parentNode.classList.contains(className)) {
      return true;
    }
    return anyParentHasClass(element.parentNode, className);
  }
  
  function parentMatchingQuery(element, query) {
    if(!element || !element.parentNode) {
      return null;
    }
    if(element.parentNode.matches && element.parentNode.matches(query)) {
      return element.parentNode;
    }
    return parentMatchingQuery(element.parentNode, query);
  }
  
  function isNumber(o) {
    return ! isNaN (o-0) && o !== null && o !== "" && o !== false;
  }
  
  function blink(element, times, interval) {
    if(!isNumber(times) || times <= 0) {
      times = 6;
    }
    else {
      times *= 2;
    }
    if(!isNumber(interval) || interval <= 0) {
      interval = 100;
    }
    else {
      interval /= 2;
    }
    for(var i = 0; i < times; ++i) {
      setTimeout(function() {
        invertColors(element);
      }, i * interval);
    }
    setTimeout(function() {
      element.style.color = '';
      element.style.background = '';
    }, times * interval);
  }

  function invertColors(element) {
    var computed = window.getComputedStyle(element);
    var color = computed.getPropertyValue('color');
    var background = computed.getPropertyValue('background-color');
    element.style.color = background;
    element.style.background = color;
  }

  function objectToArray(obj) {
    var array;
    for(var i in obj) {
      array.push(obj[i]);
    }
    return array;
  }

  function parseRGB(rgbstring) {
    var list = rgbstring.replace(/[^\d,]/g, '');
    var components = list.split(',');
    if(components.length < 3) {
      return null;
    }
    return {
      r: parseInt(components[0]),
      g: parseInt(components[1]),
      b: parseInt(components[2])
    };
  }
  
  function t(msgid) {
    var translation = msgid;
    if(typeof Gettext === 'function') {
      if(!t.gt) {
        t.gt = new Gettext({domain: 'app'});
      }
      translation = t.gt.gettext(msgid);
    }
    var args = Array.prototype.slice.call(arguments, 1);
    return sprintf(translation, args);
  }
  
  function onLoad(callback) {
    window.addEventListener('load', callback);
  }
  
  function getQueryVariable(variable) {
    var query = window.location.search.substring(1);
    var vars = query.split("&");
    for (var i=0; i < vars.length; ++i) {
      var pair = vars[i].split("=");
      if(pair[0] === variable) {
        return pair[1];
      }
    }
    return null;
  }
  
  function moneyRound(amount, rounding) {
    if(rounding) {
      amount *= 100;
      rounding *= 100;
      rounding = Math.abs(rounding);
      var excess = amount % rounding;
      if(excess) {
        if(excess > rounding/2) {
          amount += rounding - excess;
        } else {
          amount -= excess;
        }
      }
      amount = Math.round(amount) / 100;
    }
    return amount;
  }

