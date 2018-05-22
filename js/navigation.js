  var cover = cover || false;
  
  var navigation = {
    index: sessionStorage.getItem('currentLocationIndex'),
    action: sessionStorage.getItem('latestNavigation'),
    list: JSON.parse(sessionStorage.getItem('locations')),
    select: {},
    back: {},
    forward: {},
    
    init: function() {
      navigation.select = document.getElementById('navigation-select');
      navigation.back = document.getElementById('navigation-back');
      navigation.forward = document.getElementById('navigation-forward');
      if(!(navigation.list instanceof Array)) {
        navigation.list = [];
      } 
      if(!navigation.index) {
        navigation.index = -1;
        navigation.record();
      }
      else if(!navigation.action || navigation.action === 'none') {
        navigation.record();
      }
      sessionStorage.setItem('latestNavigation', 'none');
      navigation.prepareSelect();
      navigation.prepareButtons();
    },
    
    prepareSelect: function() {
      if(navigation.list.length) {
        var option = document.createElement('option');
        option.innerHTML = t('history');
        option.id = 'navigation-history';
        option.disabled = true;
        option.selected = true;
        navigation.select.appendChild(option);
        forEachRev(navigation.list, function(location, index) {
          var option = document.createElement('option');
          option.value = index;
          option.disabled = parseInt(index) === parseInt(navigation.index);
          var prefix = option.disabled ? '>>> ' : '';
          option.innerHTML = prefix + location.title;
          navigation.select.appendChild(option);
        });
        navigation.select.style.display = 'block';
      }
      navigation.select.addEventListener('change', function() {
        var jumpIndex = parseInt(navigation.select.value);
        var jumpLocation = navigation.list[jumpIndex];
        sessionStorage.setItem('latestNavigation', 'jump');
        sessionStorage.setItem('currentLocationIndex', jumpIndex);
        window.location = jumpLocation.path;
      });
    },
    
    prepareButtons: function() {
      navigation.back.style.display = 'none';
      navigation.prevIndex = parseInt(navigation.index) - 1;
      if(navigation.prevIndex >= 0) {
        navigation.prevPath = navigation.list[navigation.prevIndex].path;
        navigation.back.title = t('go back to') + ' ' + navigation.list[navigation.prevIndex].title; 
        navigation.back.style.display = 'inline-block';
        navigation.back.href = navigation.prevPath;
        navigation.back.addEventListener('click', function() {
          sessionStorage.setItem('latestNavigation', 'back');
          sessionStorage.setItem('currentLocationIndex', navigation.prevIndex);
        });
      }
      
      navigation.forward.style.display = 'none';
      navigation.nextIndex = parseInt(navigation.index) + 1;
      if(navigation.nextIndex < navigation.list.length) {
        navigation.nextPath = navigation.list[navigation.nextIndex].path;
        navigation.forward.title = t('go forward to') + ' ' + navigation.list[navigation.nextIndex].title; 
        navigation.forward.style.display = 'inline-block';
        navigation.forward.href = navigation.nextPath;
        navigation.forward.addEventListener('click', function() {
          sessionStorage.setItem('latestNavigation', 'forward');
          sessionStorage.setItem('currentLocationIndex', navigation.nextIndex);
        });
      }
      
    },
    
    record: function() {
      var curPath = window.location.pathname;
      var temp = false;
      if(curPath === '/list') {
        temp = true;
      }
      //navigation.list = navigation.list.slice(0, 1 + parseInt(navigation.index));
      var curLen = navigation.list.length;
      if(!curLen || curPath !== navigation.list[curLen-1].path) {
        navigation.list.push({
          path: curPath,
          title: document.title.replace(app.data.siteName + ' | ', ''),
        });
      } 
      navigation.index = navigation.list.length - 1;
      if(!temp) {
        sessionStorage.setItem('locations', JSON.stringify(navigation.list));
        sessionStorage.setItem('currentLocationIndex', navigation.index);
      }
    }
    
  };
  
  onLoad(navigation.init);
  