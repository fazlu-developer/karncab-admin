(function () {
  var flight = null;
  var ticket = 0;

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
      var name = root.getAttribute('data-kc-open') || sessionStorage.getItem('kc-tab:' + root.id);
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

  function barStart() {
    var bar = document.querySelector('.kc-bar');
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'kc-bar';
      document.body.appendChild(bar);
    }
    bar.classList.remove('done');
    void bar.offsetWidth;
    bar.classList.add('on');
  }

  function barDone() {
    var bar = document.querySelector('.kc-bar');
    if (!bar) return;
    bar.classList.remove('on');
    bar.classList.add('done');
  }

  function spin(control) {
    if (!control || control.querySelector(':scope > .kc-spin')) return;
    var mark = document.createElement('span');
    mark.className = 'kc-spin';
    mark.setAttribute('aria-hidden', 'true');
    control.classList.add('is-loading');
    control.setAttribute('aria-busy', 'true');
    control.insertBefore(mark, control.firstChild);
  }

  function clearSpin() {
    document.querySelectorAll('.is-loading').forEach(function (control) {
      control.classList.remove('is-loading');
      control.removeAttribute('aria-busy');
      var mark = control.querySelector(':scope > .kc-spin');
      if (mark) mark.remove();
    });
  }

  function runScripts(root) {
    root.querySelectorAll('script').forEach(function (old) {
      var script = document.createElement('script');
      Array.prototype.forEach.call(old.attributes, function (attr) {
        script.setAttribute(attr.name, attr.value);
      });
      script.text = old.text;
      old.replaceWith(script);
    });
  }

  function needsFullLoad(doc) {
    var nodes = doc.querySelectorAll('script[src]');
    for (var i = 0; i < nodes.length; i++) {
      var src = nodes[i].getAttribute('src') || '';
      if (/maps\.googleapis|leaflet/i.test(src)) return true;
    }
    return false;
  }

  function applyDocument(doc, url) {
    var next = doc.querySelector('.page');
    var page = document.querySelector('.page');
    if (!next || !page) {
      window.location.href = url;
      return;
    }
    page.innerHTML = next.innerHTML;
    var heading = doc.querySelector('.topbar strong');
    var currentHeading = document.querySelector('.topbar strong');
    if (heading && currentHeading) currentHeading.innerHTML = heading.innerHTML;
    if (doc.title) document.title = doc.title;
    var active = {};
    doc.querySelectorAll('.side a.nav, .phone-nav a').forEach(function (link) {
      active[link.getAttribute('href')] = link.classList.contains('active');
    });
    document.querySelectorAll('.side a.nav, .phone-nav a').forEach(function (link) {
      var href = link.getAttribute('href');
      if (Object.prototype.hasOwnProperty.call(active, href)) {
        link.classList.toggle('active', active[href]);
      }
    });
    runScripts(page);
    autoTabs(page);
    restoreTabs(page);
    icons();
    document.dispatchEvent(new CustomEvent('kc:page'));
    window.scrollTo(0, 0);
  }

  function openPage(url, push) {
    var page = document.querySelector('.page');
    if (!page) {
      window.location.href = url;
      return;
    }
    if (flight) flight.abort();
    flight = new AbortController();
    var mine = ++ticket;
    barStart();
    page.classList.add('kc-saving');
    fetch(url, {
      credentials: 'same-origin',
      signal: flight.signal,
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
    }).then(function (res) {
      var type = res.headers.get('content-type') || '';
      if (!res.ok || type.indexOf('text/html') === -1 || (res.url && res.url.indexOf('/login') !== -1 && location.pathname.indexOf('/login') === -1)) {
        window.location.href = res.url || url;
        return null;
      }
      return res.text().then(function (html) {
        return { html: html, url: res.url || url };
      });
    }).then(function (payload) {
      if (!payload) return;
      var doc = new DOMParser().parseFromString(payload.html, 'text/html');
      if (needsFullLoad(doc) || !doc.querySelector('.page')) {
        window.location.href = payload.url;
        return;
      }
      applyDocument(doc, payload.url);
      if (push) {
        var next = new URL(payload.url, location.href);
        var here = location.pathname + location.search;
        var there = next.pathname + next.search;
        if (next.origin === location.origin && there !== here) {
          history.pushState({ kc: 1 }, '', there);
        }
      }
    }).catch(function (error) {
      if (error && error.name === 'AbortError') return;
      window.location.href = url;
    }).finally(function () {
      if (mine !== ticket) return;
      page.classList.remove('kc-saving');
      clearSpin();
      barDone();
    });
  }

  function sameAdmin(url) {
    if (!url || url.indexOf('mailto:') === 0 || url.indexOf('tel:') === 0 || url.indexOf('javascript:') === 0) return false;
    var next;
    try {
      next = new URL(url, location.href);
    } catch (error) {
      return false;
    }
    if (next.origin !== location.origin) return false;
    if (next.hash && next.pathname + next.search === location.pathname + location.search) return false;
    return true;
  }

  function isDownload(link) {
    if (link.hasAttribute('download') || link.getAttribute('target') === '_blank') return true;
    var href = link.getAttribute('href') || '';
    return /download=1|\/export\b|letterhead-file|\.(pdf|docx|xlsx|csv|zip)(\?|$)/i.test(href);
  }

  document.addEventListener('click', function (event) {
    var tab = event.target.closest('[data-kc-tab]');
    if (tab) {
      var root = tab.closest('[data-kc-tabs]');
      if (!root) return;
      event.preventDefault();
      showTab(root, tab);
      return;
    }
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    var link = event.target.closest('a[href]');
    if (!link || isDownload(link) || !sameAdmin(link.href)) return;
    var inMenu = link.closest('.side, .phone-nav');
    var inPage = link.closest('.page');
    if (!inMenu && !inPage) return;
    if (!document.querySelector('.page')) return;
    event.preventDefault();
    spin(link);
    openPage(link.href, true);
  });

  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    var button = event.submitter || form.querySelector('button[type="submit"], input[type="submit"], button:not([type])');
    spin(button);
    barStart();
    if (form.hasAttribute('data-no-instant') || !document.querySelector('.page') || !form.closest('.page')) return;
    var method = (form.getAttribute('method') || 'get').toLowerCase();
    if (method === 'get') {
      event.preventDefault();
      var params = new URLSearchParams(new FormData(form));
      var action = form.getAttribute('action') || location.pathname;
      var join = action.indexOf('?') === -1 ? '?' : '&';
      openPage(action + join + params.toString(), true);
      return;
    }
    event.preventDefault();
    var mine = ++ticket;
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
          clearSpin();
          barDone();
        });
      }
      return res.text().then(function (html) {
        return { html: html, url: res.url };
      });
    }).then(function (payload) {
      if (!payload || typeof payload.html !== 'string') return;
      var doc = new DOMParser().parseFromString(payload.html, 'text/html');
      if (needsFullLoad(doc) || !doc.querySelector('.page')) {
        window.location.href = payload.url || form.action;
        return;
      }
      applyDocument(doc, payload.url || form.action);
      if (payload.url) {
        var next = new URL(payload.url, location.href);
        var there = next.pathname + next.search;
        if (next.origin === location.origin && there !== location.pathname + location.search) {
          history.pushState({ kc: 1 }, '', there);
        }
      }
      window.scrollTo(0, scroll);
    }).catch(function () {
      form.submit();
    }).finally(function () {
      if (mine !== ticket) return;
      page.classList.remove('kc-saving');
      clearSpin();
      barDone();
    });
  });

  window.addEventListener('popstate', function () {
    if (!document.querySelector('.page')) return;
    openPage(location.href, false);
  });

  autoTabs(document);
  restoreTabs(document);
  icons();
})();
