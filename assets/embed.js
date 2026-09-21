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

  function hostLayout() {
    return document.documentElement.getAttribute('data-sc-host-layout') || 'default';
  }

  function parentLayoutCss(width, layout) {
    var base =
      '.sc-cjenik-embed{display:block;width:100%;max-width:' + width + ';margin:0 auto 24px;}' +
      '#sc-cjenik,.sc-cjenik-embed iframe{display:block;width:100%;max-width:100%;margin:0 auto;border:0;}';
    if (layout !== 'shape5') {
      return base;
    }
    return (
      'body:has(#sc-cjenik) #s5_right_column_wrap,body:has(#sc-cjenik) #s5_right_wrap,' +
      'body:has(.sc-cjenik-embed) #s5_right_column_wrap,body:has(.sc-cjenik-embed) #s5_right_wrap' +
      '{display:none!important;width:0!important;}' +
      'body:has(#sc-cjenik) #s5_center_column_wrap_inner,' +
      'body:has(.sc-cjenik-embed) #s5_center_column_wrap_inner{margin-right:0!important;width:100%!important;}' +
      'body:has(#sc-cjenik) #s5_component_wrap,body:has(#sc-cjenik) #s5_component_wrap_inner,' +
      'body:has(.sc-cjenik-embed) #s5_component_wrap,body:has(.sc-cjenik-embed) #s5_component_wrap_inner' +
      '{width:100%!important;}' +
      'body:has(#sc-cjenik) .item-page,body:has(.sc-cjenik-embed) .item-page' +
      '{width:100%;max-width:' + width + ';margin-left:auto;margin-right:auto;}' +
      'body:has(#sc-cjenik) .item-page>h2,body:has(#sc-cjenik) .item-page .page-header,' +
      'body:has(.sc-cjenik-embed) .item-page>h2,body:has(.sc-cjenik-embed) .item-page .page-header' +
      '{text-align:center;}' +
      base
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
      style.textContent = parentLayoutCss(width, hostLayout());
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
