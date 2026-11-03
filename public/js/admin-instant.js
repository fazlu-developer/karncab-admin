(function () {
  function icons() {
    if (window.lucide) {
      window.lucide.createIcons();
    }
  }

  function rememberTabs() {
    document.querySelectorAll('[data-kc-tabs][id]').forEach(function (root) {
      var on = root.querySelector('[data-kc-tab].on');
      if (on) {
        sessionStorage.setItem('kc-tab:' + root.id, on.getAttribute('data-kc-tab') || '');
      }
    });
  }

  function restoreTabs(scope) {
    (scope || document).querySelectorAll('[data-kc-tabs][id]').forEach(function (root) {
      var name = sessionStorage.getItem('kc-tab:' + root.id);
      if (!name) return;
      var tab = root.querySelector('[data-kc-tab="' + name + '"]');
      if (tab) showTab(root, tab);
    });
  }

  function showTab(root, tab) {
    var name = tab.getAttribute('data-kc-tab');
    root.querySelectorAll('[data-kc-tab]').forEach(function (btn) {
      btn.classList.toggle('on', btn === tab);
    });
    root.querySelectorAll('[data-kc-panel]').forEach(function (panel) {
      panel.hidden = panel.getAttribute('data-kc-panel') !== name;
    });
    if (root.id) {
      sessionStorage.setItem('kc-tab:' + root.id, name || '');
    }
  }

  function autoTabs(scope) {
    var root = scope.querySelector ? scope : document;
    if (root.querySelector('[data-kc-tabs]')) return;
    var cards = Array.prototype.filter.call(root.querySelectorAll('section.card'), function (card) {
      return !card.parentElement || !card.parentElement.closest('section.card');
    });
    if (cards.length < 4) return;
    var parent = cards[0].parentElement;
    if (!cards.every(function (card) { return card.parentElement === parent; })) return;
    var wrap = document.createElement('div');
    wrap.className = 'kc-tabs';
    wrap.id = 'page-sections';
    wrap.setAttribute('data-kc-tabs', '');
    var bar = document.createElement('div');
    bar.className = 'kc-tabbar';
    bar.setAttribute('role', 'tablist');
    parent.insertBefore(wrap, cards[0]);
    wrap.appendChild(bar);
    cards.forEach(function (card, index) {
      var heading = card.querySelector('h2, h1');
      var title = heading ? heading.textContent.trim() : ('Section ' + (index + 1));
      var name = 'part-' + index;
      var button = document.createElement('button');
      button.type = 'button';
      button.className = 'kc-tab' + (index === 0 ? ' on' : '');
      button.setAttribute('data-kc-tab', name);
      button.textContent = title.length > 36 ? title.slice(0, 36) : title;
      bar.appendChild(button);
      var panel = document.createElement('div');
      panel.className = 'kc-panel';
      panel.setAttribute('data-kc-panel', name);
      if (index !== 0) panel.hidden = true;
      panel.appendChild(card);
      wrap.appendChild(panel);
    });
  }

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-kc-tab]');
    if (!tab) return;
    var root = tab.closest('[data-kc-tabs]');
    if (!root) return;
    event.preventDefault();
    showTab(root, tab);
  });

  document.addEventListener('change', function (event) {
    var input = event.target;
    if (!(input instanceof HTMLInputElement) || input.type !== 'file') return;
    var accept = input.getAttribute('accept') || '';
    if (accept.indexOf('image') === -1 && input.name !== 'image') return;
    var box = input.parentNode.querySelector('.kc-image-preview');
    if (!box) {
      box = document.createElement('div');
      box.className = 'kc-image-preview';
      box.style.marginTop = '8px';
      input.parentNode.appendChild(box);
    }
    var file = input.files && input.files[0];
    box.innerHTML = file ? '<img alt="Preview" style="max-width:100%;max-height:160px;border-radius:10px;object-fit:cover">' : '';
    if (file) box.querySelector('img').src = URL.createObjectURL(file);
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (!form.closest('.page')) return;
    if (form.hasAttribute('data-no-instant') || (form.getAttribute('method') || 'get').toLowerCase() === 'get') return;
    event.preventDefault();
    rememberTabs();
    var page = document.querySelector('.page');
    var scroll = window.scrollY;
    page.classList.add('kc-saving');
    var data = new FormData(form);
    fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
    }).then(function (res) {
      var type = res.headers.get('content-type') || '';
      if (res.url && res.url.indexOf('/login') !== -1) {
        window.location.href = res.url;
        return null;
      }
      if (type.indexOf('text/html') === -1) {
        return res.blob().then(function (blob) {
          var link = document.createElement('a');
          link.href = URL.createObjectURL(blob);
          var match = /filename="?([^"]+)"?/.exec(res.headers.get('content-disposition') || '');
          link.download = match ? match[1] : 'download';
          link.click();
        });
      }
      return res.text();
    }).then(function (html) {
      if (typeof html !== 'string') return;
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var next = doc.querySelector('.page');
      if (!next) {
        window.location.reload();
        return;
      }
      page.innerHTML = next.innerHTML;
      autoTabs(page);
      restoreTabs(page);
      icons();
      document.dispatchEvent(new CustomEvent('kc:page'));
      window.scrollTo(0, scroll);
    }).catch(function () {
      window.location.reload();
    }).finally(function () {
      page.classList.remove('kc-saving');
    });
  });

  autoTabs(document);
  restoreTabs(document);
  icons();
})();
