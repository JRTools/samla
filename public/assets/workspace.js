/* Optional interaction only. Forms and navigation work without JavaScript. */
'use strict';
const toggle = document.querySelector('[data-navigation-toggle]');
const navigation = document.getElementById('site-navigation');
if (toggle && navigation) {
  const mobile = window.matchMedia('(max-width: 760px)');
  const setOpen = open => {
    navigation.hidden = !open;
    toggle.setAttribute('aria-expanded', String(open));
  };
  const resize = () => { toggle.hidden = !mobile.matches; setOpen(!mobile.matches); };
  toggle.addEventListener('click', () => setOpen(navigation.hidden));
  mobile.addEventListener('change', resize);
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && mobile.matches && !navigation.hidden) {
      setOpen(false); toggle.focus();
    }
  });
  resize();
}
document.addEventListener('keydown', event => {
  if (event.key !== 'Escape') return;
  document.querySelectorAll('details[open]').forEach(detail => {
    if (detail.contains(document.activeElement)) {
      detail.open = false; detail.querySelector('summary')?.focus();
    }
  });
});
