/*
  Initial commented-out global functions from the original.
  If these are used by other scripts on your page, you can keep them.
  If they were only for this library, they are largely superseded by R() or native DOM methods.

  var bible = function(){
    location.href="bible/book.php?passage=Revelation 1";
  }
  if(!org) var org={};
  if(!org.facegod) org.facegod={};
  if(!org.facegod.FGBibleTagger) org.facegod.FGBibleTagger={};
  const getId = (id) => { return document.getElementById(id) } // Using const
  const getTagname = (id) => { return document.getElementsByTagName(id) } // Using const
  const querySelect = (id) => { return document.querySelector(id) } // Using const
*/

var R = (function() {
    "use strict";

    const version = '1.0 alpha Optimized';
    const slice = Array.prototype.slice;

    function isPlainObject(obj) {
        if (typeof obj !== "object" || obj === null || obj.nodeType || obj === obj.window) {
            return false;
        }
        try {
            if (obj.constructor && !Object.prototype.hasOwnProperty.call(obj.constructor.prototype, "isPrototypeOf")) {
                return false;
            }
        } catch (e) {
            return false;
        }
        for (let key in obj) {} // Test iteration (for IE host objects)
        return Object.prototype.toString.call(obj) === '[object Object]';
    }

    function extend() {
        let options, name, src, copy, copyIsArray, clone,
            target = arguments[0] || {},
            i = 1,
            length = arguments.length,
            deep = false;

        if (typeof target === "boolean") {
            deep = target;
            target = arguments[i] || {};
            i++;
        }

        if (typeof target !== "object" && typeof target !== "function") {
            target = {};
        }

        if (i === length) {
            target = this;
            i--;
        }

        for (; i < length; i++) {
            if ((options = arguments[i]) != null) {
                for (name in options) {
                    src = target[name];
                    copy = options[name];

                    if (target === copy) continue;

                    if (deep && copy && (isPlainObject(copy) || (copyIsArray = Array.isArray(copy)))) {
                        if (copyIsArray) {
                            copyIsArray = false;
                            clone = src && Array.isArray(src) ? src : [];
                        } else {
                            clone = src && isPlainObject(src) ? src : {};
                        }
                        target[name] = extend(deep, clone, copy);
                    } else if (copy !== undefined) {
                        target[name] = copy;
                    }
                }
            }
        }
        return target;
    }

    function staticEach(obj, callback) {
        let length, i = 0;
        if (Array.isArray(obj) || (typeof obj === 'object' && typeof obj.length === 'number' && obj.length >= 0)) { // Array-like
            length = obj.length;
            for (; i < length; i++) {
                if (callback.call(obj[i], i, obj[i]) === false) break;
            }
        } else { // Object
            for (i in obj) {
                if (Object.prototype.hasOwnProperty.call(obj, i)) {
                    if (callback.call(obj[i], i, obj[i]) === false) break;
                }
            }
        }
        return obj;
    }

    function staticMap(elems, callback, arg) {
        let length = elems.length, value, i = 0, ret = [];
        for (; i < length; i++) {
            value = callback(elems[i], i, arg);
            if (value != null) ret.push(value);
        }
        return [].concat.apply([], ret);
    }

    function staticGrep(elems, callback, invert) {
        let matches = [], i = 0, length = elems.length, callbackExpect = !invert;
        for (; i < length; i++) {
            if (!callback(elems[i], i) !== callbackExpect) {
                matches.push(elems[i]);
            }
        }
        return matches;
    }

    function staticMerge(first, second) {
        let len = +second.length, j = 0, i = first.length;
        for (; j < len; j++) first[i++] = second[j];
        first.length = i;
        return first;
    }

    const RInstance = function(selector, context) {
        return new RInstance.fn.init(selector, context);
    };

    RInstance.fn = RInstance.prototype = {
        constructor: RInstance,
        R: version,
        length: 0,
        selector: "",

        init: function(selector, context) {
            if (!selector) return this;

            if (selector.nodeType) { // DOMElement
                this[0] = selector; this.length = 1; return this;
            }
            if (selector instanceof RInstance) return selector; // RInstance object

            if (typeof selector === "string") {
                if (selector.charAt(0) === "<" && selector.charAt(selector.length - 1) === ">" && selector.length >= 3) {
                    let temp = (context && context.createElement) ? context.createElement('div') : document.createElement('div');
                    temp.innerHTML = selector;
                    staticMerge(this, temp.childNodes);
                } else {
                    let PUSH = (elms) => { for (let k = 0; k < elms.length; k++) this.push(elms[k]); };
                    let CTX = context && context.querySelectorAll ? context : document;
                    let results = CTX.querySelectorAll(selector);
                    if (results && results.length > 0) PUSH(results);
                    this.selector = selector;
                }
            } else if (typeof selector === 'function') { // $(document).ready()
                if (document.readyState === "complete" || (document.readyState !== "loading" && !document.documentElement.doScroll)) {
                    setTimeout(selector, 0);
                } else {
                    document.addEventListener("DOMContentLoaded", selector);
                }
            } else if (Array.isArray(selector)) { // Array of elements
                 staticMerge(this, selector);
            }
            return this;
        },

        toArray: function() { return slice.call(this); },
        get: function(num) {
            if (num == null) return slice.call(this);
            return num < 0 ? this[num + this.length] : this[num];
        },
        pushStack: function(elems) {
            let ret = staticMerge(this.constructor(), elems);
            ret.prevObject = this;
            ret.selector = this.selector;
            return ret;
        },
        each: function(callback) { return staticEach(this, callback); },
        map: function(callback) {
            return this.pushStack(staticMap(this, (elem, i) => callback.call(elem, i, elem)));
        },
        slice: function() { return this.pushStack(slice.apply(this, arguments)); },
        first: function() { return this.eq(0); },
        last: function() { return this.eq(-1); },
        eq: function(i) {
            let len = this.length, j = +i + (i < 0 ? len : 0);
            return this.pushStack(j >= 0 && j < len ? [this[j]] : []);
        },
        even: function() { return this.pushStack(staticGrep(this, (_elem, i) => (i + 1) % 2)); },
        odd: function() { return this.pushStack(staticGrep(this, (_elem, i) => i % 2)); },
        end: function() { return this.prevObject || this.constructor(); },

        find: function(selector) {
            let finalElements = [];
            this.each(function() { // `this` is DOM element
                let found = this.querySelectorAll(selector);
                if (found.length > 0) finalElements = finalElements.concat(slice.call(found));
            });
            return this.pushStack(finalElements.length > 0 ? finalElements : []);
        },
        show: function() {
            return this.each(function() { this.style.display = this._R_oldDisplay || 'block'; });
        },
        hide: function() {
            return this.each(function() {
                const currentDisplay = window.getComputedStyle(this).display;
                if (currentDisplay !== 'none') this._R_oldDisplay = currentDisplay;
                this.style.display = 'none';
            });
        },
        css: function(prop, value) {
            if (typeof prop === 'string' && value === undefined) {
                return this.length > 0 ? window.getComputedStyle(this[0]).getPropertyValue(prop.replace(/([A-Z])/g, '-$1').toLowerCase()) : undefined;
            }
            let styles = {};
            if (typeof prop === 'object') styles = prop;
            else styles[prop] = value;
            return this.each(function() {
                for (let key in styles) this.style[key.replace(/-([a-z])/g, (g) => g[1].toUpperCase())] = styles[key];
            });
        },
        on: function(eventName, selectorOrHandler, handler) {
            if (typeof selectorOrHandler === 'function') {
                handler = selectorOrHandler;
                selectorOrHandler = undefined;
            }
            return this.each(function(i, el) {
                let eventListener = handler;
                if (selectorOrHandler) { // Delegated binding
                    eventListener = function(event) {
                        let currentTarget = event.target.closest(selectorOrHandler);
                        if (currentTarget && el.contains(currentTarget)) {
                            handler.call(currentTarget, event);
                        }
                    };
                    // Storing the listener for `off` is tricky without a robust event system
                    // This simple `on` doesn't store it easily for removal by (eventName, selector, handler)
                }
                el.addEventListener(eventName, eventListener, false);
            });
        },
        off: function(eventName, handler) { // Simplified: requires the same handler function, difficult for delegated.
            return this.each(function(i, el) { el.removeEventListener(eventName, handler, false); });
        },
        trigger: function(eventName) {
            return this.each(function() {
                let event;
                if (typeof this[eventName] === 'function') { this[eventName](); }
                else {
                    try { event = new Event(eventName, { bubbles: true, cancelable: true }); }
                    catch (e) { event = document.createEvent('Event'); event.initEvent(eventName, true, true); }
                    this.dispatchEvent(event);
                }
            });
        },
        attr: function(attrName, value) {
            if (value === undefined) return this.length > 0 ? this[0].getAttribute(attrName) : undefined;
            return this.each(function() { this.setAttribute(attrName, value); });
        },
        removeAttr: function(attrName) { return this.each(function() { this.removeAttribute(attrName); }); },
        prop: function(propName, value) {
            if (value === undefined) return this.length > 0 ? this[0][propName] : undefined;
            return this.each(function() { this[propName] = value; });
        },
        val: function(value) {
            if (value === undefined) return this.length > 0 ? this[0].value : undefined;
            return this.each(function() { this.value = value; });
        },
        html: function(htmlString) {
            if (htmlString === undefined) return this.length > 0 ? this[0].innerHTML : undefined;
            return this.each(function() { this.innerHTML = htmlString; });
        },
        text: function(textString) {
            if (textString === undefined) {
                let ret = ""; this.each(function() { ret += this.textContent; }); return ret;
            }
            return this.each(function() { this.textContent = textString; });
        },
        _manipulateDom: function(content, callback) {
            let toInsert, contentIsRInstance = content instanceof RInstance;
            if (typeof content === 'string') {
                let temp = document.createElement('div'); temp.innerHTML = content;
                toInsert = slice.call(temp.childNodes);
            } else if (content.nodeType) { toInsert = [content]; }
            else if (contentIsRInstance) { toInsert = content.toArray(); }
            else if (Array.isArray(content)) { toInsert = content; }
            else { return this; }

            const targetElements = this.toArray(); // Operate on a static list of targets
            return this.each(function(i, targetEl) {
                for (let j = 0; j < toInsert.length; j++) {
                    let itemToInsert = toInsert[j];
                    // Clone if it's not the last target element and item is part of document or was an R instance
                    let actualItem = (i < targetElements.length - 1 && (itemToInsert.parentNode || contentIsRInstance)) ?
                                     itemToInsert.cloneNode(true) : itemToInsert;
                    callback(targetEl, actualItem);
                }
            });
        },
        append: function(content) { return this._manipulateDom(content, (target, item) => target.appendChild(item)); },
        prepend: function(content) { return this._manipulateDom(content, (target, item) => target.insertBefore(item, target.firstChild)); },
        
        _animateOpacity: function(targetOpacity, duration, onComplete) {
            duration = duration || 400;
            return this.each(function() {
                const el = this;
                const startOpacity = parseFloat(window.getComputedStyle(el).opacity);
                if (targetOpacity > 0 && (el.style.display === 'none' || window.getComputedStyle(el).display === 'none')) {
                    el.style.opacity = 0; // Make it visible but transparent before starting animation
                    el.style.display = el._R_oldDisplay || 'block';
                }

                let start = null;
                function step(timestamp) {
                    if (!start) start = timestamp;
                    const progress = timestamp - start;
                    const currentOpacity = startOpacity + (targetOpacity - startOpacity) * Math.min(progress / duration, 1);
                    el.style.opacity = currentOpacity;

                    if (progress < duration) {
                        window.requestAnimationFrame(step);
                    } else {
                        el.style.opacity = targetOpacity;
                        if (typeof onComplete === 'function') onComplete.call(el);
                    }
                }
                window.requestAnimationFrame(step);
            });
        },
        fadeIn: function(duration) { return this._animateOpacity(1, duration); },
        fadeOut: function(duration) {
            return this._animateOpacity(0, duration, function() { this.style.display = 'none'; });
        },

        push: Array.prototype.push,
        sort: Array.prototype.sort,
        splice: Array.prototype.splice
    };

    RInstance.fn.init.prototype = RInstance.fn;
    RInstance.extend = RInstance.fn.extend = extend;

    RInstance.extend({
        isPlainObject: isPlainObject,
        each: staticEach,
        map: staticMap,
        grep: staticGrep,
        merge: staticMerge,
        encrypt: function(s) { // Base64 encode + filter non-standard base64 chars
            try { return btoa(String(s)).replace(/[^A-Za-z0-9+/=]/g, ''); }
            catch (e) { console.error("R.encrypt (btoa) failed:", e); return ""; }
        }
    });

    window._ = window.R = RInstance; // Use R as primary, _ as alias
    return RInstance;
})();

