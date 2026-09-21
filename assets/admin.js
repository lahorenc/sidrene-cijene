(function () {
  'use strict';

  var categories = document.getElementById('sc-categories');
  var categoryTemplate = document.getElementById('sc-category-template');
  var itemTemplate = document.getElementById('sc-item-template');
  var iconTemplate = document.getElementById('sc-icon-template');
  var tabs = document.querySelector('[data-tabs]');
  var embedBuilder = document.querySelector('[data-embed-builder]');

  function updateEmbedCode() {
    if (!embedBuilder) return;
    var url = new URL(embedBuilder.getAttribute('data-base-url'), window.location.origin);
    var categories = [];
    embedBuilder.querySelectorAll('[data-embed-category]:checked').forEach(function (checkbox) {
      categories.push(checkbox.value);
    });
    if (categories.length) {
      url.searchParams.set('categories', categories.join(','));
    } else {
      url.searchParams.delete('categories');
    }
    var urlField = embedBuilder.querySelector('[data-embed-url]');
    var codeField = embedBuilder.querySelector('[data-embed-code]');
    if (urlField) urlField.value = url.toString();
    if (codeField) {
      var style = embedBuilder.getAttribute('data-embed-style') || 'width:100%;min-height:650px;border:0;display:block;margin:0 auto';
      var parentCss = embedBuilder.getAttribute('data-embed-parent-css') || '';
      codeField.value = (parentCss ? '<style>' + parentCss + '</style>\n' : '')
        + '<div class="sc-cjenik-embed"><iframe id="sc-cjenik" src="' + url.toString() + '" title="Cjenik"'
        + ' loading="lazy" style="' + style + '"></iframe></div>\n'
        + '<script>window.addEventListener("message",function(e){var f=document.getElementById("sc-cjenik");'
        + 'if(f&&e.source===f.contentWindow&&e.data&&e.data.type==="sc-cjenik-height"){'
        + 'f.style.height=Math.max(300,e.data.height)+"px";}});</script>';
    }
  }

  function activateTab(name, updateUrl) {
    if (!tabs) return;
    var allowed = ['cjenik', 'postavke', 'izvozi', 'ugradnja', 'program', 'racun'];
    if (allowed.indexOf(name) === -1) name = 'postavke';
    document.querySelectorAll('[data-tab-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-tab-panel') !== name;
    });
    tabs.querySelectorAll('[data-tab]').forEach(function (button) {
      var active = button.getAttribute('data-tab') === name;
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-selected', active ? 'true' : 'false');
    });
    document.querySelectorAll('[data-return-tab]').forEach(function (field) {
      field.value = name;
    });
    var savebar = document.querySelector('[data-savebar]');
    if (savebar) savebar.hidden = name !== 'cjenik' && name !== 'postavke';
    if (updateUrl && window.history && window.history.replaceState) {
      var url = new URL(window.location.href);
      url.searchParams.set('tab', name);
      window.history.replaceState({}, '', url.toString());
    }
    window.setTimeout(function () {
      if (window.tinymce && tinymce.editors) {
        tinymce.editors.forEach(function (editor) {
          try { editor.dispatch('ResizeEditor'); } catch (error) {}
        });
      }
    }, 0);
  }

  function reindex() {
    if (categories) {
      categories.querySelectorAll('[data-category]').forEach(function (category, categoryIndex) {
        category.querySelectorAll('input, textarea, select').forEach(function (field) {
          if (field.name) {
            field.name = field.name.replace(/categories\[\d+\]/, 'categories[' + categoryIndex + ']');
          }
        });
        category.querySelectorAll('[data-item]').forEach(function (item, itemIndex) {
          item.querySelectorAll('input, textarea, select').forEach(function (field) {
            if (field.name) {
              field.name = field.name.replace(/\[items\]\[\d+\]/, '[items][' + itemIndex + ']');
            }
          });
        });
      });
    }
    document.querySelectorAll('[data-payment-icon]').forEach(function (row, index) {
      row.querySelectorAll('input').forEach(function (field) {
        field.name = field.name.replace(/payment_icons\[\d+\]/, 'payment_icons[' + index + ']');
      });
    });
  }

  function addItem(category) {
    if (!itemTemplate) return;
    category.querySelector('[data-items]').insertAdjacentHTML('beforeend', itemTemplate.innerHTML);
    reindex();
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('button');
    if (!button) return;

    if (button.matches('[data-tab]')) {
      activateTab(button.getAttribute('data-tab'), true);
    } else if (button.matches('[data-add-category]') && categories && categoryTemplate) {
      categories.insertAdjacentHTML('beforeend', categoryTemplate.innerHTML);
      reindex();
    } else if (button.matches('[data-remove-category]')) {
      if (window.confirm('Ukloniti kategoriju i sve njezine stavke?')) {
        button.closest('[data-category]').remove();
        reindex();
      }
    } else if (button.matches('[data-add-item]')) {
      addItem(button.closest('[data-category]'));
    } else if (button.matches('[data-remove-item]')) {
      button.closest('[data-item]').remove();
      reindex();
    } else if (button.matches('[data-add-icon]') && iconTemplate) {
      document.querySelector('[data-payment-icons]').insertAdjacentHTML('beforeend', iconTemplate.innerHTML);
      reindex();
    } else if (button.matches('[data-remove-icon]')) {
      button.closest('[data-payment-icon]').remove();
      reindex();
    }
  });

  document.addEventListener('change', function (event) {
    if (event.target.matches('[data-embed-category]')) {
      updateEmbedCode();
    }
  });

  var paletteSelect = document.querySelector('[data-palette-select]');
  var paletteJson = {};
  if (paletteSelect) {
    try { paletteJson = JSON.parse(paletteSelect.getAttribute('data-palettes') || '{}'); } catch (error) { paletteJson = {}; }
  }

  function applyPalette(id, markCustom) {
    var colors = paletteJson[id];
    if (!colors) return;
    Object.keys(colors).forEach(function (key) {
      var field = document.querySelector('input[name="theme[' + key + ']"]');
      if (field) field.value = colors[key];
    });
    if (paletteSelect && !markCustom) {
      paletteSelect.value = id;
    }
    document.querySelectorAll('[data-apply-palette]').forEach(function (button) {
      button.classList.toggle('is-active', button.getAttribute('data-apply-palette') === id);
    });
    if (accent) lastAccent = accent.value;
  }

  if (paletteSelect) {
    paletteSelect.addEventListener('change', function () {
      if (paletteSelect.value !== 'custom') {
        applyPalette(paletteSelect.value);
      } else {
        document.querySelectorAll('[data-apply-palette]').forEach(function (button) {
          button.classList.remove('is-active');
        });
      }
    });
  }

  document.addEventListener('click', function (event) {
    var swatch = event.target.closest('[data-apply-palette]');
    if (!swatch) return;
    event.preventDefault();
    applyPalette(swatch.getAttribute('data-apply-palette'));
  });

  var colorFields = document.querySelector('[data-color-fields]');
  if (colorFields && paletteSelect) {
    colorFields.addEventListener('input', function (event) {
      if (event.target && event.target.type === 'color') {
        paletteSelect.value = 'custom';
        document.querySelectorAll('[data-apply-palette]').forEach(function (button) {
          button.classList.remove('is-active');
        });
      }
    });
  }

  var accent = document.querySelector('[data-accent-master]');
  var lastAccent = accent ? accent.value : '';
  if (accent) {
    var linked = [
      document.querySelector('input[name="theme[title_color]"]'),
      document.querySelector('input[name="theme[category_bg]"]'),
      document.querySelector('input[name="theme[price_color]"]')
    ];
    accent.addEventListener('input', function () {
      linked.forEach(function (field) {
        if (field && field.value === lastAccent) {
          field.value = accent.value;
        }
      });
      lastAccent = accent.value;
    });
  }

  reindex();
  updateEmbedCode();
  if (tabs) activateTab(tabs.getAttribute('data-active-tab') || 'postavke', false);
})();
