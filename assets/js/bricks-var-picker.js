/**
 * Variable Picker for the Bricks builder.
 *
 * Adapted from Bricks Boost (James Lively) with permission. Right-click a
 * text input in the Bricks panel to pick a variable for it. The input type
 * (color, typography, spacing, border radius, grid) is detected from the
 * control, and the matching variable category comes from
 * includes/bricks-builder-tools.php (imdgVarPicker).
 */
(function () {
  'use strict';

  if (typeof imdgVarPicker === 'undefined') return;

  var data = imdgVarPicker;
  var floatTooltip = null;

  // ── Input type detection (from Bricks Boost) ────────────────────────────
  // Also returns types that have no mapping here (sizing, aspect-ratio)
  // so width/height/ratio inputs are not mistaken for typography.

  function detectInputType(el) {
    var ctrl = el.closest('[data-controlkey]');
    if (ctrl) {
      var key = (ctrl.getAttribute('data-controlkey') || '').toLowerCase();
      if (['_top', '_right', '_bottom', '_left', 'top', 'right', 'bottom', 'left'].indexOf(key) !== -1) {
        return 'spacing';
      }
    }

    var id = (el.id || '').toLowerCase();
    if (id) {
      if (id.indexOf('color') !== -1 || id.indexOf('background') !== -1) return 'color';
      if (id.indexOf('margin') !== -1 || id.indexOf('padding') !== -1 || id.indexOf('gap') !== -1) return 'spacing';
      if (id.indexOf('radius') !== -1) return 'border-radius';
      if (id.indexOf('aspect') !== -1 || id.indexOf('objectposition') !== -1 || id.indexOf('object-position') !== -1) return 'aspect-ratio';
      if (id.indexOf('width') !== -1 || id.indexOf('height') !== -1) return 'sizing';
      if (id.indexOf('font-size') !== -1 || id.indexOf('line-height') !== -1 || id.indexOf('letter-spacing') !== -1) return 'typography';
      if (id.indexOf('grid-template') !== -1 || id.indexOf('gridtemplate') !== -1) return 'grid';
    }

    var node = el;
    var allClasses = '';
    var allKeys = '';
    var nearestLabel = '';
    var sectionTitle = '';

    for (var i = 0; i < 6; i++) {
      if (!node || node.id === 'bricks-panel') break;
      if (node.className && typeof node.className === 'string') allClasses += ' ' + node.className;
      var dk = node.getAttribute && (node.getAttribute('data-controlkey') || node.getAttribute('data-control') || '');
      if (dk) allKeys += ' ' + dk;
      if (!nearestLabel && node !== el) {
        var lbl = node.querySelector('label, .label, .control-label');
        if (lbl) nearestLabel = lbl.textContent.toLowerCase();
      }
      if (!sectionTitle) {
        var title = node.querySelector('.control-group-title, .group-title, h3');
        if (title) sectionTitle = title.textContent.toLowerCase();
      }
      node = node.parentElement;
    }

    if (!sectionTitle) {
      var wrap = el.closest('.control-wrapper, [data-control], [data-controlkey]');
      var sib = wrap ? wrap.previousElementSibling : null;
      for (var n = 0; sib && n < 10; n++) {
        var txt = sib.textContent.toLowerCase().trim();
        if ((sib.classList && (sib.classList.contains('separator') || sib.classList.contains('control-group-title'))) ||
            sib.tagName === 'H3' || sib.tagName === 'H4' ||
            ['spacing', 'sizing', 'typography', 'border'].indexOf(txt) !== -1) {
          sectionTitle = txt;
          break;
        }
        sib = sib.previousElementSibling;
      }
    }

    var context = (allClasses + ' ' + allKeys + ' ' + nearestLabel + ' ' + sectionTitle).toLowerCase();
    allKeys = allKeys.toLowerCase();

    if (context.indexOf('color') !== -1 || context.indexOf('background') !== -1 ||
        el.type === 'color' || el.closest('.color, [class*="color"]')) return 'color';
    if (context.indexOf('margin') !== -1 || context.indexOf('padding') !== -1 ||
        context.indexOf('gap') !== -1 || context.indexOf('spacing') !== -1) return 'spacing';
    if (context.indexOf('radius') !== -1) return 'border-radius';
    if (context.indexOf('aspect') !== -1 || nearestLabel.indexOf('aspect') !== -1) return 'aspect-ratio';
    if (nearestLabel.indexOf('width') !== -1 || nearestLabel.indexOf('height') !== -1 ||
        allKeys.indexOf('width') !== -1 || allKeys.indexOf('height') !== -1 ||
        sectionTitle.indexOf('sizing') !== -1) return 'sizing';
    if (nearestLabel.indexOf('font') !== -1 || nearestLabel.indexOf('line') !== -1 ||
        nearestLabel.indexOf('letter') !== -1 || nearestLabel.indexOf('size') !== -1 ||
        context.indexOf('typography') !== -1) return 'typography';
    if (context.indexOf('grid-template') !== -1 || context.indexOf('gridtemplate') !== -1 ||
        nearestLabel.indexOf('columns') !== -1 || nearestLabel.indexOf('grid') !== -1) return 'grid';
    if (sectionTitle.indexOf('border') !== -1) return 'border-radius';

    return null;
  }

  // ── Helpers ──────────────────────────────────────────────────────────────

  function setValue(input, value) {
    var setter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
    setter.call(input, value);
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
    input.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
  }

  function attachTooltip(el, text) {
    el.addEventListener('mouseenter', function () {
      if (!floatTooltip) {
        floatTooltip = document.createElement('div');
        floatTooltip.className = 'brxbo-vp-float-tooltip';
        document.body.appendChild(floatTooltip);
      }
      floatTooltip.textContent = text;
      floatTooltip.style.display = 'block';
      var r = el.getBoundingClientRect();
      var top = r.top - floatTooltip.offsetHeight - 6;
      var left = r.left + r.width / 2 - floatTooltip.offsetWidth / 2;
      left = Math.max(4, Math.min(left, window.innerWidth - floatTooltip.offsetWidth - 4));
      if (top < 4) top = r.bottom + 6;
      floatTooltip.style.top = top + 'px';
      floatTooltip.style.left = left + 'px';
    });
    el.addEventListener('mouseleave', function () {
      if (floatTooltip) floatTooltip.style.display = 'none';
    });
  }

  function groupBy(items, key) {
    var groups = {};
    var order = [];
    items.forEach(function (it) {
      var k = it[key] || 'other';
      if (!groups[k]) { groups[k] = []; order.push(k); }
      groups[k].push(it);
    });
    return { groups: groups, order: order };
  }

  // ── Popup ────────────────────────────────────────────────────────────────

  function closePopup() {
    document.querySelectorAll('.brxbo-vp-popup').forEach(function (el) { el.remove(); });
    document.removeEventListener('mousedown', onOutsideClick);
    document.removeEventListener('keydown', onKeydown);
    if (floatTooltip) floatTooltip.style.display = 'none';
  }

  function onOutsideClick(e) {
    if (!e.target.closest('.brxbo-vp-popup')) closePopup();
  }

  function onKeydown(e) {
    if (e.key === 'Escape') closePopup();
  }

  function showPopup(x, y, set, input) {
    closePopup();

    var useGrid = set.layout === 'grid';
    var current = (input.value || '').trim();

    var popup = document.createElement('div');
    popup.className = 'brxbo-vp-popup' + (useGrid ? ' brxbo-vp-popup-grid' : '');

    var header = document.createElement('div');
    header.className = 'brxbo-vp-header';
    var title = document.createElement('span');
    title.className = 'brxbo-vp-title';
    title.textContent = set.title;
    var close = document.createElement('button');
    close.className = 'brxbo-vp-close';
    close.type = 'button';
    close.innerHTML = '&times;';
    close.addEventListener('click', closePopup);
    header.appendChild(title);
    header.appendChild(close);
    popup.appendChild(header);

    var search = document.createElement('input');
    search.className = 'brxbo-vp-search';
    search.type = 'text';
    search.placeholder = 'Search variables...';
    popup.appendChild(search);

    var list = document.createElement('ul');
    list.className = useGrid ? 'brxbo-vp-grid' : 'brxbo-vp-list';
    popup.appendChild(list);

    function pick(item) {
      setValue(input, item.insert);
      closePopup();
    }

    function swatch(item, extraClass) {
      var s = document.createElement('span');
      s.className = 'brxbo-vp-grid-item' + (extraClass ? ' ' + extraClass : '');
      if (current === item.insert) s.classList.add('is-current');
      s.style.background = item.swatch || '#333';
      attachTooltip(s, item.label);
      s.addEventListener('click', function () { pick(item); });
      return s;
    }

    function render(filter) {
      list.innerHTML = '';
      var items = set.items;
      if (filter) {
        items = items.filter(function (it) {
          return it.label.toLowerCase().indexOf(filter) !== -1 || String(it.group).toLowerCase().indexOf(filter) !== -1;
        });
      }
      if (!items.length) {
        list.innerHTML = '<li class="brxbo-vp-empty">No variables found</li>';
        return;
      }

      var grouped = groupBy(items, 'group');
      grouped.order.forEach(function (groupName) {
        var vars = grouped.groups[groupName];
        var gh = document.createElement('li');
        gh.className = 'brxbo-vp-group-header';
        var label = document.createElement('span');
        label.textContent = groupName;
        gh.appendChild(label);
        list.appendChild(gh);

        if (useGrid) {
          // Base colors sit in the group header; each shade gets its own row.
          var shades = groupBy(vars, 'shade');
          shades.order.forEach(function (shade) {
            if (shade === 'base') {
              shades.groups.base.forEach(function (it) { gh.appendChild(swatch(it, 'brxbo-vp-header-swatch')); });
              return;
            }
            var row = document.createElement('li');
            row.className = 'brxbo-vp-grid-wrap';
            var inner = document.createElement('div');
            inner.className = 'brxbo-vp-grid-row';
            var sl = document.createElement('span');
            sl.className = 'brxbo-vp-shade-label';
            sl.textContent = shade.toUpperCase();
            inner.appendChild(sl);
            shades.groups[shade].forEach(function (it) { inner.appendChild(swatch(it)); });
            row.appendChild(inner);
            list.appendChild(row);
          });
        } else {
          vars.forEach(function (it) {
            var li = document.createElement('li');
            li.className = 'brxbo-vp-item';
            if (current === it.insert) li.classList.add('is-current');
            if (it.swatch) {
              var p = document.createElement('span');
              p.className = 'brxbo-vp-item-preview';
              p.style.background = it.swatch;
              li.appendChild(p);
            }
            var nm = document.createElement('span');
            nm.className = 'brxbo-vp-item-name';
            nm.textContent = it.label;
            li.appendChild(nm);
            li.addEventListener('click', function () { pick(it); });
            list.appendChild(li);
          });
        }
      });
    }

    render();
    search.addEventListener('input', function () { render(this.value.toLowerCase()); });

    popup.style.left = x + 'px';
    popup.style.top = y + 'px';
    document.body.appendChild(popup);

    var rect = popup.getBoundingClientRect();
    if (rect.right > window.innerWidth) popup.style.left = (window.innerWidth - rect.width - 10) + 'px';
    if (rect.bottom > window.innerHeight) popup.style.top = Math.max(10, window.innerHeight - rect.height - 10) + 'px';

    setTimeout(function () { search.focus(); }, 50);
    setTimeout(function () {
      document.addEventListener('mousedown', onOutsideClick);
      document.addEventListener('keydown', onKeydown);
    }, 100);
  }

  // ── Right-click handler ─────────────────────────────────────────────────

  function onContextMenu(e) {
    var target = e.target;
    if (!target.closest('#bricks-panel, .bricks-panel')) return;
    if (target.tagName !== 'INPUT' || (target.type !== 'text' && target.type !== 'number')) return;

    // Leave the element's own text-content control alone.
    var ctrl = target.closest('[data-controlkey]');
    if (ctrl && ctrl.getAttribute('data-controlkey') === 'text') return;

    var type = detectInputType(target);
    if (!type || !data[type]) return;

    e.preventDefault();
    showPopup(e.clientX, e.clientY, data[type], target);
  }

  function init() {
    document.addEventListener('contextmenu', onContextMenu);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 1500); });
  } else {
    setTimeout(init, 1500);
  }
})();
