
  var dialog = dialog || false;
  var msg = msg || false;
  var ajax = ajax || false;
  var cover = cover || false;

  var gallery = {

    init: function() {
      
      gallery.dataSection = document.getElementById('gallery-data');      
      
      if(!gallery.dataSection) {
        return;
      }            

      gallery.radix = gallery.dataSection.dataset.radix;
      gallery.context = gallery.dataSection.dataset.context;
      
      gallery.mainContainer = document.getElementById('gallery-container');
      gallery.savedSection = document.getElementById('gallery-saved-section');

      gallery.prepareImagePreview();
      
      
      if(gallery.context === 'edit') {
        gallery.prepareEditMode();
      }
      
      gallery.loadSavedImages();
      gallery.cleanup();      
    },
    
    prepareEditMode: function() {
      gallery.displayWidth = 320;
      gallery.displayHeight = 240;

      gallery.dropSection = document.getElementById('gallery-drop-section');
      gallery.streamingSection = document.getElementById('gallery-streaming-section');
      gallery.streamingInterface = gallery.streamingSection.querySelector('.streaming-interface');
      gallery.pendingSection = document.getElementById('gallery-pending-section');
      
      gallery.video = document.getElementById('gallery-video');
      gallery.canvas = document.getElementById('gallery-canvas');
      gallery.canvas.style.display = 'none';
      gallery.picture = document.getElementById('gallery-picture');
      
      gallery.picture.addEventListener('click', function() {
        gallery.imagePreview.src = gallery.picture.src;
        gallery.imagePreviewContainer.classList.add('open');
      });
      
      gallery.startButton = document.getElementById('gallery-start-button');
      gallery.stopButton = document.getElementById('gallery-stop-button');
      gallery.captureButton = document.getElementById('gallery-capture-button');
      gallery.saveButton = document.getElementById('gallery-save-button');
      gallery.deletePendingButton = document.getElementById('gallery-delete-pending-button');
      gallery.deleteSavedButton = document.getElementById('gallery-delete-saved-button');
      
      gallery.startButton.addEventListener('click', gallery.startButtonClick);
      gallery.stopButton.addEventListener('click', gallery.stopButtonClick);
      gallery.captureButton.addEventListener('click', gallery.captureButtonClick);
      gallery.video.addEventListener('click', gallery.captureButtonClick);
      gallery.saveButton.addEventListener('click', gallery.saveButtonClick);
      gallery.deletePendingButton.addEventListener('click', gallery.deletePendingButtonClick);
      gallery.deleteSavedButton.addEventListener('click', gallery.deleteSavedButtonClick);
      gallery.video.addEventListener('canplay', gallery.videoCanPlay);
      
      gallery.prepareDragSection();
      
    },
    
    loadSavedImages: function() {
      var items = JSON.parse(gallery.dataSection.dataset.items);
      if(items && items.length) {
        forEach(items, function(item) {
          gallery.addSavedImage(item);
          gallery.cleanup();
        });
      }
    },
        
    prepareImagePreview: function() {
      gallery.imagePreviewContainer = document.createElement('div');
      gallery.imagePreviewContainer.className = 'image-preview';
      gallery.imagePreview = document.createElement('img');
      document.body.appendChild(gallery.imagePreviewContainer);
      gallery.imagePreviewContainer.appendChild(gallery.imagePreview);
      gallery.imagePreview.addEventListener('click', function() {
        gallery.imagePreviewContainer.classList.remove('open');
      });
      gallery.imagePreview.addEventListener('load', function() {
        gallery.centerFit(gallery.imagePreview);
      });
    },
    
    hideStreamingInterface: function() {
      gallery.streamingInterface.style.display = 'none';
      gallery.startButton.style.display = 'inline-block';
    },
    
    showStreamingInterface: function() {
      gallery.streamingInterface.style.display = 'block';
      gallery.startButton.style.display = 'none';
    },
    
    startButtonClick: function() {
      var constraints = {
        audio: false,
        video: {
          optional: [
            {minWidth: 320},
            {minWidth: 640},
            {minWidth: 1024},
            {minWidth: 1280},
            {minWidth: 1920},
            {minWidth: 2560}
          ]
        }
      };
      navigator.mediaDevices.getUserMedia(constraints)
      .then(gallery.streamReady)
      .catch(function(error) {
        console.log(error);
      });
    },
        
    streamReady: function(mediaStream) {
      gallery.mediaStream = mediaStream;
      gallery.video.src = window.URL.createObjectURL(mediaStream);
    },

    videoCanPlay: function(){
      gallery.video.width = gallery.displayWidth;
      gallery.video.height = gallery.displayHeight;

      gallery.picture.width = gallery.displayWidth;
      gallery.picture.height = gallery.displayHeight;
      
      gallery.videoWidth = gallery.video.videoWidth;
      gallery.videoHeight = gallery.video.videoHeight;
      
      gallery.canvas.width = gallery.videoWidth;
      gallery.canvas.height = gallery.videoHeight;
            
      gallery.clearPicture();
      gallery.video.play();
      gallery.showStreamingInterface();      
    },
    
    captureButtonClick: function() {
      gallery.takePicture();
    },

    takePicture: function() {
      var context = gallery.canvas.getContext('2d');
      if(gallery.videoWidth && gallery.videoHeight) {
        context.drawImage(gallery.video, 0, 0, gallery.videoWidth, gallery.videoHeight);

        var data = gallery.canvas.toDataURL('image/jpeg');
        gallery.picture.src = data;
        gallery.addPendingImage(data);
        blink(gallery.picture.parentNode);
      }
      else {
        gallery.clearPicture();
      }
    },
    
    processSavedImageDeletion: function(image) {
      if(image.galleryItem) {
        gallery.deleteGalleryImage(image.galleryItem, function(response) {
          if(response.success) {
             gallery.removeImageFromSection(image);
          }
        });
      } else {
        dialog.alert(t('Missing gallery item data from image element!'));
      }
    },
    
    saveButtonClick: function() {
      var images = gallery.pendingSection.querySelectorAll('img');
      if(!images.length) {
        dialog.alert(t('No images to save'));
      }
      else {
        gallery.saveFirstImage();
      }
    },
    
    saveFirstImage: function() {
      var image = gallery.pendingSection.querySelector('img');
      if(!image) {
        cover.hide();
        dialog.alert(t('All images saved'));
        return;
      }
      var data = image.src;
      gallery.saveGalleryImage(gallery.radix, data, function(response) {
        if(response.success) {
          gallery.removeImageFromSection(image);
          gallery.addSavedImage(response.item);
          gallery.saveFirstImage();
        } else {
          cover.hide();
        }
      });
    },
    
    addSavedImage: function(item) {
      var image = gallery.prepareImage(item.src, 'saved', gallery.processSavedImageDeletion);
      image.galleryItem = item;
      gallery.cleanup();
    },
    
    addPendingImage: function(data) {
      gallery.prepareImage(data, 'pending');
      gallery.cleanup();
    },
    
    prepareImage: function(data, context, deleteCallback) {
      var section = gallery[context + 'Section'];
      
      var container = document.createElement('div');
      container.className = 'image-container';
      section.appendChild(container);
      
      var image = document.createElement('img');
      image.deleteCallback = deleteCallback;
      container.appendChild(image);
      
      image.addEventListener('load', function() {
        gallery.centerCrop(image);
        image.addEventListener('click', function() {
          gallery.imagePreview.src = data;
          gallery.imagePreviewContainer.classList.add('open');
        });        
      });

      if(gallery.context === 'edit') {
        var deleteButton = document.createElement('button');
        deleteButton.className = 'button cornered square-button delete';
        deleteButton.innerHTML = '\u2716';
        container.appendChild(deleteButton);

        var msg = t('Do you really want to discard this pending image?');
        if(context === 'saved') {
          msg = t('Do you really want to delete this saved image from server?');
        }
        deleteButton.addEventListener('click', function() {
          gallery.confirmImageDeletion(msg, image);
        });
      }
      
      image.src = data;      
      return image;
    },
    
    centerCrop: function(image) {
      var portrait = image.clientHeight > image.clientWidth;
      if(portrait) {
        image.style.width = '100%';
        image.style.height = 'auto';
      } else {
        image.style.width = 'auto';
        image.style.height = '100%';
      }
      image.style.top = (image.parentNode.clientHeight / 2 - image.clientHeight / 2) + 'px';
      image.style.left = (image.parentNode.clientWidth / 2 - image.clientWidth / 2) + 'px';
    },
    
    centerFit: function(image) {
      var ratio = image.clientHeight / image.clientWidth;
      var parentRatio = image.parentNode.clientHeight / image.parentNode.clientWidth;
      if(ratio > parentRatio) {
        image.style.width = 'auto';
        image.style.height = '100%';
      } else {
        image.style.width = '100%';
        image.style.height = 'auto';
      }
      image.style.top = (image.parentNode.offsetHeight / 2 - image.offsetHeight / 2) + 'px';
      image.style.left = (image.parentNode.offsetWidth / 2 - image.offsetWidth / 2) + 'px';
    },

    confirmImageDeletion: function(msg, image) {
      dialog.confirm(msg, function() {
        gallery.deleteImage(image);
      });
    },
    
    deleteImage: function(image) {
      if(typeof image.deleteCallback === 'function') {
        image.deleteCallback(image);
      } else {
        gallery.removeImageFromSection(image);
      }      
    },
    
    deletePendingButtonClick: function() {
      dialog.confirm(t('Do you really want to discard all pending images?'), function() {
        var images = gallery.pendingSection.querySelectorAll('img');
        forEach(images, function(image) {
          gallery.deleteImage(image);
        });
      });
    },
    
    deleteSavedButtonClick: function() {
      dialog.confirm(t('Do you really want to delete all these saved images from server?'), function() {
        var images = gallery.savedSection.querySelectorAll('img');
        forEach(images, function(image) {
          gallery.deleteImage(image);
        });
      });
    },
    
    removeImageFromSection: function(image) {
      image.parentNode.parentNode.removeChild(image.parentNode);
      gallery.cleanup();
    },
    
    stopButtonClick: function() {
      gallery.clearPicture();
      gallery.video.pause();
      gallery.hideStreamingInterface();
      var tracks = gallery.mediaStream.getTracks();
      forEach(tracks, function(track) {
        track.stop();
      });
    },

    clearPicture: function() {
      var context = gallery.canvas.getContext('2d');
      context.fillStyle = "#000";
      context.fillRect(0, 0, gallery.canvas.width, gallery.canvas.height);
      var data = gallery.canvas.toDataURL('image/png');
      gallery.picture.setAttribute('src', data);
    },
    
    prepareDragSection: function() {
      var zone = new FileDrop('gallery-drop-section');
      zone.multiple(true);
      zone.event('send', function(files) {
        files.each(function(file) {
          file.readDataURL(gallery.addPendingImage);
        });
      });
    },
    
    cleanup: function() {
      if(gallery.context === 'display') {
        if(!gallery.savedSection.querySelector('img')) {
          gallery.mainContainer.style.display = 'none';
        } else {
          gallery.mainContainer.style.display = 'block';
        }
        return;
      }
      var sections = [gallery.savedSection, gallery.pendingSection];
      forEach(sections, function(section) {
        if(!section.querySelector('img')) {
          section.style.display = 'none';
        } else {
          section.style.display = 'block';
        }
      });
      gallery.dropSection.classList.remove('dragover');
      var dropelement = gallery.dropSection.querySelector('.fd-file');
      if(dropelement.hasDragHandlers) {
        dropelement.removeEventListener('dragenter', gallery.dragenter, true);
        dropelement.removeEventListener('dragleave', gallery.dragleave, true);
      }
      dropelement.addEventListener('dragenter', gallery.dragenter, true);
      dropelement.addEventListener('dragleave', gallery.dragleave, true);
      dropelement.hasDragHandlers = true;
    },
    
    dragenter: function() {
      gallery.dropSection.classList.add('dragover');
    },
    
    dragleave: function() {
      gallery.dropSection.classList.remove('dragover');
    },
    
    saveGalleryImage: function(radix, data, callback) {
      var postValues = {
        action: 'save-gallery-image',
        radix: radix,
        data: data
      };
      ajax.run(postValues, callback);    
    },

    deleteGalleryImage: function(item, callback) {
      var postValues = {
        action: 'delete-gallery-image',
        radix: item.radix,
        index: item.index,
        ext: item.ext
      };
      ajax.run(postValues, callback);    
    },

    getGalleryItems: function(radix, callback) {
      var postValues = {
        action: 'get-gallery-items',
        radix: radix
      };
      ajax.run(postValues, callback);    
    }
  
  };

  onLoad(gallery.init);
  