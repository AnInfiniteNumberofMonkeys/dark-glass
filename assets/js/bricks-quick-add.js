/**
 * Quick Add Bar for the Bricks builder.
 *
 * Adapted from Bricks Boost (James Lively) with permission. Draws a vertical
 * bar of element icons; clicking one inserts that element the same way a
 * click on its tile in the Bricks Elements panel does. Element list comes
 * from includes/bricks-builder-tools.php (imdgQuickAdd).
 */
(function () {
  'use strict';

  if (typeof imdgQuickAdd === 'undefined' || !imdgQuickAdd.length) return;

  var BAR_WIDTH = '44px';
  var TILE_SELECTOR = '#bricks-panel-elements-categories .bricks-add-element, #bricks-panel-elements .bricks-add-element';

  function createBar() {
    // --brxbo-bar-width is read by bricks-editor.css for the docked layout.
    document.documentElement.style.setProperty('--brxbo-bar-width', BAR_WIDTH);

    var bar = document.createElement('div');
    bar.id = 'bricks-element-shortcuts-bar';
    bar.setAttribute('data-icon-size', 'medium');

    var tooltip = document.createElement('div');
    tooltip.className = 'brxbo-bar-tooltip';
    tooltip.style.display = 'none';
    document.body.appendChild(tooltip);

    imdgQuickAdd.forEach(function (el) {
      if (el.name === 'separator') {
        var sep = document.createElement('div');
        sep.className = 'brxbo-bar-separator';
        bar.appendChild(sep);
        return;
      }

      var item = document.createElement('div');
      item.className = 'brxbo-bar-item';
      item.setAttribute('data-element-name', el.name);
      item.innerHTML = '<i class="' + el.icon + '"></i>';

      item.addEventListener('mouseenter', function () {
        var rect = item.getBoundingClientRect();
        tooltip.textContent = el.label;
        tooltip.style.display = 'block';
        tooltip.style.top = (rect.top + rect.height / 2 - tooltip.offsetHeight / 2) + 'px';
        tooltip.style.right = (window.innerWidth - rect.left + 6) + 'px';
      });
      item.addEventListener('mouseleave', function () {
        tooltip.style.display = 'none';
      });
      item.addEventListener('click', function () {
        tooltip.style.display = 'none';
        addElement(el.name);
      });

      bar.appendChild(item);
    });

    document.body.appendChild(bar);
  }

  // Open the Elements panel if needed, wait for its tiles, then click the tile.
  function addElement(name) {
    var panel = document.getElementById('bricks-panel-elements');
    if (!panel || panel.offsetParent === null) {
      document.querySelectorAll('#bricks-panel-header li, #bricks-panel-tabs li').forEach(function (tab) {
        if (tab.textContent.trim().toLowerCase() === 'elements' || tab.getAttribute('data-tab') === 'elements') {
          tab.click();
        }
      });
    }
    whenTilesReady(function () { clickTile(name); }, 2000);
  }

  function whenTilesReady(callback, timeoutMs) {
    if (document.querySelector(TILE_SELECTOR)) {
      setTimeout(callback, 0);
      return;
    }
    var done = false;
    var observer = new MutationObserver(function () {
      if (!done && document.querySelector(TILE_SELECTOR)) {
        done = true;
        observer.disconnect();
        callback();
      }
    });
    observer.observe(document.body, { childList: true, subtree: true });
    setTimeout(function () {
      if (done) return;
      done = true;
      observer.disconnect();
      console.warn('IMDG Quick Add: Elements panel did not open');
    }, timeoutMs);
  }

  function clickTile(name) {
    var tiles = document.querySelectorAll(TILE_SELECTOR);
    for (var i = 0; i < tiles.length; i++) {
      if (tiles[i].getAttribute('data-element-name') === name) {
        tiles[i].click();
        return;
      }
    }
    console.warn('IMDG Quick Add: no Elements panel tile for "' + name + '"');
  }

  // Hide the bar in preview mode and give its room back.
  function watchPreviewMode(attempt) {
    var toolbar = document.querySelector('#bricks-toolbar, .bricks-toolbar');
    if (!toolbar) {
      attempt = attempt || 0;
      if (attempt < 60) setTimeout(function () { watchPreviewMode(attempt + 1); }, 500);
      return;
    }
    new MutationObserver(function () {
      var bar = document.getElementById('bricks-element-shortcuts-bar');
      if (!bar) return;
      var previewing = toolbar.classList.contains('is-previewing');
      bar.classList.toggle('brxbo-hidden', previewing);
      document.documentElement.style.setProperty('--brxbo-bar-width', previewing ? '0px' : BAR_WIDTH);
    }).observe(toolbar, { attributes: true, attributeFilter: ['class'] });
  }

  function init() {
    if (!document.getElementById('bricks-builder-iframe') && !document.querySelector('.bricks-panel')) return;
    createBar();
    watchPreviewMode();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { setTimeout(init, 1000); });
  } else {
    setTimeout(init, 1000);
  }
})();
