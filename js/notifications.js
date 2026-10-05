window.initializeNotifications = (root = document) => {
  root.querySelectorAll('.notification-card').forEach(card => {
    if (card.dataset.notificationReady) return;
    card.dataset.notificationReady = '1';
    const summary = card.querySelector('summary');
    let expanded = card.open, animation = null;
    summary.addEventListener('click', event => {
      if (!card.animate || matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      event.preventDefault();
      const start = card.getBoundingClientRect().height;
      animation?.cancel();
      expanded = !expanded;
      card.dataset.expanded = String(expanded);
      card.open = true;
      const style = getComputedStyle(card);
      const closed = summary.getBoundingClientRect().height + parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
      animation = card.animate([{height:start+'px'}, {height:(expanded ? card.getBoundingClientRect().height : closed)+'px'}], {duration:300, easing:'ease-in-out'});
      animation.onfinish = () => {card.open = expanded; animation = null; delete card.dataset.expanded;};
    });
    const form = card.querySelector('.notification-read-form');
    if (!form) return;
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (form.dataset.reading) return;
      form.dataset.reading = '1';
      const button = form.querySelector('button');
      button.disabled = true; button.textContent = 'Updating…';
      try {
        const data = new FormData(form);
        const response = await fetch('api/index.php/notifications/'+data.get('notification_id'), {method:'PUT', credentials:'same-origin', headers:{'Content-Type':'application/json','X-CSRF-Token':data.get('csrf')}, body:'{}'});
        const result = await response.json();
        if (!response.ok) throw new Error(result.message || 'Could not update this notification. Please try again.');
        if (!card.isConnected) return;
        card.classList.remove('is-unread');
        card.querySelector('.notification-badge').textContent = 'Read';
        const count = card.closest('.notification-panel').querySelector('[data-unread-count]');
        count.textContent = String(Math.max(0, Number(count.textContent)-1));
        animation?.cancel(); animation = null; card.open = expanded = true; delete card.dataset.expanded;
        form.remove();
        window.showNotice('notification-feedback', 'Notification marked as read.', false);
      } catch (error) {
        if (card.isConnected) window.showNotice('notification-feedback', error.message);
      } finally {
        delete form.dataset.reading;
        delete form.dataset.busy;
        button.disabled = false; button.textContent = 'Mark as read';
      }
    });
  });
};
window.initializeNotifications();