// --- Bible Tagger Extension ---
R.extend({
    bible: function(configOptions) {
        const defaults = {
            apiBaseUrl: "/", // Must be configured if not root
            spinnerUrl: "view/api/img/spinner.gif", // Relative to apiBaseUrl or full path
            booksString: "Genesis|Gen|Ge|Exodus|Exod|Ex|Exo|Leviticus|Lev|Numbers|Num|Deuteronomy|Deut|Joshua|Josh|" +
                "Judges|Judg|Ruth|1 Samuel|1 Sam|Samuel|2 Samuel|2 Sam|1 Kings|1 Kgs|Kings|2 Kings|2 Kgs|1 Chronicles|1 Chr|Chronicles|2 Chronicles|2 Chr|" +
                "Ezra|Nehemiah|Neh|Esther|Esth|Est|Job|Psalms|Ps|Psa|Psalm|Proverbs|Prov|Pro|Ecclesiastes|Eccl|Ecc|Song of Solomon|Song|Song of Songs|" +
                "Isaiah|Isa|Jeremiah|Jer|Lamentations|Lam|Ezekiel|Ezek|Daniel|Dan|Hosea|Hos|Joel|Amos|Obadiah|Obad|Oba|Jonah|Jon|Micah|Mic|" +
                "Nahum|Nah|Habakkuk|Hab|Zephaniah|Zeph|Haggai|Hag|Zechariah|Zech|Malachi|Mal|Matthew|Matt|Mat|Mark|Mk|Luke|Lk|John|Jn|Acts|Romans|Rom|" +
                "1 Corinthians|1 Cor|Corinthians|2 Corinthians|2 Cor|Galatians|Gal|Ephesians|Eph|Philippians|Phil|Colossians|Col|" +
                "1 Thessalonians|1 Thess|Thessalonians|2 Thessalonians|2 Thess|1 Timothy|1 Tim|Timothy|2 Timothy|2 Tim|Titus|Philemon|Phlm|Hebrews|Heb|" +
                "James|Jas|1 Peter|1 Pet|Peter|2 Peter|2 Pet|1 John|1 Jn|John|2 John|2 Jn|3 John|3 Jn|Jude|Revelation|Rev",
            taggingRootElement: document.body,
            verseLinkClass: 'fg-bible-verse-link',
            popupId: 'fg-bible-popup'
        };
        const settings = R.extend(true, {}, defaults, configOptions);
        
        // Sort books by length (desc) to help regex match longer names first (e.g., "1 Samuel" before "Samuel")
        const booksArray = settings.booksString.split('|').sort((a, b) => b.length - a.length);
        const verseRegexPattern = "\\b(" + booksArray.join("|").replace(/\./g, "\\.") + ")\\s+\\d+([:\\.]\\d+)?(?:(?:\\s*[,;&-]\\s*|\\s*(?:to|through|thru)\\s*)\\d+([:\\.]\\d+)?)*";
        const verseRegex = new RegExp(verseRegexPattern, "gim");

        let currentPopup = null;
        const passageCache = {};
        const mousePos = { x: 0, y: 0 };

        R.bible.setApiBaseUrl = function(url) { settings.apiBaseUrl = url; }; // Allow external setting

        function createPopup() {
            if (R('#' + settings.popupId).length > 0) return R('#' + settings.popupId);
            
            const popupHtml = `
                <div id="${settings.popupId}" class="fg-bible-popup" style="display:none; position:absolute; z-index:10000; border:1px solid #ccc; background:white; padding:10px; box-shadow: 2px 2px 5px rgba(0,0,0,0.2); min-width: 250px; max-width: 400px;">
                    <div class="fg-bible-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px; padding-bottom:5px; border-bottom:1px solid #eee;">
                        <span class="fg-bible-passage-ref" style="font-weight:bold;"></span>
                        <span class="fg-bible-version" style="font-size:0.9em; color:#555;">(KJV)</span>
                        <span class="fg-bible-close" style="cursor:pointer; font-weight:bold; padding: 0 5px; font-size:1.2em; line-height:1;">×</span>
                    </div>
                    <div class="fg-bible-content" style="min-height:50px; max-height: 300px; overflow-y:auto;">Loading...</div>
                    <div class="fg-bible-footer" style="font-size:0.8em; text-align:right; margin-top:5px; padding-top:5px; border-top:1px solid #eee;">
                        <span>Powered by God!</span> <span class="fg-bible-more" style="cursor:pointer; color:blue;">more »</span>
                    </div>
                </div>`;
            R('body').append(popupHtml);
            currentPopup = R('#' + settings.popupId);

            currentPopup.find('.fg-bible-close').on('click', () => currentPopup.hide());
            currentPopup.on('mouseleave', () => {
                 setTimeout(() => { // Delay to allow mouse re-entry from link
                    if (!currentPopup.get(0).matches(':hover')) currentPopup.hide();
                 }, 300);
            });
            currentPopup.on('mouseenter', () => currentPopup.show()); // Keep open if mouse enters popup
            return currentPopup;
        }
        
        document.addEventListener('mousemove', (e) => { mousePos.x = e.pageX; mousePos.y = e.pageY; });

        function showPopupForVerse(verseText) {
            currentPopup = currentPopup || createPopup();
            
            const passageRef = verseText.trim();
            currentPopup.find('.fg-bible-passage-ref').text(passageRef);
            const spinnerPath = (settings.spinnerUrl.startsWith('http') ? '' : settings.apiBaseUrl) + settings.spinnerUrl;
            currentPopup.find('.fg-bible-content').html(`<div align="center"><img src="${spinnerPath}" alt="Loading..."></div>`);

            const popupWidth = currentPopup.get(0).offsetWidth;
            const popupHeight = currentPopup.get(0).offsetHeight; // Get initial height

            let left = mousePos.x + 15;
            let top = mousePos.y + 15;

            if ((left + popupWidth) > window.innerWidth) left = Math.max(15, window.innerWidth - popupWidth - 15);
            if ((top + popupHeight) > (window.innerHeight + window.scrollY)) top = Math.max(window.scrollY + 15, mousePos.y - popupHeight - 15);
             if (top < window.scrollY) top = window.scrollY + 15;


            currentPopup.css({ top: top + 'px', left: left + 'px' }).show();

            const cacheKey = R.encrypt(passageRef);
            if (passageCache[cacheKey]) {
                currentPopup.find('.fg-bible-content').html('<div>' + passageCache[cacheKey] + '</div>');
                return;
            }
            
            const callbackName = 'fgBibleCallback_' + cacheKey.replace(/[^A-Za-z0-9_]/g, ''); // Ensure valid JS function name
            window[callbackName] = function(json) {
                passageCache[cacheKey] = json.content;
                // Check if popup is still for this verse (user might have moused over another link quickly)
                if (currentPopup.get(0) && R(currentPopup.get(0).querySelector('.fg-bible-passage-ref')).text() === passageRef) {
                    currentPopup.find('.fg-bible-content').html('<div>' + json.content + '</div>');
                }
                try { delete window[callbackName]; } catch (e) { window[callbackName] = undefined; }
                const scriptTag = document.getElementById('fgBibleScript_' + cacheKey);
                if (scriptTag) scriptTag.remove();
            };

            const script = document.createElement('script');
            script.id = 'fgBibleScript_' + cacheKey;
            script.src = settings.apiBaseUrl + 'view/api/fgbible/lib/fgparser.php?passage=' + encodeURIComponent(passageRef) + '&callback=' + callbackName;
            script.onerror = () => {
                if (currentPopup.get(0) && R(currentPopup.get(0).querySelector('.fg-bible-passage-ref')).text() === passageRef) {
                     currentPopup.find('.fg-bible-content').text('Error loading passage.');
                }
                try { delete window[callbackName]; } catch (e) { window[callbackName] = undefined; }
                if (script.parentNode) script.remove();
            };
            document.head.appendChild(script);
        }

        function tagVersesInElement(rootElement) {
            const treeWalker = document.createTreeWalker(rootElement, NodeFilter.SHOW_TEXT, {
                acceptNode: function (node) {
                    // Skip nodes inside SCRIPT, STYLE, A, TEXTAREA, INPUT, or already processed tags, or the popup itself
                    let parent = node.parentNode;
                    while(parent && parent !== rootElement.ownerDocument.body && parent !== rootElement) {
                        if (['SCRIPT', 'STYLE', 'A', 'TEXTAREA', 'INPUT', 'BUTTON', 'SELECT'].includes(parent.tagName.toUpperCase()) ||
                            (parent.classList && (parent.classList.contains(settings.verseLinkClass) || parent.id === settings.popupId)) ||
                            parent.isContentEditable) {
                            return NodeFilter.FILTER_REJECT;
                        }
                        parent = parent.parentNode;
                    }
                    if (node.nodeValue.trim() === '' || !verseRegex.test(node.nodeValue)) {
                        return NodeFilter.FILTER_SKIP;
                    }
                    verseRegex.lastIndex = 0;
                    return NodeFilter.FILTER_ACCEPT;
                }
            });

            const nodesToProcess = [];
            while (treeWalker.nextNode()) nodesToProcess.push(treeWalker.currentNode);

            nodesToProcess.forEach(textNode => {
                const textContent = textNode.nodeValue;
                const fragment = document.createDocumentFragment();
                let lastIndex = 0;
                let match;
                verseRegex.lastIndex = 0;

                while ((match = verseRegex.exec(textContent)) !== null) {
                    const verse = match[0];
                    // Check if the matched book is one of the known books (first capture group from regex)
                    // This is an additional check if the regex for book names might be too broad.
                    // const matchedBookName = match[1];
                    // if (!booksArray.some(b => new RegExp("^" + b.replace(/\./g, "\\.") + "$", "i").test(matchedBookName))) {
                    //     continue; // Not a recognized book variation if check fails
                    // }

                    if (match.index > lastIndex) {
                        fragment.appendChild(document.createTextNode(textContent.substring(lastIndex, match.index)));
                    }
                    const link = document.createElement('a');
                    link.href = '#' + encodeURIComponent(verse) + '?' + Date.now();
                    link.className = settings.verseLinkClass;
                    link.textContent = verse;
                    // link.style.textDecoration = 'underline'; // Style with CSS instead
                    // link.style.cursor = 'pointer';
                    fragment.appendChild(link);
                    lastIndex = verseRegex.lastIndex;
                }
                if (lastIndex < textContent.length) {
                    fragment.appendChild(document.createTextNode(textContent.substring(lastIndex)));
                }
                if (fragment.childNodes.length > 0 && textNode.parentNode) {
                    textNode.parentNode.replaceChild(fragment, textNode);
                }
            });
        }
        
        tagVersesInElement(settings.taggingRootElement);

        R(document).on('mouseover', '.' + settings.verseLinkClass, function(e) {
            showPopupForVerse(R(this).text());
        });

        // Ensure namespace for compatibility if original fixed JSONP callback is absolutely needed by server
        // but ideally, server supports dynamic callback names.
        if (!window.org) window.org = {};
        if (!window.org.facegod) window.org.facegod = {};
        if (!window.org.facegod.FGBibleTagger) window.org.facegod.FGBibleTagger = {};
    }
});

