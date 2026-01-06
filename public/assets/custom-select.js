// Minimal custom select dropdown for theme compatibility
// Usage: Replace <select> with <div class="custom-select">...</div> and call initCustomSelect()

function initCustomSelect() {
  document.querySelectorAll('.custom-select').forEach(function(wrapper) {
    const select = wrapper.querySelector('select');
    if (!select) return;
    // Hide native select
    select.style.display = 'none';
    // Create custom dropdown
    const selected = document.createElement('div');
    selected.className = 'selected-value';
    selected.textContent = select.options[select.selectedIndex].textContent;
    wrapper.appendChild(selected);
    const dropdown = document.createElement('div');
    dropdown.className = 'custom-options';
    for (let i = 0; i < select.options.length; i++) {
      const opt = document.createElement('div');
      opt.className = 'custom-option';
      opt.textContent = select.options[i].textContent;
      opt.dataset.value = select.options[i].value;
      if (i === select.selectedIndex) opt.classList.add('selected');
      opt.onclick = function() {
        select.selectedIndex = i;
        selected.textContent = opt.textContent;
        dropdown.querySelectorAll('.custom-option').forEach(o => o.classList.remove('selected'));
        opt.classList.add('selected');
        dropdown.classList.remove('open');
        select.dispatchEvent(new Event('change'));
      };
      dropdown.appendChild(opt);
    }
    wrapper.appendChild(dropdown);
    selected.onclick = function() {
      dropdown.classList.toggle('open');
    };
    document.addEventListener('click', function(e) {
      if (!wrapper.contains(e.target)) dropdown.classList.remove('open');
    });
  });
}

document.addEventListener('DOMContentLoaded', initCustomSelect);
