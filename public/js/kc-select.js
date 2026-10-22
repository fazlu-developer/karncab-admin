(function () {
  function upgrade(select) {
    if (!(select instanceof HTMLSelectElement)) return;
    if (select.dataset.kcSelect === '1' || select.multiple || select.size > 1) return;
    if (select.closest('.kc-select')) return;
    select.dataset.kcSelect = '1';
    select.classList.add('kc-select-native');

    var wrap = document.createElement('div');
    wrap.className = 'kc-select';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);

    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'kc-select-btn';
    var panel = document.createElement('div');
    panel.className = 'kc-select-panel';
    panel.hidden = true;
    var search = document.createElement('input');
    search.type = 'search';
    search.className = 'kc-select-search';
    search.placeholder = 'Search';
    search.setAttribute('autocomplete', 'off');
    var list = document.createElement('div');
    list.className = 'kc-select-list';
    panel.appendChild(search);
    panel.appendChild(list);
    wrap.appendChild(button);
    wrap.appendChild(panel);

    function currentLabel() {
      var option = select.options[select.selectedIndex];
      button.textContent = option ? option.textContent.trim() : 'Select';
    }

    function addOption(option) {
      var row = document.createElement('div');
      row.className = 'kc-select-option' + (option.selected ? ' is-selected' : '');
      row.setAttribute('role', 'option');
      row.textContent = option.textContent.trim();
      row.addEventListener('click', function () {
        select.value = option.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        currentLabel();
        close();
      });
      list.appendChild(row);
    }

    function render() {
      var query = search.value.trim().toLowerCase();
      list.innerHTML = '';
      Array.prototype.forEach.call(select.children, function (node) {
        if (node.tagName === 'OPTGROUP') {
          var matches = Array.prototype.filter.call(node.children, function (option) {
            return query === '' || option.textContent.toLowerCase().indexOf(query) >= 0 || node.label.toLowerCase().indexOf(query) >= 0;
          });
          if (matches.length === 0) return;
          var header = document.createElement('div');
          header.className = 'kc-select-group';
          header.textContent = node.label;
          list.appendChild(header);
          matches.forEach(addOption);
          return;
        }
        if (node.tagName !== 'OPTION') return;
        if (query !== '' && node.textContent.toLowerCase().indexOf(query) < 0) return;
        addOption(node);
      });
    }

    function close() {
      panel.hidden = true;
      search.value = '';
    }

    button.addEventListener('click', function () {
      var open = panel.hidden;
      document.querySelectorAll('.kc-select-panel').forEach(function (other) {
        if (other !== panel) other.hidden = true;
      });
      if (open) {
        panel.hidden = false;
        render();
        search.focus();
      } else {
        close();
      }
    });
    search.addEventListener('input', render);
    search.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') close();
    });
    document.addEventListener('click', function (event) {
      if (!wrap.contains(event.target)) close();
    });
    select.addEventListener('change', currentLabel);
    new MutationObserver(function () {
      currentLabel();
      if (!panel.hidden) render();
    }).observe(select, { childList: true, subtree: true });
    currentLabel();
  }

  function boot() {
    document.querySelectorAll('select').forEach(upgrade);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
  new MutationObserver(boot).observe(document.body, { childList: true, subtree: true });
})();
