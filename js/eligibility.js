document.querySelectorAll('[data-conditional-question]').forEach(group => {
  const fieldset = group.closest('fieldset');
  const update = () => {
    const yes = fieldset.querySelector('input[type=radio]:checked')?.value === 'yes';
    group.hidden = !yes;
    group.querySelectorAll('input').forEach(input => { input.disabled = !yes; input.required = yes; });
  };
  fieldset.querySelectorAll('input[type=radio]').forEach(input => input.addEventListener('change',update));
  update();
});
