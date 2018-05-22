
  var ajax = ajax || false;
  var cover = cover || false;
  var dialog = dialog || false;
  var tables = tables || false;

  var theme = {
    name: false,
    markers: false,
    target: false,
    selectorFilter: /:focus|:active|:hover/g,
    
    init: function() {
      theme.useTheme();
      theme.inspector.container = document.getElementById('theme-inspector');
      theme.bindThemeSwitcher();
      document.addEventListener('keyup', function(ev) {
        if(ev.key === 'F7') {
          if(window.location.pathname === '/theme') {
            dialog.alert(t('Rapid theme editing is disabled in the theme manager pages'));
            return;
          }
          theme.handleInspection();
        }
      });
      document.addEventListener('mousemove', function(ev) {
        theme.target = ev.target;
      });
    },
    
    bindThemeSwitcher: function() {
      var switches = document.querySelectorAll('#themeswitcher .switch');
      forEach(switches, function(el) {
        el.addEventListener('click', theme.switchTheme);
      });
    },
    
    switchTheme: function(ev) {
      var el = ev.target;
      if(el.classList.contains('inactive')) {
        return;
      }
      var themeName = el.dataset.themeName;
      var inactives = document.querySelectorAll('#themeswitcher .inactive');
      forEach(inactives, function(inactive) {
        inactive.classList.remove('inactive');
      });
      el.classList.add('inactive');
      theme.loadTheme(themeName);
    },
    
    handleInspection: function() {
      if(!theme.target) return;
      theme.processAllMarkers();
      var markers = {}; 
      theme.getTreeMarkers(theme.target, markers);
      if(theme.target.matches('#menu select')) {
        theme.getChildrenMarkers(theme.target, markers); 
      }

      if(theme.markersMatchSub(markers, 'TOOLTIP')) {
        theme.mergeMarkers(markers, theme.getMarkersBySub('TOOLTIP'));
      }
      
      theme.inspector.show(markers);
    },
    
    markersMatchSub: function(markers, sub) {
      for(var marker in markers) {
        if(marker.indexOf(sub) !== -1) {
          return true;
        }
      }
      return false;
    },
    
    mergeMarkers: function(markers1, markers2) {
      for(var marker in markers2) {
        markers1[marker] = markers2[marker];
      }
    },
    
    getMarkersBySub: function(sub) {
      var result = {};
      for(var marker in theme.markers) {
        if(marker.indexOf(sub) !== -1) {
          result[marker] = theme.markers[marker];
        }
      }
      return result;
    },
    
    inspector: {
      container: false,
      visible: false,
      markers: false,
      movable: false,
      
      prepare: function() {
        var container = theme.inspector.container;
        if(!theme.inspector.movable) {
          theme.inspector.movable = new movableInstance(theme.inspector.container);
          theme.inspector.movable.allowOverflow = true;
        }
        container.innerHTML = '<div>' + t('Theme rapid edit') + ' - ' + theme.name + '</div>';
        container.style.display = 'block';
        theme.inspector.visible = true;
      },
      
      show: function(markers) {
        theme.inspector.markers = markers;
        var forceDisplay = true;
        theme.inspector.reload(forceDisplay);
      },
      
      reload: function(forceDisplay) {
        var inspector = theme.inspector;
        if(!inspector.container) {
          return;
        }
        if(!inspector.visible && !forceDisplay) {
          return;
        }
        var markers = inspector.markers;
        inspector.prepare();
        var tableContainer = document.createElement('div');
        tableContainer.className = 'markers';
        inspector.container.appendChild(tableContainer);
        
        var table = document.createElement('table');
        table.className = 'theme';
        tableContainer.appendChild(table);
        for(var marker in markers) {
          inspector.addMarker(marker, table);
        };
        
        if(tableContainer.clientHeight > 300) {
          tableContainer.classList.add('overflow');
        }
        
        var done = document.createElement('a');
        done.className = 'button';
        done.innerHTML = t('done');
        inspector.container.appendChild(done);
        done.addEventListener('click', inspector.cleanup);

        var reset = document.createElement('a');
        reset.className = 'button';
        reset.innerHTML = t('reset cache');
        inspector.container.appendChild(reset);
        reset.addEventListener('click', theme.resetCssCache);

        var close = document.createElement('a');
        close.className = 'button cornered square-button';
        close.innerHTML = '\u2716';
        inspector.container.appendChild(close);
        close.addEventListener('click', inspector.cleanup);
        
        inspector.movable.skipChildren = inspector.container.querySelectorAll('input, a');
        inspector.movable.fixPosition();
      },
      
      addMarker: function(marker, table) {
        var settings = theme.markers[marker];
        var type = settings.type;
        var input = document.createElement('input');
        input.type = type;
        input.dataset.themeName = theme.name;
        if(type === 'number') {
          input.step = '0.01';
          input.min = '0';
          input.max = '1';
        }
        input.name = marker;
        input.value = settings.value;
        var tr = document.createElement('tr');
        var td = document.createElement('td');
        tr.appendChild(td);
        td.appendChild(input);
        var text = document.createTextNode(' ' + settings.description);
        td.appendChild(text);
        table.appendChild(tr);
        var timeoutID = null;
        input.addEventListener('change', function(ev) {
          if(timeoutID) {
            cancelTimeout(timeoutID);
          }
          timeoutID = setTimeout(function() {
            theme.inspector.markerInputChanged(ev);
          }, 250);
        });
      },
      
      markerInputChanged: function(ev) {
        var input = ev.target;
        var themeName = input.dataset.themeName;
        var marker = input.name;
        var value = input.value;
        theme.setThemeMarker(themeName, marker, value, function() {
          theme.loadTheme(themeName);
        });
      },
      
      cleanup: function() {
        theme.inspector.visible = false;
        theme.inspector.container.style.display = 'none';
        theme.inspector.container.innerHTML = '';
      }
      
    },
    
    debugMarkers: function(markers) {
      var msg = '';
      for(var m in markers) {
        msg += m + '\n';
      }
      console.log(msg);
    },
    
    getChildrenMarkers: function(parent, markers) {
      forEach(parent.children, function(child) {
        if(child.themeMarkers) {
          for(var m in child.themeMarkers) {
            markers[m] = true;
          }
        }
      });
    },
    
    getTreeMarkers: function(leaf, markers) {
      if(!leaf) {
        return;
      }
      if(leaf.themeMarkers) {
        for(var m in leaf.themeMarkers) {
          markers[m] = true;
        }
      }
      theme.getTreeMarkers(leaf.parentNode, markers);
    },
    
    useTheme: function() {
      theme.name = app.data.theme.name;
      theme.markers = app.data.theme.markers;
      for(var marker in theme.markers) {
        theme.markers[marker].selector = theme.markers[marker].selector.replace(theme.selectorFilter, '');
      }
    },
    
    processAllMarkers: function() {
      if(!theme.markers) return;
      for(var marker in theme.markers) {
        theme.processMarker(marker);
      }
    },
    
    processMarker: function(marker) {
      var selector = theme.markers[marker].selector;
      var elements = document.querySelectorAll(selector);
      forEach(elements, function(element) {
        theme.markElement(element, marker);
      });
    },
    
    markElement: function(element, marker) {
      if(!element.themeMarkers) {
        element.themeMarkers = {};
      }
      element.themeMarkers[marker] = true;
    },
    
    loadTheme: function(themeName) {
      theme.ensureTheme(themeName, function(response) {
        if(response.success) {
          var cssName = response.cssName;
          theme.getCurrentTheme(function(response) {
            theme.useTheme(response);
            setTimeout(function() {
              theme.inspector.reload();
              var link = document.querySelector('link[title="theme-css"]');
              if(link) {
                link.href = '/' + cssName;
                setTimeout(tables.processAllTables, 100);
              }
            }, 100);
          });
        }
      });
    },
    
    ensureTheme: function(themeName, callback) {
      var postValues = {
        action: 'ensure-theme',
        'theme-name': themeName
      };
      ajax.run(postValues, callback);
    },

    getCurrentTheme: function(callback) {
      var postValues = {
        action: 'get-current-theme'
      };
      ajax.run(postValues, callback);
    },

    resetCssCache: function() {
      var postValues = {
        action: 'reset-css-cache'
      };
      ajax.run(postValues, function(response) {
        if(response.themeName) {
          theme.loadTheme(response.themeName);
        }
      });
    },

    setThemeMarker: function(themeName, marker, value, callback) {
      var postValues = {
        action: 'set-theme-marker',
        'theme-name': themeName,
        marker: marker,
        value: value
      };
      ajax.run(postValues, callback);
    }

  };
  
  onLoad(theme.init);
  