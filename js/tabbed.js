
  var tabbed = {
    tabbables: [],
    
    init: function() {
      tabbed.tabbables = document.querySelectorAll('[data-tabbed]');
      forEach(tabbed.tabbables, tabbed.initTabbable);
    },
            
    initTabbable: function(tabbable) {
      var containerSelector = tabbable.dataset.tabContainers;
      var titleSelector = tabbable.dataset.tabTitles;
      if(!containerSelector || !titleSelector) {
        return;
      }
      var containers = tabbable.querySelectorAll(containerSelector);
      if(!containers) {
        return;
      }
      tabbable.tabs = [];
      forEach(containers, function(container) {
        tabbed.initContainer(container, titleSelector, tabbable);
      });
      tabbed.buildTabs(tabbable);
    },
    
    initContainer: function(container, titleSelector, tabbable) {
      var tab = {};
      var titleElement = container.querySelector(titleSelector);
      if(!titleElement) {
        return;
      }
      tab.title = titleElement.textContent;
      tab.container = container;
      tab.tabbable = tabbable;
      tabbable.tabs.push(tab);
    },
    
    buildTabs: function(tabbable) {
      var tabContainer = document.createElement('div');
      tabbable.insertBefore(tabContainer, tabbable.firstChild);
      tabContainer.className = 'tabbed-tabs';
      tabbable.tabContainer = tabContainer;
      forEach(tabbable.tabs, function(tab) {
        tabbed.buildTab(tab);
      });
      if(tabbable.tabs.length) {
        tabbed.openTab(tabbable.tabs[0]);
      }
    },
    
    buildTab: function(tab) {
      tab.container.classList.add('tabbed-container');
      tab.button = document.createElement('a');
      tab.button.className = 'button tab-button';
      tab.button.href = 'javascript:';
      tab.button.textContent = tab.title;
      tab.tabbable.tabContainer.appendChild(tab.button);
      tab.button.addEventListener('click', function() {
        tabbed.openTab(tab);
      });
    },
    
    openTab: function(tab) {
      tabbed.hideTabContainers(tab.tabbable);
      tab.button.classList.add('active');
      tab.container.style.display = '';
      tab.button.focus();
    },
    
    hideTabContainers: function(tabbable) {
      forEach(tabbable.tabs, function(tab) {
        tab.button.classList.remove('active');
        tab.container.style.display = 'none';
      });
    }

};
  
  onLoad(tabbed.init);