// --- Idle Timer Extension ---
R.extend({
    idle: (function() {
        const Idle = function(options) {
            if (!(this instanceof Idle)) return new Idle(options);

            this.options = R.extend(true, {
                isIdle: false, idleTimeout: 10000, onIdle: null, onAlive: null,
                onVisible: null, onHidden: null,
                events: ['click', 'mousemove', 'mouseenter', 'keypress', 'keyup', 'keydown', 'scroll', 'touchstart', 'mousewheel', 'touchmove'],
                viewChangeEvents: ['visibilitychange', 'webkitvisibilitychange', 'msvisibilitychange', 'mozvisibilitychange']
            }, options);

            this.idleStamp = 0; this.idleTimer = null; this.listener = null;

            this._handleActivity = this._handleActivity.bind(this);
            this._checkIdleStatus = this._checkIdleStatus.bind(this);
            this._handleVisibilityChange = this._handleVisibilityChange.bind(this);
        };

        Idle.prototype = {
            constructor: Idle,
            _handleActivity: function() {
                this.idleStamp = Date.now() + this.options.idleTimeout;
                if (this.options.isIdle) {
                    this.options.isIdle = false;
                    if (typeof this.options.onAlive === 'function') this.options.onAlive.call(this);
                    this.start(); // Restart timer accurately
                }
            },
            _attachEvents: function() {
                this.options.events.forEach(name => window.addEventListener(name, this._handleActivity, false));
            },
            _detachEvents: function() {
                this.options.events.forEach(name => window.removeEventListener(name, this._handleActivity, false));
            },
            start: function() {
                this._handleActivity(); // Set initial stamp
                clearTimeout(this.idleTimer);
                this.idleTimer = setTimeout(this._checkIdleStatus, this.options.idleTimeout + 100);

                if (!this.listener) {
                    this.listener = this._handleVisibilityChange;
                    this.options.viewChangeEvents.forEach(name => document.addEventListener(name, this.listener, false));
                }
                this._attachEvents();
                return this;
            },
            stop: function() {
                clearTimeout(this.idleTimer);
                this._detachEvents();
                if (this.listener) {
                    this.options.viewChangeEvents.forEach(name => document.removeEventListener(name, this.listener, false));
                    this.listener = null;
                }
                return this;
            },
            setIdleTimeout: function(ms) {
                this.options.idleTimeout = parseInt(ms, 10);
                if (this.idleTimer) this.start();
                return this;
            },
            _checkIdleStatus: function() {
                const currentTime = Date.now();
                if (currentTime < this.idleStamp) {
                    this.options.isIdle = false;
                    clearTimeout(this.idleTimer);
                    this.idleTimer = setTimeout(this._checkIdleStatus, (this.idleStamp - currentTime) + 100);
                } else {
                    this.options.isIdle = true;
                    if (typeof this.options.onIdle === 'function') this.options.onIdle.call(this);
                }
            },
            _handleVisibilityChange: function() {
                const isHidden = document.hidden || document.msHidden || document.webkitHidden || document.mozHidden;
                if (isHidden) {
                    if (typeof this.options.onHidden === 'function') this.options.onHidden.call(this);
                } else {
                    if (typeof this.options.onVisible === 'function') this.options.onVisible.call(this);
                    this._handleActivity();
                }
            },
            isIdle: function() { return this.options.isIdle; }
        };
        return Idle;
    })()
});

