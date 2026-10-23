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

const exportForm = document.getElementById('export-selection');
if (exportForm) {
  const boxes = Array.from(document.querySelectorAll('input[type="checkbox"][form="export-selection"]'));
  const count = exportForm.querySelector('[data-selection-count]');
  const clear = exportForm.querySelector('[data-selection-clear]');
  const submit = exportForm.querySelector('[type="submit"]');
  const update = () => {
    const selected = boxes.filter(box => box.checked).length;
    count.textContent = selected ? `${selected} Dateien ausgewählt` : 'Keine Dateien ausgewählt';
    clear.hidden = !selected;
    submit.disabled = !selected;
  };
  boxes.forEach(box => box.addEventListener('change', update));
  clear.addEventListener('click', () => { boxes.forEach(box => { box.checked = false; }); update(); boxes[0]?.focus(); });
  update();
}
