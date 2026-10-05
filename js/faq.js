// Keep native details/summary behavior, adding a reversible height transition.
document.querySelectorAll('.home-faq .faq-bar, .system-faq-section .faq-bar').forEach(details => {
  const summary = details.querySelector('summary');
  if (!summary || !details.animate) return;
  let expanded = details.open, animation = null;

  summary.addEventListener('click', event => {
    event.preventDefault();
    const start = details.getBoundingClientRect().height;
    animation?.cancel();
    expanded = !expanded;
    details.dataset.expanded = String(expanded);

    if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
      details.open = expanded;
      details.classList.remove('faq-animating');
      animation = null;
      return;
    }

    details.open = true;
    const style = getComputedStyle(details);
    const closedHeight = summary.getBoundingClientRect().height +
      parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth) +
      parseFloat(style.paddingTop) + parseFloat(style.paddingBottom);
    const end = expanded ? details.getBoundingClientRect().height : closedHeight;
    details.classList.add('faq-animating');
    animation = details.animate([{height: start + 'px'}, {height: end + 'px'}], {
      duration: 360, easing: 'cubic-bezier(0.25, 0.1, 0.25, 1)'
    });
    animation.onfinish = () => {
      details.open = expanded;
      details.classList.remove('faq-animating');
      animation = null;
    };
  });
});
