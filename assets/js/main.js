// Small site-wide UI behaviors (mobile nav toggle, flash auto-dismiss).
document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('.mobile-nav-toggle');
  var nav = document.querySelector('.nav');
  if (toggle && nav) {
    toggle.addEventListener('click', function () {
      nav.classList.toggle('open');
    });
  }

  document.querySelectorAll('.alert[data-autohide]').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity 0.4s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 400);
    }, 4000);
  });
});
