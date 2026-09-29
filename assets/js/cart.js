// Cart interactions: quantity steppers + AJAX "add to cart" (updates the
// header cart badge without a full page reload). Cart page quantity/remove
// still use plain form posts server-side, so everything keeps working even
// with JavaScript disabled — this just makes it nicer when it's on.

document.addEventListener('DOMContentLoaded', function () {
  // --- Quantity steppers (+ / - next to a number input) ---
  document.querySelectorAll('.qty-control').forEach(function (control) {
    var input = control.querySelector('input[type="number"]');
    var minus = control.querySelector('[data-step="-1"]');
    var plus = control.querySelector('[data-step="1"]');
    if (!input) return;

    var max = input.getAttribute('max') ? parseInt(input.getAttribute('max'), 10) : Infinity;

    function clamp(val) {
      val = Math.max(1, Math.min(max, val || 1));
      return val;
    }

    if (minus) {
      minus.addEventListener('click', function () {
        input.value = clamp(parseInt(input.value, 10) - 1);
        input.dispatchEvent(new Event('change'));
      });
    }
    if (plus) {
      plus.addEventListener('click', function () {
        input.value = clamp(parseInt(input.value, 10) + 1);
        input.dispatchEvent(new Event('change'));
      });
    }
    input.addEventListener('change', function () {
      input.value = clamp(parseInt(input.value, 10));
    });
  });

  // --- Cart page: auto-submit the row form when quantity changes ---
  document.querySelectorAll('.cart-qty-form input[type="number"]').forEach(function (input) {
    input.addEventListener('change', function () {
      input.closest('form').submit();
    });
  });

  // --- AJAX "Add to cart" forms (product cards + product detail page) ---
  document.querySelectorAll('.ajax-add-to-cart').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var button = form.querySelector('button[type="submit"]');
      var originalText = button ? button.textContent : '';
      if (button) { button.disabled = true; button.textContent = 'Adding…'; }

      fetch('/api/add_to_cart.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(new FormData(form))
      })
        .then(function (res) { return res.json(); })
        .then(function (data) {
          if (data.success) {
            document.querySelectorAll('.cart-count').forEach(function (el) {
              el.textContent = data.cart_count;
            });
            if (button) button.textContent = 'Added ✓';
          } else {
            if (button) button.textContent = data.message || 'Error';
          }
        })
        .catch(function () {
          if (button) button.textContent = 'Error — try again';
        })
        .finally(function () {
          setTimeout(function () {
            if (button) { button.disabled = false; button.textContent = originalText; }
          }, 1400);
        });
    });
  });
});
