<?php
/**
 * Selection behavior for the orders listing.
 *
 * The checkbox column and the action row are rendered by zcObserverBatchOrderSelect.
 * This script selects ranges, keeps the count, and copies the checked order ids
 * into whichever plugin form was submitted. It does not insert markup.
 *
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$labels = [
    'none' => defined('TEXT_BATCH_ORDER_SELECT_NONE') ? TEXT_BATCH_ORDER_SELECT_NONE : 'Select at least one order.',
    'selected' => defined('TEXT_BATCH_ORDER_SELECT_SELECTED') ? TEXT_BATCH_ORDER_SELECT_SELECTED : '%s selected',
];
$jsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
?>
<script>
(function () {
  var cfg = <?= json_encode(['labels' => $labels], $jsonFlags) ?>;

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  ready(function () {
    var table = document.getElementById('orders-table');
    var bar = document.querySelector('.zc-bos-bar');
    if (!table || !bar || bar.getAttribute('data-zc-bos') === '1') {
      return;
    }
    bar.setAttribute('data-zc-bos', '1');

    var actions = bar.querySelector('.zc-bos-actions');
    var selectAll = table.querySelector('thead .zc-bos-all');
    var count = bar.querySelector('.zc-bos-count');
    var lastIndex = null;

    function boxes() {
      return Array.prototype.slice.call(table.querySelectorAll('tbody .zc-bos-check'));
    }

    function selectedIds() {
      var ids = [];
      boxes().forEach(function (box) {
        if (box.checked) {
          ids.push(box.value);
        }
      });
      return ids;
    }

    function submitControls() {
      if (!actions) {
        return [];
      }
      return Array.prototype.slice.call(actions.querySelectorAll('button[type="submit"], input[type="submit"]'));
    }

    function sync() {
      var all = boxes();
      var chosen = selectedIds().length;
      var total = all.length;
      if (selectAll) {
        selectAll.checked = total > 0 && chosen === total;
        selectAll.indeterminate = chosen > 0 && chosen < total;
      }
      submitControls().forEach(function (control) {
        control.disabled = chosen === 0;
      });
      if (count) {
        count.textContent = cfg.labels.selected.replace('%s', String(chosen));
      }
    }

    boxes().forEach(function (box) {
      box.addEventListener('click', function (event) {
        event.stopPropagation();
        var all = boxes();
        var index = all.indexOf(box);
        if (event.shiftKey && lastIndex !== null && index !== -1) {
          var from = Math.min(lastIndex, index);
          var to = Math.max(lastIndex, index);
          for (var i = from; i <= to; i++) {
            all[i].checked = box.checked;
          }
        }
        if (index !== -1) {
          lastIndex = index;
        }
        sync();
      });
    });

    if (selectAll) {
      selectAll.addEventListener('click', function (event) {
        event.stopPropagation();
        var checked = selectAll.checked;
        boxes().forEach(function (box) {
          box.checked = checked;
        });
        selectAll.indeterminate = false;
        sync();
      });
    }

    if (actions) {
      actions.addEventListener('submit', function (event) {
        var form = event.target;
        if (!form || String(form.tagName).toLowerCase() !== 'form') {
          return;
        }
        var ids = selectedIds();
        if (!ids.length) {
          event.preventDefault();
          window.alert(cfg.labels.none);
          return;
        }
        var field = form.getAttribute('data-zc-bos-field') || 'batch_order_ids[]';
        var slot = form.querySelector('.zc-bos-ids');
        if (!slot) {
          slot = document.createElement('span');
          slot.className = 'zc-bos-ids';
          form.insertBefore(slot, form.firstChild);
        }
        while (slot.firstChild) {
          slot.removeChild(slot.firstChild);
        }
        ids.forEach(function (id) {
          var input = document.createElement('input');
          input.type = 'hidden';
          input.name = field;
          input.value = id;
          slot.appendChild(input);
        });
      });
    }

    sync();
  });
})();
</script>
