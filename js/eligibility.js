window.initializeEligibility = () => {
document.querySelectorAll('[data-conditional-question]').forEach(group => {
  if(group.dataset.controllerReady)return;group.dataset.controllerReady='1';
  const fieldset = group.closest('fieldset');
  const update = () => {
    const checked=fieldset.querySelector('input[type=radio]:checked');
    const yes = checked?.value === 'yes' && !checked.disabled;
    group.hidden = !yes;
    group.querySelectorAll('input').forEach(input => { input.disabled = !yes; input.required = yes; });
  };
  fieldset.querySelectorAll('input[type=radio]').forEach(input => input.addEventListener('change',update));
  update();
});

};
window.initializeEligibility();
