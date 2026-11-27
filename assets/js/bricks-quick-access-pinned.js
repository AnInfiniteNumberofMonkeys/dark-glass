/**
 * Quick Access -> Pinned panel switcher for the Bricks builder.
 *
 * For a pinned-section workflow (Content tab on top, style group(s) pinned
 * at the bottom of the element panel):
 *
 *  1. Clicking a Quick Access icon (#bricks-panel-element-quick-access)
 *     replaces the pinned group with the clicked one instead of switching
 *     the panel to the Style tab. The Content tab stays in view above.
 *  2. The Content shortcut is hidden (bricks-quick-access-pinned.css).
 *  3. An "All" icon is appended to the bar. It pins every style group of
 *     the selected element in the pinned area, shown like the Style tab:
 *     a collapsed list of group titles, one group opening on click.
 *
 * Works through Bricks' own reactive state ($_state.pinnedControlGroups).
 * Changing that state is session-only: Bricks persists pins only through
 * the Builder UI profile, so a reload restores the saved default pin.
 *
 * Only active while at least one group is pinned. With nothing pinned,
 * the bar behaves exactly as stock Bricks.
 */
(function () {
  'use strict';

  var QA_ID = 'bricks-panel-element-quick-access';
  var PANEL_ID = 'bricks-panel-element';
  var ALL_CLASS = 'imdg-qa-all';
  var ACTIVE_CLASS = 'imdg-qa-active';
  var MODE_CLASS = 'imdg-qa-pinned-mode';
  var COLLAPSE_CLASS = 'imdg-pinned-collapsed';
  var OPEN_CLASS = 'imdg-open';

  var state = null;
  var qaInstance = null;
  var mode = null;          // null | 'single' | 'all'
  var current = null;       // pinned group key in 'single' mode
  var allForElement = null; // element name the 'all' set was built for
  var lastActiveId = null;  // selected element id at the last refresh
  var openGroup = null;     // expanded group in the collapsed 'all' list

  function getHost() {
    return document.querySelector('.brx-body');
  }

  function getState() {
    if (state) return state;
    var host = getHost();
    if (host && host.__vue_app__) {
      state = host.__vue_app__.config.globalProperties.$_state || null;
    }
    return state;
  }

  // Locate the Vue component that renders the Quick Access bar, so the
  // group key for each icon comes from Bricks (labels are translated).
  function getQaInstance(target) {
    if (qaInstance && !qaInstance.isUnmounted && qaInstance.subTree && qaInstance.subTree.el === target) {
      return qaInstance;
    }
    qaInstance = null;
    var host = getHost();
    if (!host || !host._vnode) return null;
    var stack = [host._vnode];
    var n = 0;
    while (stack.length && n < 200000) {
      var vn = stack.pop();
      n++;
      if (!vn || typeof vn !== 'object') continue;
      if (vn.component) {
        if (vn.component.subTree && vn.component.subTree.el === target) {
          qaInstance = vn.component;
          return qaInstance;
        }
        stack.push(vn.component.subTree);
        continue;
      }
      if (vn.suspense) stack.push(vn.suspense.activeBranch);
      if (Array.isArray(vn.children)) {
        for (var i = vn.children.length - 1; i >= 0; i--) stack.push(vn.children[i]);
      }
    }
    return null;
  }

  function getShortcuts(qa) {
    var inst = getQaInstance(qa);
    var list = inst && inst.proxy && inst.proxy.shortcuts;
    return Array.isArray(list) ? list : [];
  }

  // Bricks' own <li> items, in render order (excludes the injected "All").
  function nativeItems(ul) {
    return Array.prototype.filter.call(ul.children, function (el) {
      return el.tagName === 'LI' && !el.classList.contains(ALL_CLASS);
    });
  }

  function groupForLi(li) {
    var qa = document.getElementById(QA_ID);
    var ul = li.parentNode;
    if (!qa || !ul) return null;
    var idx = nativeItems(ul).indexOf(li);
    var item = getShortcuts(qa)[idx];
    return item ? item.group : null;
  }

  // Every style-tab group for the selected element, in Style tab order.
  function allGroups() {
    var st = getState();
    var name = st && st.activeElement && st.activeElement.name;
    var def = name && window.bricksData && bricksData.elements && bricksData.elements[name];
    var groups = [];
    if (def && def.controlGroups) {
      Object.keys(def.controlGroups).forEach(function (key) {
        if (def.controlGroups[key] && def.controlGroups[key].tab === 'style') groups.push(key);
      });
    }
    if (!groups.length) {
      var qa = document.getElementById(QA_ID);
      if (qa) {
        getShortcuts(qa).forEach(function (s) {
          if (s.group && s.group !== 'content') groups.push(s.group);
        });
      }
    }
    return groups;
  }

  function isPinnedMode() {
    var st = getState();
    return !!(st && Array.isArray(st.pinnedControlGroups) && st.pinnedControlGroups.length);
  }

  function samePins(a, b) {
    if (!Array.isArray(a) || a.length !== b.length) return false;
    for (var i = 0; i < a.length; i++) if (a[i] !== b[i]) return false;
    return true;
  }

  function applyPins(groups) {
    var st = getState();
    if (!st || !groups.length) return;
    st.activePanelTab = 'content';
    st.showHideElement = false;
    st.showConditions = false;
    st.showInteractions = false;
    st.elementQuickAccessIndex = -1;
    if (!samePins(st.pinnedControlGroups, groups)) st.pinnedControlGroups = groups.slice();
    holdPinnedAtTop();
    requestAnimationFrame(refresh);
  }

  // Some controls in the lower groups scroll themselves into view a few
  // hundred ms after mounting, which drops the pinned area near its end.
  // Keep it at the top for a short window unless the user scrolls it.
  var holdUntil = 0;
  var holdBox = null;
  function onHoldScroll() {
    if (Date.now() > holdUntil) return releaseHold();
    if (holdBox && holdBox.scrollTop !== 0) holdBox.scrollTop = 0;
  }
  function releaseHold() {
    holdUntil = 0;
    if (holdBox) holdBox.removeEventListener('scroll', onHoldScroll);
    holdBox = null;
    ['wheel', 'pointerdown', 'keydown', 'touchstart'].forEach(function (t) {
      window.removeEventListener(t, releaseHold, true);
    });
  }
  function holdPinnedAtTop() {
    releaseHold();
    holdUntil = Date.now() + 1500;
    ['wheel', 'pointerdown', 'keydown', 'touchstart'].forEach(function (t) {
      window.addEventListener(t, releaseHold, true);
    });
    var tries = 0;
    (function attach() {
      if (!holdUntil) return;
      var box = document.querySelector('#bricks-pinned-section .pinned-groups-container');
      if (!box) {
        if (++tries < 30) requestAnimationFrame(attach);
        return;
      }
      if (box !== holdBox) {
        if (holdBox) holdBox.removeEventListener('scroll', onHoldScroll);
        holdBox = box;
        box.addEventListener('scroll', onHoldScroll, { passive: true });
      }
      box.scrollTop = 0;
      if (Date.now() < holdUntil) requestAnimationFrame(attach);
      else releaseHold();
    })();
  }

  function ensureAllItem(ul) {
    var existing = ul.querySelector('li.' + ALL_CLASS);
    if (existing) {
      if (existing !== ul.lastElementChild) ul.appendChild(existing);
      return existing;
    }
    var source = null;
    nativeItems(ul).some(function (li) {
      if (li.querySelector('.bricks-svg-wrapper[data-name="box"]')) { source = li; return true; }
      return false;
    });
    if (!source) return null;
    var li = source.cloneNode(true);
    li.classList.remove('active', 'has-settings');
    li.classList.add(ALL_CLASS);
    li.setAttribute('data-balloon', 'All');
    var idx = li.querySelector('.index');
    if (idx) idx.textContent = '';
    ul.appendChild(li);
    return li;
  }

  function refresh() {
    var panel = document.getElementById(PANEL_ID);
    var qa = document.getElementById(QA_ID);
    if (!panel || !qa) return;

    var pinned = isPinnedMode();
    panel.classList.toggle(MODE_CLASS, pinned);

    var ul = qa.querySelector('ul');
    if (!ul) return;
    var allLi = ensureAllItem(ul);
    if (!pinned) return;

    var st = getState();
    var pins = st.pinnedControlGroups;

    // Bricks remounts pinned controls on element change; with several
    // groups pinned that lands the pinned area near its end.
    if (st.activeId !== lastActiveId) {
      lastActiveId = st.activeId;
      if (pins.length > 1) holdPinnedAtTop();
    }

    // 'All' follows the selected element: rebuild when the element changes.
    if (mode === 'all') {
      var name = st.activeElement && st.activeElement.name;
      if (name && name !== allForElement) {
        allForElement = name;
        var groups = allGroups();
        if (groups.length && !samePins(pins, groups)) {
          st.pinnedControlGroups = groups;
          holdPinnedAtTop();
          return; // re-render will call refresh again
        }
      }
    } else if (mode === 'single' && !(pins.length === 1 && pins[0] === current)) {
      mode = null; // pins changed through Bricks' own UI
    }

    var activeGroup = pins.length === 1 ? pins[0] : null;
    var shortcuts = getShortcuts(qa);
    nativeItems(ul).forEach(function (li, i) {
      var s = shortcuts[i];
      li.classList.toggle(ACTIVE_CLASS, !!(s && activeGroup && s.group === activeGroup));
    });
    if (allLi) allLi.classList.toggle(ACTIVE_CLASS, mode === 'all' && pins.length > 1);

    // 'All' shows the pinned groups as a collapsed list of titles, like the
    // Style tab; one group at a time opens when its title is clicked.
    var section = document.getElementById('bricks-pinned-section');
    if (section) {
      var collapsed = mode === 'all' && pins.length > 1;
      section.classList.toggle(COLLAPSE_CLASS, collapsed);
      Array.prototype.forEach.call(section.querySelectorAll('.pinned-group'), function (g) {
        g.classList.toggle(OPEN_CLASS, collapsed && g.getAttribute('data-control-group') === openGroup);
      });
    }
  }

  // Scroll an opened group's title to the top of the pinned area, and let
  // code editors that initialised while hidden re-measure themselves.
  function revealGroup(group) {
    requestAnimationFrame(function () {
      var box = document.querySelector('#bricks-pinned-section .pinned-groups-container');
      if (box) {
        // Rects are in zoomed screen px when the builder UI is scaled;
        // scrollTop is in CSS px, so convert by the box's scale.
        var rect = box.getBoundingClientRect();
        var scale = box.offsetHeight ? rect.height / box.offsetHeight : 1;
        box.scrollTop += (group.getBoundingClientRect().top - rect.top) / (scale || 1) - 6;
      }
      Array.prototype.forEach.call(group.querySelectorAll('.CodeMirror'), function (cm) {
        if (cm.CodeMirror && typeof cm.CodeMirror.refresh === 'function') cm.CodeMirror.refresh();
      });
    });
  }

  // Capture phase: runs before Bricks' own click handler on the <li>.
  document.addEventListener('click', function (e) {
    if (!e.target || !e.target.closest) return;

    // Title row in the collapsed 'all' list: open / close that group.
    var title = e.target.closest('#bricks-pinned-section.' + COLLAPSE_CLASS + ' .pinned-group-title');
    if (title) {
      if (e.target.closest('.has-setting, .inherited-from, .unpin, .pinned-group-order-actions')) return;
      var groupEl = title.parentNode;
      var key = groupEl && groupEl.getAttribute('data-control-group');
      if (!key) return;
      openGroup = openGroup === key ? null : key;
      releaseHold();
      refresh();
      if (openGroup) revealGroup(groupEl);
      return;
    }

    var li = e.target.closest('#' + QA_ID + ' li');
    if (!li || !getState() || !isPinnedMode()) return;

    var groups;
    if (li.classList.contains(ALL_CLASS)) {
      groups = allGroups();
      if (!groups.length) return;
      mode = 'all';
      current = null;
      openGroup = null;
      var st = getState();
      allForElement = st.activeElement && st.activeElement.name;
    } else {
      var group = groupForLi(li);
      if (!group || group === 'content') return;
      mode = 'single';
      current = group;
      groups = [group];
    }

    e.preventDefault();
    e.stopPropagation();
    e.stopImmediatePropagation();
    applyPins(groups);
  }, true);

  // Bricks re-renders the bar on element/panel changes; re-apply after each.
  var scheduled = false;
  function schedule() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(function () {
      scheduled = false;
      refresh();
    });
  }

  function start() {
    if (!getState()) {
      setTimeout(start, 300);
      return;
    }
    new MutationObserver(schedule).observe(document.body, { childList: true, subtree: true });
    schedule();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
