document.querySelectorAll('.nav-dropdown').forEach(menu => {
  const trigger = menu.querySelector('.dropdown-trigger');
  function setOpen(open) { menu.classList.toggle('menu-open', open); trigger.setAttribute('aria-expanded', String(open)); }
  trigger.addEventListener('click', () => setOpen(true));
  menu.addEventListener('mouseenter', () => setOpen(true));
  menu.addEventListener('mouseleave', () => { if (!menu.contains(document.activeElement)) setOpen(false); });
  menu.addEventListener('focusin', event => { if (event.target !== trigger) setOpen(true); });
  menu.addEventListener('focusout', event => { if (!menu.contains(event.relatedTarget)) setOpen(false); });
  menu.addEventListener('keydown', event => {
    if (event.key === 'Escape') { trigger.focus(); setOpen(false); }
    if (event.key === 'ArrowDown' && event.target === trigger) { event.preventDefault(); setOpen(true); menu.querySelector('.dropdown-menu a')?.focus(); }
  });
  document.addEventListener('click', event => { if (!menu.contains(event.target)) setOpen(false); });
});