// --- AJAX Utilities ---
R.extend({
    _createXHR: function() {
        try { return new XMLHttpRequest(); } catch (e1) {
            try { return new ActiveXObject("Msxml2.XMLHTTP"); } catch (e2) {
                try { return new ActiveXObject("Microsoft.XMLHTTP"); } catch (e3) { return false; }
            }
        }
    },
    _serializeParams: function(data) {
        if (typeof data === 'string') return data;
        if (!R.isPlainObject(data) && !Array.isArray(data)) return '';
        
        const params = [];
        function add(key, value) {
            value = (typeof value === 'function') ? value() : (value == null ? "" : value);
            params.push(encodeURIComponent(key) + "=" + encodeURIComponent(value));
        }
        if (Array.isArray(data)) R.each(data, (i, v) => add(v.name, v.value));
        else for (let prefix in data) if (Object.prototype.hasOwnProperty.call(data, prefix)) add(prefix, data[prefix]);
        return params.join("&").replace(/%20/g, "+");
    },
    ajax: function(options) {
        if (typeof options === 'string') options = { url: options };
        const settings = R.extend(true, {}, {
            method: 'GET', url: '', data: null, dataType: 'text',
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            headers: {}, async: true, cache: true, timeout: 0,
            beforeSend: null, success: null, error: null, complete: null
        }, options);
        settings.method = settings.method.toUpperCase();

        const xhr = this._createXHR();
        if (!xhr) {
            if (typeof settings.error === 'function') settings.error.call(settings, null, 'NoXHR', 'No XHR object available.');
            if (typeof settings.complete === 'function') settings.complete.call(settings, null, 'NoXHR');
            const dummyPromise = { fail: function(){ return this; }, done: function(){ return this; }, always: function(){ return this; }, abort: function() {} };
            if (options.success) dummyPromise.done(options.success);
            if (options.error) dummyPromise.fail(options.error);
            if (options.live) dummyPromise.always(options.live);
            return dummyPromise;
        }

        let requestData = null;
        if (settings.data) requestData = this._serializeParams(settings.data);
        if (settings.method === 'GET' && requestData) {
            settings.url += (settings.url.includes('?') ? '&' : '?') + requestData;
            requestData = null;
        }
        if (!settings.cache) settings.url += (settings.url.includes('?') ? '&' : '?') + '_=' + Date.now();

        xhr.open(settings.method, settings.url, settings.async);
        if (requestData && settings.method !== 'GET' && settings.contentType) xhr.setRequestHeader('Content-Type', settings.contentType);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        for (let hName in settings.headers) xhr.setRequestHeader(hName, settings.headers[hName]);

        if (typeof settings.beforeSend === 'function' && settings.beforeSend.call(settings, xhr, settings) === false) {
            if (typeof settings.complete === 'function') settings.complete.call(settings, xhr, 'abort');
            xhr.abort();
            return { fail: function(){ return this; }, done: function(){ return this; }, always: function(){ return this; }, abort: function() {xhr.abort();} };
        }

        let timeoutId = null;
        if (settings.async && settings.timeout > 0) {
            timeoutId = setTimeout(() => {
                xhr.abort();
                if (typeof settings.error === 'function') settings.error.call(settings, xhr, 'timeout', 'Request timed out.');
                if (typeof settings.complete === 'function') settings.complete.call(settings, xhr, 'timeout');
            }, settings.timeout);
        }
        
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                clearTimeout(timeoutId);
                let status = xhr.status, textStatus, responseData;
                if ((status >= 200 && status < 300) || status === 304) {
                    textStatus = 'success'; responseData = xhr.responseText;
                    if (settings.dataType === 'json') {
                        try { responseData = JSON.parse(responseData); }
                        catch (e) { textStatus = 'parsererror'; if (typeof settings.error === 'function') settings.error.call(settings, xhr, textStatus, e); }
                    } else if (settings.dataType === 'xml') {
                        responseData = xhr.responseXML;
                        if (!responseData || !responseData.documentElement || responseData.getElementsByTagName("parsererror").length) {
                            textStatus = "parsererror"; if (typeof settings.error === 'function') settings.error.call(settings, xhr, textStatus, "Invalid XML");
                        }
                    }
                    if (textStatus === 'success' && typeof settings.success === 'function') settings.success.call(settings, responseData, textStatus, xhr);
                } else {
                    textStatus = 'error';
                    let errorThrown = xhr.statusText || (status === 0 ? "Network Error" : null);
                    if (typeof settings.error === 'function') settings.error.call(settings, xhr, textStatus, errorThrown);
                }
                if (typeof settings.complete === 'function') settings.complete.call(settings, xhr, textStatus);
            }
        };
        xhr.send(requestData);

        const deferred = { xhr: xhr,
            done: function(cb) { settings.success = cb; return this; },
            fail: function(cb) { settings.error = cb; return this; },
            always: function(cb) { settings.complete = cb; return this; },
            abort: function() { xhr.abort(); return this; }
        };
        if (options.success) deferred.done(options.success); // From original passed options
        if (options.error) deferred.fail(options.error);     // From original passed options
        if (options.live) deferred.always(options.live);     // From original passed options (live -> always)
        return deferred;
    },
    get: function(url, data, success, dataType) {
        if (typeof data === 'function') { dataType = success; success = data; data = undefined; }
        return R.ajax({ url: url, method: 'GET', data: data, success: success, dataType: dataType });
    },
    post: function(url, data, success, dataType) {
        if (typeof data === 'function') { dataType = success; success = data; data = undefined; }
        return R.ajax({ url: url, method: 'POST', data: data, success: success, dataType: dataType });
    },
    getJSON: function(url, data, success) { return R.get(url, data, success, 'json'); },
    getScript: function(url, success) { // Simplified: fetches script as text, doesn't execute.
        return R.ajax({ url: url, method: 'GET', success: success, dataType: 'text', cache: false });
    }
    // measureBW and measureCB are application-specific, consider moving them.
});

