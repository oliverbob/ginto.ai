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

      // Insert sentinel before the element to detect when it reaches top
      var sentinel = createSentinel(el);
      el.parentNode && el.parentNode.insertBefore(sentinel, el);

      var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry){
          // When sentinel is NOT intersecting, element is stuck to top
          if (entry.boundingClientRect.top < (offset + 1) && entry.intersectionRatio === 0) {
            el.classList.add('stuck');
          } else {
            el.classList.remove('stuck');
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
