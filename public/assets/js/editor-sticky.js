/* Lightweight sticky helper using IntersectionObserver.
   Usage: add attribute `data-sticky` or class `sticky-target` to element.
   Optional: set `data-sticky-offset="20"` for top offset in px.
*/
(function(){
  'use strict';

  function createSentinel(el) {
    var s = document.createElement('div');
    s.className = 'sticky-sentinel';
    s.style.position = 'absolute';
    s.style.height = '1px';
    s.style.width = '1px';
    s.style.top = '0';
    s.style.left = '0';
    return s;
  }

  function init() {
    if (typeof IntersectionObserver === 'undefined') return;
    var els = Array.from(document.querySelectorAll('[data-sticky], .sticky-target'));
    if (!els.length) return;

    els.forEach(function(el){
      // ensure the element is block-level and can be sticky
      el.classList.add('sticky-target');

      var offset = parseInt(el.dataset.stickyOffset || el.getAttribute('data-sticky-offset') || 0, 10) || 0;
      el.style.setProperty('--sticky-offset', offset + 'px');

      // Optional: limit number of lines to render when stuck. Specify as
      // `data-sticky-max-lines="N"` on the element. When stuck, the helper
      // will set a max-height equal to N * computed line-height and enable
      // scrolling within the sticky element.
      var rawMax = el.dataset.stickyMaxLines || el.getAttribute('data-sticky-max-lines');
      var maxLines = 5; // default to 5 lines when not specified
      if (rawMax !== null && rawMax !== undefined && rawMax !== '') {
        maxLines = parseInt(rawMax, 10) || 5;
      }
      var maxHeightPx = 0;
      if (maxLines > 0) {
        var cs = getComputedStyle(el);
        var lineHeight = cs.lineHeight;
        var fontSize = parseFloat(cs.fontSize) || 14;
        var lineHeightPx = 0;
        if (lineHeight && lineHeight.indexOf('px') !== -1) {
          lineHeightPx = parseFloat(lineHeight);
        } else if (lineHeight && lineHeight === 'normal') {
          lineHeightPx = fontSize * 1.2;
        } else if (lineHeight) {
          // numeric multiplier (e.g. 1.5)
          lineHeightPx = parseFloat(lineHeight) * fontSize;
        } else {
          lineHeightPx = fontSize * 1.2;
        }
        maxHeightPx = Math.ceil(lineHeightPx * maxLines);
        // store for observer callback
        el.__stickyMaxHeight = maxHeightPx;
      }

      // Insert sentinel before the element to detect when it reaches top
      var sentinel = createSentinel(el);
      el.parentNode && el.parentNode.insertBefore(sentinel, el);

      var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry){
          // When sentinel is NOT intersecting, element is stuck to top
          if (entry.boundingClientRect.top < (offset + 1) && entry.intersectionRatio === 0) {
            el.classList.add('stuck');
            // apply max height if configured
            if (el.__stickyMaxHeight) {
              el.style.maxHeight = el.__stickyMaxHeight + 'px';
            }
          } else {
            el.classList.remove('stuck');
            // remove max-height so element can expand normally
            if (el.__stickyMaxHeight) {
              el.style.maxHeight = '';
            }
          }
        });
      }, { root: null, threshold: [0,1], rootMargin: '-' + offset + 'px 0px 0px 0px' });

      observer.observe(sentinel);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