// --- Application-specific onload/ready logic ---
R(function() { // R's document ready
    // Initialize Idle Timer
    const idleTimer = R.idle({
        idleTimeout: 15000, // 15 seconds
        onIdle: function() { console.log("User is idle."); },
        onAlive: function() { console.log("User is active."); },
        onHidden: function() { console.log("Page hidden."); },
        onVisible: function() { console.log("Page visible."); }
    }).start();
    // window.myAppIdleTimer = idleTimer; // Expose if needed

    // Initialize Bible Tagger
    // Configure API base URL if needed (e.g., from server-generated JS variable)
    // if (window.myAppConfig && window.myAppConfig.bibleApiBaseUrl) {
    //    R.bible.setApiBaseUrl(window.myAppConfig.bibleApiBaseUrl);
    // }
    R.bible(); // Call with default settings or pass custom { apiBaseUrl: '...', ... }

    // Example: PHP-driven login/register button handlers (assuming window.myAppConfig.formProcessorUrl... are set)
    /*
    if (window.myAppConfig) {
        if (R("#signupButton").length > 0 && window.myAppConfig.formProcessorUrlRegister) { // Check if on register page
            R("#signupButton").on('click', function() {
                R.ajax({
                    url: window.myAppConfig.formProcessorUrlRegister, method: 'POST',
                    data: {
                        fn: R('#fullname').val(), username: R('#email').val(),
                        password: R('#password').val(), password2: R('#password2').val()
                    },
                    dataType: 'json' // Expect JSON response
                }).done(function(data) {
                    console.log("Registration success:", data);
                    // if (data.success) location.reload(); else alert(data.message);
                }).fail(function(xhr, status, error) { console.error("Registration failed:", status, error); alert("Registration error."); });
            });
        } else if (R("#ajaxButton").length > 0 && window.myAppConfig.formProcessorUrlLogin) { // Check if on login page
            R("#ajaxButton").on('click', function() {
                R.ajax({
                    url: window.myAppConfig.formProcessorUrlLogin, method: 'POST',
                    data: { username: R('#email').val(), password: R('#password').val() },
                    dataType: 'json'
                }).done(function(data) {
                    console.log("Login success:", data);
                    // if (data.success) location.href = 'index.php'; else alert(data.message);
                }).fail(function(xhr, status, error) { console.error("Login failed:", status, error); alert("Login error."); });
            });
        }
    }
    */
});

// R('.reg').show(); // This line was at the end of the original code. Place it appropriately.
// For example, inside R(function() { ... }) if '.reg' should be shown on page load.
R(function() {
    if (R('.reg').length) R('.reg').show();
});