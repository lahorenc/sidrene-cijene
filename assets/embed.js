(function () {
  'use strict';

  var search = document.querySelector('[data-sc-search]');
  if (search) {
    search.addEventListener('input', function () {
      var term = search.value.toLocaleLowerCase('hr').trim();
      document.querySelectorAll('[data-sc-item]').forEach(function (row) {
        row.hidden = term !== '' && row.getAttribute('data-search').indexOf(term) === -1;
      });
      document.querySelectorAll('[data-sc-category]').forEach(function (category) {
        category.hidden = !category.querySelector('[data-sc-item]:not([hidden])');
      });
      reportHeight();
    });
  }

  function parentLayoutCss(width) {
    return (
      '.sc-cjenik-embed{display:block;width:100%;max-width:' + width + ';margin:0 auto 24px;}' +
      '#sc-cjenik,.sc-cjenik-embed iframe{display:block;width:100%;max-width:100%;margin:0 auto;border:0;}'
    );
  }

  function styleParentPage() {
    if (window.parent === window) return;
    try {
      var parentDoc = window.parent.document;
    } catch (error) {
      return;
    }
    try {
      var frame = window.frameElement || (parentDoc && parentDoc.getElementById('sc-cjenik'));
      if (!parentDoc || !frame) return;
      var wrap = frame.parentNode && frame.parentNode.classList && frame.parentNode.classList.contains('sc-cjenik-embed')
        ? frame.parentNode
        : null;
      if (!wrap && frame.parentNode) {
        wrap = parentDoc.createElement('div');
        wrap.className = 'sc-cjenik-embed';
        frame.parentNode.insertBefore(wrap, frame);
        wrap.appendChild(frame);
      }
      var width = (frame.style && frame.style.maxWidth) || '1180px';
      var style = parentDoc.getElementById('sc-cjenik-parent-style');
      if (!style) {
        style = parentDoc.createElement('style');
        style.id = 'sc-cjenik-parent-style';
        (parentDoc.head || parentDoc.documentElement).appendChild(style);
      }
      style.textContent = parentLayoutCss(width);
    } catch (error) {}
  }

  function reportHeight() {
    if (window.parent === window) return;
    window.parent.postMessage({
      type: 'sc-cjenik-height',
      height: Math.ceil(document.documentElement.scrollHeight)
    }, '*');
  }

  window.addEventListener('load', function () {
    styleParentPage();
    reportHeight();
  });
  window.addEventListener('resize', reportHeight);
  if ('ResizeObserver' in window) {
    new ResizeObserver(reportHeight).observe(document.documentElement);
  }
  styleParentPage();
})();
