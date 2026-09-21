(function () {
  'use strict';

  if (!window.tinymce) return;

  var csrfMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = csrfMeta ? csrfMeta.content : '';
  var base = document.body.getAttribute('data-sc-base') || '/sc';

  function uploadHandler(blobInfo, progress) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      var form = new FormData();
      form.append('csrf_token', csrf);
      form.append('mode', 'json');
      form.append('file', blobInfo.blob(), blobInfo.filename());
      xhr.open('POST', base + '/filemanager.php');
      xhr.withCredentials = true;
      xhr.upload.onprogress = function (event) {
        if (event.lengthComputable && progress) progress(event.loaded / event.total * 100);
      };
      xhr.onload = function () {
        try {
          var response = JSON.parse(xhr.responseText || '{}');
          if (xhr.status === 200 && response.location) {
            resolve(response.location);
          } else {
            reject(response.error || 'Upload nije uspio.');
          }
        } catch (error) {
          reject('Neispravan odgovor servera.');
        }
      };
      xhr.onerror = function () { reject('Mrežna pogreška.'); };
      xhr.send(form);
    });
  }

  tinymce.init({
    selector: '#sc-footer-editor',
    height: 360,
    menubar: 'edit insert format table view',
    plugins: 'lists link image table code autoresize advlist searchreplace fullscreen',
    toolbar: 'undo redo | blocks | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image table | code fullscreen',
    relative_urls: false,
    convert_urls: false,
    automatic_uploads: true,
    images_upload_handler: uploadHandler,
    file_picker_types: 'image',
    file_picker_callback: function (callback) {
      var picker = window.open(base + '/filemanager.php?picker=1', 'scTinyFileManager', 'width=1100,height=760,resizable=yes,scrollbars=yes');
      function receive(event) {
        if (event.origin !== window.location.origin || !event.data || event.data.type !== 'sc-media-select') return;
        callback(event.data.url, {alt: ''});
        window.removeEventListener('message', receive);
        if (picker && !picker.closed) picker.close();
      }
      window.addEventListener('message', receive);
    },
    content_style: 'body{font-family:Arial,sans-serif;font-size:16px;line-height:1.6}img{max-width:100%;height:auto}',
    setup: function (editor) {
      editor.on('change keyup undo redo', function () { editor.save(); });
    }
  });

  var form = document.getElementById('sc-catalog-form');
  if (form) {
    form.addEventListener('submit', function () {
      tinymce.triggerSave();
    });
  }
})();
