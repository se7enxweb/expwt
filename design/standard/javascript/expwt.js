/*
 * expwt: the website sidebar's shell, <exp-wt> (decisions 2-8, F9, F12). Vanilla JS and a Web Component with a Shadow
 * DOM; no framework, no build step; packed by ezjscore.
 *
 * - The closed tab, docked to the right or the left edge just below the site header, sticky once the header has
 *   scrolled away. Dragged along the edge and to the other edge (mouse, pen and touch), moved with the keyboard
 *   (arrows; Alt+Shift+E opens the sidebar from anywhere), opened with Enter.
 * - The open panel beside the page (push) on a wide window, over it (overlay) on a narrow one or by choice; its width
 *   dragged or set with the arrow keys on its edge.
 * - Where it is, is kept per user on the server (expwt/api state.save) and can be kept per device instead (the Me
 *   panel); a visitor who is not signed in keeps it in the browser only.
 * - For a signed-in user one deferred request after the page has loaded (boot); the panels' script only when the
 *   panel is first opened.
 * - Every write goes through call(): the data layer of the installation (expwt/api or the GraphQL API), the CSRF
 *   token, and "Verify it's you" when the administration would ask.
 */
(function () {
  'use strict';
  if (!window.customElements || window.customElements.get('exp-wt')) {
    return;
  }

  var DEVICE = 'expwt.device';
  var CACHE = 'expwt.cache';
  /* The light or dark mode the server last gave this device for the signed-in user (boot.theme), so a reload is
     drawn in it from the first frame instead of in the system's mode until the deferred boot request answers. */
  var THEME = 'expwt.theme';
  var SVG = 'http://www.w3.org/2000/svg';
  var canAdopt = 'adoptedStyleSheets' in Document.prototype && 'replaceSync' in CSSStyleSheet.prototype;

  function store(key, value) {
    try {
      if (value === undefined) {
        return JSON.parse(window.localStorage.getItem(key) || 'null');
      }
      if (value === null) {
        window.localStorage.removeItem(key);
      } else {
        window.localStorage.setItem(key, JSON.stringify(value));
      }
    } catch (e) {
      return null;
    }
    return null;
  }

  /* h('button', {class: 'wt-btn', onclick: fn}, ['text', node]) */
  function h(tag, attrs, children) {
    var el = document.createElement(tag);
    var name;
    attrs = attrs || {};
    for (name in attrs) {
      if (!Object.prototype.hasOwnProperty.call(attrs, name) || attrs[name] === null || attrs[name] === undefined || attrs[name] === false) {
        continue;
      }
      if (name.indexOf('on') === 0 && typeof attrs[name] === 'function') {
        el.addEventListener(name.slice(2), attrs[name]);
      } else if (name === 'text') {
        el.textContent = attrs[name];
      } else if (name === 'value') {
        el.value = attrs[name];
      } else if (name === 'checked') {
        el.checked = !!attrs[name];
      } else {
        el.setAttribute(name, attrs[name] === true ? '' : String(attrs[name]));
      }
    }
    (children || []).forEach(function (child) {
      if (child === null || child === undefined || child === false) {
        return;
      }
      el.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    });
    return el;
  }

  function icon(paths) {
    var svg = document.createElementNS(SVG, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('class', 'wt-tab-icon');
    svg.setAttribute('aria-hidden', 'true');
    svg.setAttribute('focusable', 'false');
    paths.forEach(function (d) {
      var p = document.createElementNS(SVG, 'path');
      p.setAttribute('d', d);
      p.setAttribute('fill', 'none');
      p.setAttribute('stroke', 'currentColor');
      p.setAttribute('stroke-width', '2');
      p.setAttribute('stroke-linecap', 'round');
      p.setAttribute('stroke-linejoin', 'round');
      svg.appendChild(p);
    });
    return svg;
  }

  function clamp(n, min, max) {
    return Math.max(min, Math.min(max, n));
  }

  /* The page's own stylesheet: the push of the page and the outlines of inline editing (never an attribute style). */
  var pageSheet = null;
  function pageCss(text) {
    if (!canAdopt) {
      return;
    }
    if (!pageSheet) {
      pageSheet = new CSSStyleSheet();
      document.adoptedStyleSheets = document.adoptedStyleSheets.concat([pageSheet]);
    }
    pageSheet.replaceSync(text);
  }

  class ExpWt extends HTMLElement {
    connectedCallback() {
      if (this.shadowRoot) {
        return;
      }
      var self = this;
      try {
        this.cfg = JSON.parse(this.getAttribute('data-config') || '{}');
      } catch (e) {
        this.cfg = {};
      }
      this.strings = this.cfg.strings || {};
      this.anonymous = !!this.cfg.anonymous;
      this.boot = null;
      this.panels = {};
      this.current = null;
      var device = store(DEVICE);
      this.deviceOnly = !!(device && device.only) || this.anonymous;
      this.state = this.normalize(Object.assign({}, this.anonymous || !device ? (store(CACHE) || {}) : {}, device && device.state ? device.state : {}));
      this.wide = window.matchMedia('(min-width: ' + ((this.cfg.overlayBelow || 768) + 1) + 'px)');
      this.dark = window.matchMedia('(prefers-color-scheme: dark)');
      this.render();
      this.applyState();
      this.wide.addEventListener('change', function () { self.applyState(); });
      this.dark.addEventListener('change', function () { self.applyTheme(); });
      /* the site's switch: follow it in the same frame (a mutation observer runs before the next paint) */
      if (window.MutationObserver) {
        new MutationObserver(function () { self.applyTheme(); })
          .observe(document.documentElement, { attributes: true, attributeFilter: ['data-exp-theme'] });
      }
      window.addEventListener('scroll', function () { self.place(); }, { passive: true });
      window.addEventListener('resize', function () { self.place(); });
      document.addEventListener('keydown', function (e) { self.shortcut(e); });
      window.addEventListener('message', function (e) { self.message(e); });
      if (!this.anonymous && this.cfg.boot) {
        var start = function () { setTimeout(function () { self.loadBoot(); }, 0); };
        if (document.readyState === 'complete') {
          start();
        } else {
          window.addEventListener('load', start, { once: true });
        }
      }
    }

    t(text) {
      var all = this.boot && this.boot.strings ? this.boot.strings : this.strings;
      return all[text] || this.strings[text] || text;
    }

    normalize(s) {
      var c = this.cfg;
      s = s || {};
      return {
        side: s.side === 'left' || s.side === 'right' ? s.side : (c.side || 'right'),
        offset: clamp(parseInt(s.offset, 10) || 0, 0, 4000),
        width: clamp(parseInt(s.width, 10) || c.width || 380, c.minWidth || 300, c.maxWidth || 720),
        open: s.open === true,
        mode: s.mode === 'overlay' ? 'overlay' : 'push',
        theme: ['light', 'dark'].indexOf(s.theme) >= 0 ? s.theme : 'auto',
        panel: ['page', 'create', 'structure', 'me'].indexOf(s.panel) >= 0 ? s.panel : 'page'
      };
    }

    render() {
      var self = this;
      var root = this.attachShadow({ mode: 'open' });
      if (canAdopt) {
        this.vars = new CSSStyleSheet();
        root.adoptedStyleSheets = [this.vars];
      }
      if (this.cfg.css) {
        /* The stylesheet arrives after the first frames: until it has, the tab would be drawn as a bare browser
           button (grey or black) and then turn into the sidebar's colors. Nothing of the sidebar is drawn before
           its stylesheet applies; it appears once, in its final look. */
        this.veil(true);
        var link = h('link', { rel: 'stylesheet', href: this.cfg.css });
        var shown = false;
        var show = function () {
          if (shown) { return; }
          shown = true;
          clearTimeout(self.veilTimer);
          self.veil(false);
          self.place();
        };
        link.addEventListener('load', show);
        link.addEventListener('error', show);
        this.veilTimer = setTimeout(show, 4000);
        root.appendChild(link);
      }
      this.tab = h('button', {
        type: 'button', class: 'wt-tab', 'aria-expanded': 'false', 'aria-controls': 'wt-panel',
        'aria-keyshortcuts': 'Alt+Shift+' + (this.cfg.key || 'E'),
        title: this.t('Website sidebar') + ' (Alt+Shift+' + (this.cfg.key || 'E') + ')',
        onpointerdown: function (e) { self.dragStart(e); },
        onkeydown: function (e) { self.tabKey(e); },
        onclick: function (e) { if (self.dragged) { e.preventDefault(); self.dragged = false; return; } self.toggle(); }
      }, [
        icon(['M4 6h16', 'M4 12h16', 'M4 18h10']),
        h('span', { class: 'wt-tab-label', text: this.anonymous ? this.t('Menu') : this.t('Edit') }),
        h('span', { class: 'wt-sr', text: this.t('Website sidebar') + '. ' + this.t('Arrow keys move it, Enter opens it.') })
      ]);
      this.resizer = h('div', {
        class: 'wt-resize', role: 'separator', tabindex: '0', 'aria-orientation': 'vertical',
        'aria-label': this.t('Width of the sidebar'),
        onpointerdown: function (e) { self.resizeStart(e); },
        onkeydown: function (e) { self.resizeKey(e); }
      });
      this.heading = h('h2', { id: 'wt-title', tabindex: '-1', text: this.t('Website sidebar') });
      this.tablist = h('div', { class: 'wt-tabs', role: 'tablist', 'aria-label': this.t('Panels') });
      this.body = h('div', { class: 'wt-body', id: 'wt-body', role: 'tabpanel', tabindex: '-1' });
      this.status = h('div', { class: 'wt-status', role: 'status', 'aria-live': 'polite' });
      this.panel = h('section', {
        id: 'wt-panel', class: 'wt-panel', role: 'complementary', 'aria-labelledby': 'wt-title', hidden: true,
        onkeydown: function (e) { if (e.key === 'Escape') { e.stopPropagation(); self.toggle(false, true); } }
      }, [
        this.resizer,
        h('div', { class: 'wt-head' }, [
          this.heading,
          h('button', { type: 'button', class: 'wt-btn is-icon', 'aria-label': this.t('Close the sidebar'), title: this.t('Close the sidebar'),
            onclick: function () { self.toggle(false, true); } }, ['×'])
        ]),
        this.tablist,
        this.body,
        this.status
      ]);
      root.appendChild(this.tab);
      root.appendChild(this.panel);
      this.buildTabs();
    }

    /* Hide the whole sidebar (on) or show it again (off), without an attribute style: a constructed stylesheet of
       its own in the shadow root, or the hidden attribute where a browser cannot adopt one. */
    veil(on) {
      var root = this.shadowRoot;
      if (canAdopt) {
        if (on && !this.veilSheet) {
          this.veilSheet = new CSSStyleSheet();
          this.veilSheet.replaceSync(':host{visibility:hidden!important}');
          root.adoptedStyleSheets = root.adoptedStyleSheets.concat([this.veilSheet]);
        } else if (!on && this.veilSheet) {
          var sheet = this.veilSheet;
          this.veilSheet = null;
          root.adoptedStyleSheets = root.adoptedStyleSheets.filter(function (s) { return s !== sheet; });
        }
      } else {
        this.toggleAttribute('hidden', !!on);
      }
    }

    available() {
      if (this.anonymous) {
        return [{ id: 'welcome', label: this.t('Welcome') }];
      }
      var c = this.boot ? this.boot.capabilities : { use: true };
      var list = [{ id: 'page', label: this.t('Page') }];
      if (c.create) { list.push({ id: 'create', label: this.t('Create') }); }
      if (c.structure) { list.push({ id: 'structure', label: this.t('Structure') }); }
      list.push({ id: 'me', label: this.t('Me') });
      return list;
    }

    buildTabs() {
      var self = this;
      var list = this.available();
      if (!list.some(function (p) { return p.id === self.state.panel; })) {
        this.state.panel = list[0].id;
      }
      this.tablist.textContent = '';
      this.tabButtons = list.map(function (p) {
        var b = h('button', {
          type: 'button', role: 'tab', id: 'wt-tab-' + p.id, 'aria-controls': 'wt-body', 'data-panel': p.id,
          'aria-selected': p.id === self.state.panel ? 'true' : 'false', tabindex: p.id === self.state.panel ? '0' : '-1',
          onclick: function () { self.show(p.id); },
          onkeydown: function (e) { self.tabsKey(e); }
        }, [p.label]);
        self.tablist.appendChild(b);
        return b;
      });
      this.tablist.hidden = list.length < 2;
    }

    tabsKey(e) {
      var i = this.tabButtons.indexOf(e.target);
      var n = this.tabButtons.length;
      var next = null;
      var rtl = getComputedStyle(this).direction === 'rtl';
      if (e.key === 'ArrowRight') { next = rtl ? i - 1 : i + 1; }
      if (e.key === 'ArrowLeft') { next = rtl ? i + 1 : i - 1; }
      if (e.key === 'Home') { next = 0; }
      if (e.key === 'End') { next = n - 1; }
      if (next === null) { return; }
      e.preventDefault();
      next = (next + n) % n;
      this.tabButtons[next].focus();
      this.show(this.tabButtons[next].getAttribute('data-panel'));
    }

    /* ------------------------------------------------------------------ place, side, width, theme */

    overlay() {
      return !this.wide.matches || this.state.mode === 'overlay';
    }

    headerBottom() {
      var sel = this.cfg.header || 'header';
      var el = null;
      try { el = document.querySelector(sel); } catch (e) { el = null; }
      if (!el) { return 0; }
      var r = el.getBoundingClientRect();
      return Math.max(0, Math.round(r.bottom));
    }

    place() {
      var self = this;
      if (this.placing) { return; }
      this.placing = true;
      window.requestAnimationFrame(function () {
        self.placing = false;
        var top = self.headerBottom();
        var room = Math.max(0, window.innerHeight - top - (self.tab.offsetHeight || 120));
        var offset = Math.min(self.state.offset, room);
        var css = ':host{--expwt-top:' + top + 'px;--expwt-offset:' + offset + 'px;--expwt-width:' + self.state.width + 'px}';
        if (self.vars) {
          self.vars.replaceSync(css);
        } else {
          self.style.setProperty('--expwt-top', top + 'px');
          self.style.setProperty('--expwt-offset', offset + 'px');
          self.style.setProperty('--expwt-width', self.state.width + 'px');
        }
        self.push();
      });
    }

    push() {
      var on = this.state.open && !this.overlay();
      document.documentElement.toggleAttribute('data-expwt-push', on);
      if (on) {
        document.documentElement.setAttribute('data-expwt-push', this.state.side);
      }
      this.pageRules = 'html[data-expwt-push="right"] body{margin-right:' + this.state.width + 'px}'
        + 'html[data-expwt-push="left"] body{margin-left:' + this.state.width + 'px}'
        + (this.inlineRules || '');
      pageCss(this.pageRules);
    }

    /* Light or dark, decided before the first frame: the sidebar's own choice; else, for a signed-in user, the
       server's answer (boot.theme: the administration's color mode), and until it has come firstTheme(); else (no
       choice, or the administration's "system") the page's: the site's own light or dark mode where its design has
       one (siteMode()), the system's elsewhere. The boot answer then only confirms it. */
    applyTheme() {
      var theme = this.state.theme;
      if (theme === 'auto') {
        theme = this.anonymous ? 'system' : (this.boot ? this.boot.theme : this.firstTheme());
      }
      if (theme !== 'light' && theme !== 'dark') {
        theme = this.siteMode() || (this.dark.matches ? 'dark' : 'light');
      }
      if (this.getAttribute('data-theme') !== theme) {
        this.setAttribute('data-theme', theme);
      }
    }

    /* The site design's own light or dark mode, set on <html> before the body is drawn by its head script and
       changed by its switch: html[data-exp-theme] light, dark or darker (the media designs' exp-color-mode.js).
       null where the design has none. */
    siteMode() {
      var mode = document.documentElement.getAttribute('data-exp-theme') || '';
      return mode === 'light' ? 'light' : (mode.indexOf('dark') === 0 ? 'dark' : null);
    }

    /* The signed-in user's mode before boot has answered, the way expwtState::theme() decides it: an admin4 page
       carries the preference admin_color_mode itself (html[data-a4-pref-admin-color-mode], written by the server
       for this user); elsewhere the answer the server gave this device last time. light, dark or system. */
    firstTheme() {
      var html = document.documentElement;
      var mode = html.hasAttribute('data-a4-pref-admin-color-mode') ? html.getAttribute('data-a4-pref-admin-color-mode') : store(THEME);
      return mode === 'light' || mode === 'dark' ? mode : 'system';
    }

    applyState() {
      this.setAttribute('data-side', this.state.side);
      this.setAttribute('data-open', this.state.open ? 'true' : 'false');
      this.setAttribute('data-overlay', this.overlay() ? 'true' : 'false');
      this.tab.setAttribute('aria-expanded', this.state.open ? 'true' : 'false');
      this.panel.hidden = !this.state.open;
      this.resizer.setAttribute('aria-valuemin', this.cfg.minWidth || 300);
      this.resizer.setAttribute('aria-valuemax', this.cfg.maxWidth || 720);
      this.resizer.setAttribute('aria-valuenow', this.state.width);
      this.applyTheme();
      this.place();
    }

    save() {
      var self = this;
      clearTimeout(this.saveTimer);
      this.saveTimer = setTimeout(function () {
        if (self.deviceOnly) {
          store(DEVICE, { only: true, state: self.state });
          return;
        }
        store(CACHE, self.state);
        if (self.boot) {
          self.call('state.save', { state: self.state }).catch(function () {});
        }
      }, 400);
    }

    setState(change, keep) {
      Object.assign(this.state, change);
      this.state = this.normalize(this.state);
      this.applyState();
      if (!keep) {
        this.save();
      }
    }

    toggle(open, focusTab) {
      open = open === undefined ? !this.state.open : !!open;
      this.setState({ open: open });
      if (open) {
        this.show(this.state.panel);
        this.heading.focus();
      } else if (focusTab) {
        this.tab.focus();
      }
    }

    /* ------------------------------------------------------------------ drag and keyboard */

    dragStart(e) {
      if (e.button !== 0) { return; }
      var self = this;
      var startY = e.clientY;
      var startOffset = this.state.offset;
      var moved = false;
      this.dragged = false;
      this.tab.setPointerCapture(e.pointerId);
      var move = function (ev) {
        if (!moved && Math.abs(ev.clientY - startY) < 6 && Math.abs(ev.clientX - e.clientX) < 6) { return; }
        moved = true;
        self.tab.setAttribute('data-dragging', 'true');
        var side = ev.clientX < window.innerWidth / 2 ? 'left' : 'right';
        self.setState({ offset: startOffset + (ev.clientY - startY), side: side }, true);
      };
      var up = function () {
        self.tab.removeEventListener('pointermove', move);
        self.tab.removeEventListener('pointerup', up);
        self.tab.removeEventListener('pointercancel', up);
        self.tab.removeAttribute('data-dragging');
        if (moved) {
          self.dragged = true;
          self.save();
          self.say(self.t('The sidebar was moved.'));
        }
      };
      this.tab.addEventListener('pointermove', move);
      this.tab.addEventListener('pointerup', up);
      this.tab.addEventListener('pointercancel', up);
    }

    tabKey(e) {
      var step = e.shiftKey ? 64 : 16;
      if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
        e.preventDefault();
        this.setState({ offset: this.state.offset + (e.key === 'ArrowUp' ? -step : step) });
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
        e.preventDefault();
        this.setState({ side: e.key === 'ArrowLeft' ? 'left' : 'right' });
        this.say(e.key === 'ArrowLeft' ? this.t('The sidebar is on the left.') : this.t('The sidebar is on the right.'));
      }
    }

    resizeStart(e) {
      var self = this;
      var startX = e.clientX;
      var startW = this.state.width;
      this.resizer.setPointerCapture(e.pointerId);
      var move = function (ev) {
        var d = ev.clientX - startX;
        self.setState({ width: startW + (self.state.side === 'right' ? -d : d) }, true);
      };
      var up = function () {
        self.resizer.removeEventListener('pointermove', move);
        self.resizer.removeEventListener('pointerup', up);
        self.save();
      };
      this.resizer.addEventListener('pointermove', move);
      this.resizer.addEventListener('pointerup', up);
    }

    resizeKey(e) {
      var grow = this.state.side === 'right' ? 'ArrowLeft' : 'ArrowRight';
      var shrink = this.state.side === 'right' ? 'ArrowRight' : 'ArrowLeft';
      var w = this.state.width;
      if (e.key === grow) { w += 16; } else if (e.key === shrink) { w -= 16; } else if (e.key === 'Home') { w = this.cfg.minWidth; } else if (e.key === 'End') { w = this.cfg.maxWidth; } else { return; }
      e.preventDefault();
      this.setState({ width: w });
    }

    shortcut(e) {
      var key = String(this.cfg.key || 'E');
      if (!e.altKey || !e.shiftKey || e.ctrlKey || e.metaKey) { return; }
      if (e.code !== 'Key' + key && e.code !== 'Digit' + key) { return; }
      e.preventDefault();
      if (this.state.open) {
        this.heading.focus();
      } else {
        this.toggle(true);
      }
    }

    say(text, kind) {
      this.status.textContent = text || '';
      if (kind) {
        this.status.setAttribute('data-kind', kind);
      } else {
        this.status.removeAttribute('data-kind');
      }
    }

    /* ------------------------------------------------------------------ server */

    loadBoot() {
      var self = this;
      return this.request('GET', this.cfg.boot + '?action=boot&input=' + encodeURIComponent('{}'), null).then(function (body) {
        self.boot = body.data;
        store(THEME, self.boot.theme || null);
        var device = store(DEVICE);
        if (!(device && device.only)) {
          self.state = self.normalize(Object.assign({}, self.boot.state, { open: self.state.open }));
          store(CACHE, self.state);
        }
        self.buildTabs();
        self.applyState();
        if (self.state.open) {
          self.show(self.state.panel);
        }
        return self.boot;
      }).catch(function () {
        self.say(self.t('The sidebar could not reach the server.'), 'bad');
      });
    }

    request(method, url, body, headers) {
      var init = { method: method, credentials: 'same-origin', headers: Object.assign({ 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, headers || {}) };
      if (body !== null) {
        init.body = body;
      }
      return fetch(url, init).then(function (res) {
        return res.text().then(function (text) {
          var data = null;
          try { data = JSON.parse(text); } catch (e) { data = null; }
          if (!res.ok || !data || data.ok === false) {
            var err = new Error(data && data.message ? data.message : 'HTTP ' + res.status);
            err.status = res.status;
            err.body = data || {};
            throw err;
          }
          return data;
        });
      });
    }

    /* An action through the installation's data layer; "Verify it's you" first when the administration would ask. */
    call(action, input, retried) {
      var self = this;
      var t = this.boot.transport;
      var write = ['me', 'page.load', 'create.classes', 'tree.children', 'boot'].indexOf(action) < 0;
      var json = JSON.stringify(input || {});
      var headers = { 'X-CSRF-Token': this.boot.token || '' };
      var p;
      if (t.layer === 'graphql') {
        p = fetch(t.graphql, {
          method: 'POST', credentials: 'same-origin',
          headers: Object.assign({ 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }, headers),
          body: JSON.stringify({ id: write ? 'expwt-write' : 'expwt-read', variables: { action: action, input: json } })
        }).then(function (res) {
          return res.json().then(function (out) {
            if (out && out.error === 'reauth_required') {
              var e403 = new Error('reauth');
              e403.status = 403;
              e403.body = out;
              throw e403;
            }
            var a = out && out.data ? out.data[write ? 'expwtWrite' : 'expwtRead'] : null;
            if (!a) {
              var eg = new Error(out && out.errors && out.errors[0] ? out.errors[0].message : 'GraphQL');
              eg.status = res.status;
              eg.body = {};
              throw eg;
            }
            if (!a.ok) {
              var ea = new Error(a.message || a.error);
              ea.status = a.status;
              ea.body = { error: a.error, message: a.message, details: a.details ? JSON.parse(a.details) : null };
              throw ea;
            }
            return { ok: true, data: a.data ? JSON.parse(a.data) : null };
          });
        });
      } else if (write) {
        var form = new URLSearchParams();
        form.set('action', action);
        form.set('input', json);
        form.set('ezxform_token', this.boot.token || '');
        p = this.request('POST', t.rest, form.toString(), Object.assign({ 'Content-Type': 'application/x-www-form-urlencoded' }, headers));
      } else {
        p = this.request('GET', t.rest + '?action=' + encodeURIComponent(action) + '&input=' + encodeURIComponent(json), null);
      }
      return p.then(function (body) { return body.data; }, function (err) {
        if (!retried && err.status === 403 && err.body && err.body.error === 'reauth_required') {
          return self.reauth(action).then(function () { return self.call(action, input, true); });
        }
        throw err;
      });
    }

    reauth(action) {
      var self = this;
      this.say(this.t('Please confirm it is you in the new window.'));
      return new Promise(function (resolve, reject) {
        var win = window.open(self.boot.transport.reauth + '/' + encodeURIComponent(action), 'expwt-reauth', 'width=560,height=680');
        if (!win) {
          reject(new Error(self.t('Allow this site to open a window to confirm it is you.')));
          return;
        }
        self.reauthDone = function () { clearInterval(timer); self.reauthDone = null; resolve(); };
        var timer = setInterval(function () {
          if (win.closed) {
            clearInterval(timer);
            if (self.reauthDone) {
              self.reauthDone = null;
              resolve();
            }
          }
        }, 500);
      });
    }

    message(e) {
      if (e.origin === window.location.origin && e.data && e.data.expwt === 'reauth-done' && this.reauthDone) {
        this.reauthDone();
      }
    }

    /* ------------------------------------------------------------------ panels */

    show(id) {
      var self = this;
      if (this.state.panel !== id) {
        this.setState({ panel: id });
      }
      (this.tabButtons || []).forEach(function (b) {
        var on = b.getAttribute('data-panel') === id;
        b.setAttribute('aria-selected', on ? 'true' : 'false');
        b.setAttribute('tabindex', on ? '0' : '-1');
      });
      this.body.setAttribute('aria-labelledby', 'wt-tab-' + id);
      if (id === 'welcome') {
        this.welcome();
        return;
      }
      if (!this.boot) {
        this.body.textContent = '';
        this.body.appendChild(h('p', { class: 'wt-muted', text: this.t('Loading ...') }));
        return;
      }
      this.loadPanels().then(function (registry) {
        self.body.textContent = '';
        if (registry[id]) {
          registry[id](self.context(), self.body);
        }
      }).catch(function () {
        self.say(self.t('The sidebar could not reach the server.'), 'bad');
      });
    }

    loadPanels() {
      var self = this;
      if (window.ExpwtPanels) {
        return Promise.resolve(window.ExpwtPanels);
      }
      if (!this.panelsPromise) {
        this.panelsPromise = new Promise(function (resolve, reject) {
          var s = document.createElement('script');
          s.src = self.cfg.panels;
          s.async = true;
          s.onload = function () { resolve(window.ExpwtPanels || {}); };
          s.onerror = reject;
          document.head.appendChild(s);
        });
      }
      return this.panelsPromise;
    }

    /* What a panel gets: the server, the strings, the state, the page, and the shared helpers. */
    context() {
      var self = this;
      return {
        h: h,
        t: function (s) { return self.t(s); },
        call: function (action, input) { return self.call(action, input); },
        say: function (text, kind) { self.say(text, kind); },
        boot: this.boot,
        node: this.cfg.node || 0,
        state: this.state,
        setState: function (change) { self.setState(change); },
        deviceOnly: function (on) {
          if (on === undefined) { return self.deviceOnly; }
          self.deviceOnly = !!on;
          store(DEVICE, on ? { only: true, state: self.state } : null);
          self.save();
          return self.deviceOnly;
        },
        show: function (id) { self.show(id); },
        host: this,
        inlineCss: function (css) { self.inlineRules = css || ''; self.push(); }
      };
    }

    /* The panel of a visitor who is not signed in (decision 7): sign in, register, search, light or dark. */
    welcome() {
      var self = this;
      var c = this.cfg;
      var b = this.body;
      b.textContent = '';
      b.appendChild(h('div', { class: 'wt-section' }, [
        h('p', { text: this.t('Sign in to edit this website.') }),
        h('div', { class: 'wt-row' }, [
          h('a', { class: 'wt-btn is-primary', href: c.login + '?RedirectURI=' + encodeURIComponent(window.location.pathname), text: this.t('Sign in') }),
          h('a', { class: 'wt-btn', href: c.register, text: this.t('Register') })
        ])
      ]));
      var q = h('input', { type: 'search', name: 'SearchText', id: 'wt-search', autocomplete: 'off' });
      b.appendChild(h('form', { class: 'wt-section', role: 'search', method: 'get', action: c.search }, [
        h('label', { class: 'wt-field', for: 'wt-search' }, [h('span', { text: this.t('Search') }), q]),
        h('button', { type: 'submit', class: 'wt-btn', text: this.t('Search') })
      ]));
      b.appendChild(this.themeChooser());
    }

    themeChooser() {
      var self = this;
      var group = h('fieldset', { class: 'wt-section' }, [h('legend', { text: this.t('Light or dark') })]);
      /* auto follows the site's own switch where the design has one (siteMode), the system's elsewhere */
      var auto = document.documentElement.hasAttribute('data-exp-theme') ? this.t('Like the site') : this.t('Like the system');
      [['auto', auto],['light', this.t('Light')], ['dark', this.t('Dark')]].forEach(function (o) {
        var id = 'wt-theme-' + o[0];
        group.appendChild(h('label', { class: 'wt-check', for: id }, [
          h('input', { type: 'radio', name: 'wt-theme', id: id, value: o[0], checked: self.state.theme === o[0],
            onchange: function () { self.setState({ theme: o[0] }); } }),
          o[1]
        ]));
      });
      return group;
    }
  }

  window.customElements.define('exp-wt', ExpWt);
}());
