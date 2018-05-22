
  var search = {
    form: document.querySelector('.search-form'),
    
    init: function() {
      search.bindToggle();
      search.bindTooltips();
    },
    
    bindToggle: function() {
      var searchToggle = document.getElementById('search-form-toggle');
      if(searchToggle) {
        searchToggle.addEventListener('click', function() {
          if(search.form.style.display !== 'block') {
            search.form.style.display = 'block';
          }
          else {
            search.form.style.display = 'none';
          }
        });
      }
    },

    bindTooltips: function() {
      var operatorsMarker = document.querySelectorAll('.operators-tooltip');
      var operatorsTooltip = document.getElementById('operators-tooltip');
      if(operatorsTooltip) {
        forEach(operatorsMarker, function(marker) {
          marker.addEventListener('mouseenter', function() {
            operatorsTooltip.style.display = 'block';
          });
          marker.addEventListener('mousemove', function(ev) {
            var x = ev.clientX;
            var y = ev.clientY;
            operatorsTooltip.style.top = (y + 20) + 'px';
            operatorsTooltip.style.left = (x + 20) + 'px';
          });
          marker.addEventListener('mouseleave', function() {
            operatorsTooltip.style.display = 'none';
          });
        });
      }
    }

  };
  
  onLoad(search.init);
  