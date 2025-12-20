(function(window){
'use strict';

// Minimal jQuery-like DOM helper (no dependency)
// Usage examples:
//  const $btn = $('.btn');
//  $('.btn').on('click', e => console.log(e));
//  $('#id').html('<b>x</b>');
//  $.get('/api', {q:1}).then(res=>console.log(res));

function _isNode(o){ return o && typeof o.nodeType === 'number'; }
function _isNodeList(o){ return NodeList && o instanceof NodeList; }

function Wrapper(nodes){
    this.nodes = Array.isArray(nodes) ? nodes : (nodes ? [nodes] : []);
}

Wrapper.prototype.each = function(fn){ this.nodes.forEach((n,i)=>fn.call(n,n,i)); return this; };
Wrapper.prototype.on = function(event, selectorOrHandler, handler){
    if(typeof selectorOrHandler === 'string'){
        // delegated
        this.each(function(el){
            el.addEventListener(event, function(e){
                const target = e.target.closest(selectorOrHandler);
                if(target && el.contains(target)) handler.call(target,e);
            });
        });
    } else {
        // direct
        this.each(function(el){ el.addEventListener(event, selectorOrHandler); });
    }
    return this;
};
Wrapper.prototype.off = function(event, handler){ this.each(el=>el.removeEventListener(event, handler)); return this; };
Wrapper.prototype.css = function(prop, val){
    if(typeof prop === 'string') this.each(el=>el.style[prop]=val);
    else for(const k in prop) this.each(el=>el.style[k]=prop[k]);
    return this;
};
Wrapper.prototype.addClass = function(name){ this.each(el=>el.classList.add(name)); return this; };
Wrapper.prototype.removeClass = function(name){ this.each(el=>el.classList.remove(name)); return this; };
Wrapper.prototype.toggleClass = function(name){ this.each(el=>el.classList.toggle(name)); return this; };
Wrapper.prototype.attr = function(name, val){
    if(arguments.length===1) return this.nodes[0] ? this.nodes[0].getAttribute(name) : null;
    this.each(el=>el.setAttribute(name,val)); return this;
};
Wrapper.prototype.html = function(markup){ if(arguments.length===0) return this.nodes[0] ? this.nodes[0].innerHTML : null; this.each(el=>el.innerHTML=markup); return this; };
Wrapper.prototype.text = function(str){ if(arguments.length===0) return this.nodes[0] ? this.nodes[0].textContent : null; this.each(el=>el.textContent=str); return this; };
Wrapper.prototype.append = function(child){
    if(typeof child === 'string') this.each(el=>el.insertAdjacentHTML('beforeend', child));
    else if(_isNode(child)) this.each(el=>el.appendChild(child.cloneNode(true)));
    else if(child instanceof Wrapper) this.each(el=> child.nodes.forEach(n=>el.appendChild(n.cloneNode(true))));
    return this;
};
Wrapper.prototype.find = function(selector){
    const found = [];
    this.each(el=> found.push.apply(found, Array.prototype.slice.call(el.querySelectorAll(selector))));
    return $(found);
};
Wrapper.prototype.closest = function(selector){
    return $((this.nodes[0] && this.nodes[0].closest(selector)) || []);
};
Wrapper.prototype.show = function(){ this.each(el=>el.style.display=''); return this; };
Wrapper.prototype.hide = function(){ this.each(el=>el.style.display='none'); return this; };

// Core $ function
function $(selector, ctx){
    if(!selector) return new Wrapper([]);
    if(typeof selector === 'string'){
        const root = ctx && ctx.nodes ? ctx.nodes[0] : (ctx || document);
        const nlist = root.querySelectorAll(selector);
        return new Wrapper(Array.prototype.slice.call(nlist));
    }
    if(_isNode(selector)) return new Wrapper(selector);
    if(_isNodeList(selector) || Array.isArray(selector)) return new Wrapper(Array.prototype.slice.call(selector));
    if(selector instanceof Wrapper) return selector;
    return new Wrapper([]);
}

// Document ready
$.ready = function(fn){
    if(document.readyState === 'complete' || document.readyState === 'interactive') setTimeout(fn,0);
    else document.addEventListener('DOMContentLoaded', fn);
};

// Simple AJAX helper returning Promise
$.ajax = function(opts){
    return new Promise(function(resolve,reject){
        var xhr = new XMLHttpRequest();
        var method = (opts.method || 'GET').toUpperCase();
        var url = opts.url || opts;
        var data = opts.data || null;
        if(method === 'GET' && data){
            var q = Object.keys(data).map(k=>encodeURIComponent(k)+'='+encodeURIComponent(data[k])).join('&');
            url += (url.indexOf('?')===-1 ? '?' : '&') + q;
        }
        xhr.open(method, url, true);
        if(opts.headers){ for(var h in opts.headers) xhr.setRequestHeader(h, opts.headers[h]); }
        xhr.onload = function(){
            var res = xhr.responseText;
            var ctype = xhr.getResponseHeader('Content-Type') || '';
            if(ctype.indexOf('application/json') !== -1){ try{ res = JSON.parse(res); }catch(e){}}
            if(xhr.status >= 200 && xhr.status < 300) resolve(res); else reject({status:xhr.status, response:res});
        };
        xhr.onerror = function(){ reject({status:xhr.status, response:xhr.responseText}); };
        if(method !== 'GET' && data && typeof data === 'object'){
            if(opts.contentType === 'json' || opts.headers && opts.headers['Content-Type'] && opts.headers['Content-Type'].indexOf('application/json') !== -1){
                xhr.setRequestHeader('Content-Type','application/json');
                xhr.send(JSON.stringify(data));
            } else {
                // default form encoding
                var fd = new FormData();
                for(var k in data) fd.append(k,data[k]);
                xhr.send(fd);
            }
        } else xhr.send();
    });
};
$.get = function(url, data){ return $.ajax({url:url, method:'GET', data:data}); };
$.post = function(url, data){ return $.ajax({url:url, method:'POST', data:data}); };
$.create = function(tag, attrs){ var el = document.createElement(tag); if(attrs) for(var k in attrs) if(k==='text') el.textContent = attrs[k]; else el.setAttribute(k,attrs[k]); return new Wrapper(el); };

// Expose
window.$ = $;
window._dom = { Wrapper: Wrapper };

})(window);
