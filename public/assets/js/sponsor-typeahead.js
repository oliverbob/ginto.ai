/* Sponsor Typeahead Component - CSS Embedded */
(function(){
    var style = document.createElement('style');
    style.innerHTML = `
    .typeahead-container { position: relative; max-width: 350px; }
    .typeahead-input { width: 100%; padding: 8px 12px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem; }
    .typeahead-list { position: absolute; top: 100%; left: 0; right: 0; background: #fff; border: 1px solid #ccc; border-top: none; z-index: 1000; max-height: 180px; overflow-y: auto; border-radius: 0 0 6px 6px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .typeahead-item { padding: 8px 12px; cursor: pointer; }
    .typeahead-item:hover, .typeahead-item.active { background: #f0f4ff; }
    .dark .typeahead-list { background: #23272f; border-color: #444; color: #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.32); }
    .dark .typeahead-item { color: #e2e8f0; }
    .dark .typeahead-item:hover, .dark .typeahead-item.active { background: #2d3748; }
    `;
    document.head.appendChild(style);

    window.initSponsorTypeahead = function(options) {
            var initialList = options.initialList || [];
        var input = document.getElementById(options.inputId);
        var container = document.createElement('div');
        container.className = 'typeahead-container';
        input.parentNode.insertBefore(container, input);
        container.appendChild(input);
        var list = document.createElement('div');
        list.className = 'typeahead-list';
        list.style.display = 'none';
        container.appendChild(list);
        var csrf = options.csrfToken;
        var api = options.apiUrl;
        var selected = null;

        input.setAttribute('autocomplete', 'off');
                // Show initial list on focus if available
                input.addEventListener('focus', function() {
                    if (initialList.length > 0 && input.value.trim().length < 2) {
                        list.innerHTML = '';
                        initialList.forEach(function(user) {
                            var item = document.createElement('div');
                            item.className = 'typeahead-item';
                            item.textContent = user.username;
                            item.onclick = function() {
                                updateField(user);
                                selected = user; // Always update selected
                                list.style.display = 'none';
                                if(options.onSelect) {
                                    options.onSelect(user);
                                }
                                input.blur();
                            };
                            list.appendChild(item);
                        });
                        list.style.display = 'block';
                    }
                });
        input.addEventListener('click', function(e) {
            // debug removed
        });
        input.addEventListener('input', function(e) {
            // debug removed
        });
        input.addEventListener('change', function(e) {
            // debug removed
        });
        input.addEventListener('focus', function(e) {
            // debug removed
        });
        input.addEventListener('blur', function(e) {
            // debug removed
        });
        input.addEventListener('keydown', function(e) {
            // debug removed
        });
        input.addEventListener('input', function() {
            selected = null; // Reset selected on input
            var val = input.value.trim();
            if(val.length < 2) {
                list.style.display = 'none';
                return;
            }
            // Filter initialList client-side for instant narrowing
            var filtered = initialList.filter(function(user) {
                return user.username.toLowerCase().includes(val.toLowerCase()) ||
                       (user.fullname && user.fullname.toLowerCase().includes(val.toLowerCase()));
            });
            if(filtered.length > 0) {
                list.innerHTML = '';
                filtered.forEach(function(user) {
                    var item = document.createElement('div');
                    item.className = 'typeahead-item';
                    item.textContent = user.username;
                    item.onclick = function() {
                        updateField(user);
                        selected = user; // Always update selected
                        list.style.display = 'none';
                        if(options.onSelect) {
                            options.onSelect(user);
                        }
                        input.blur();
                    };
                    list.appendChild(item);
                });
                list.style.display = 'block';
            } else {
                // If no local matches, fallback to AJAX
                var url = api + '?q=' + encodeURIComponent(val) + '&csrf_token=' + encodeURIComponent(csrf);
                fetch(url, {
                    method: 'GET'
                })
                .then(r => r.json())
                .then(data => {
                    list.innerHTML = '';
                    var users = data.users || [];
                    if(users.length) {
                        users.forEach(function(user) {
                            var item = document.createElement('div');
                            item.className = 'typeahead-item';
                            item.textContent = user.username;
                            item.onclick = function(e) {
                                updateField(user);
                                var sponsorIdField = document.getElementById('sponsorId');
                                selected = user;
                                list.style.display = 'none';
                                if(options.onSelect) {
                                    options.onSelect(user);
                                }
                                input.blur();
                            };
                            list.appendChild(item);
                        });
                        list.style.display = 'block';
                    } else {
                        list.style.display = 'none';
                    }
                });
            }
        });
        input.addEventListener('change', function(e) {
            // debug removed
        });
        document.addEventListener('click', function(e) {
            if(!container.contains(e.target)) list.style.display = 'none';
        });
        input.addEventListener('blur', function() {
            setTimeout(function(){ list.style.display = 'none'; }, 200);
        });
        let typeaheadItemClicked = false;

        document.addEventListener('mousedown', function(e) {
            if (e.target && e.target.classList.contains('typeahead-item')) {
                typeaheadItemClicked = true;
                var userId = e.target.getAttribute('data-user-id');
                var username = e.target.textContent;
                var sponsorInput = document.getElementById('sponsorInput');
                var sponsorIdField = document.getElementById('sponsorId');
                if (sponsorInput) sponsorInput.value = username;
                if (sponsorIdField) sponsorIdField.value = userId;
                setTimeout(function() { typeaheadItemClicked = false; }, 100);
            }
        });
        if (sponsorInput) {
            sponsorInput.addEventListener('blur', function(e) {
                setTimeout(function() {
                    if (!typeaheadItemClicked) {
                        list.style.display = 'none';
                    }
                }, 150);
            });
        }
        function updateField(user) {
            var sponsorInput = document.getElementById('sponsorInput');
            var sponsorIdField = document.getElementById('sponsorId');
            if (sponsorInput) {
                sponsorInput.value = user.username;
                sponsorInput.dispatchEvent(new Event('change'));
            }
            if (sponsorIdField) {
                sponsorIdField.value = user.id;
                sponsorIdField.dispatchEvent(new Event('change'));
            }
        }
        function createTypeaheadItem(user) {
            var item = document.createElement('div');
            item.className = 'typeahead-item';
            item.textContent = user.username;
            var userId = user.box || user.id || user.user_id || user.userid || user.ID || '';
            item.setAttribute('data-user-id', userId);
            item.addEventListener('mousedown', function(e) {
                updateField(user);
                var sponsorIdField = document.getElementById('sponsorId');
                selected = user;
                list.style.display = 'none';
                if(options.onSelect) {
                    options.onSelect(user);
                }
                // Do not blur here, let input handle it
            });
            return item;
        }
        return {
            getSelected: function() { return selected; },
            getValue: function() { return input.value; }
        };
    };
})();
