// Replace only the donor content; keep the sidebar and document in place.
(() => {
  const main = document.querySelector('.dash-content');
  if (!main) return;
  let pending = null;

  async function navigate(url, push = true) {
    pending?.abort();
    const request = new AbortController();
    pending = request;
    main.setAttribute('aria-busy', 'true');
    try {
      const response = await fetch(url, {credentials: 'same-origin', cache: 'no-store', signal: request.signal});
      if (request !== pending) return;
      if (response.redirected && new URL(response.url).pathname !== url.pathname) {
        location.assign(response.url);
        return;
      }
      if (!response.ok) throw new Error('This section could not load. Please try again.');
      const page = new DOMParser().parseFromString(await response.text(), 'text/html');
      if (request !== pending) return;
      const content = page.querySelector('.dash-content');
      if (!content) throw new Error('This section is unavailable. Refresh the page to try again.');
      main.replaceChildren(...content.childNodes);
      document.querySelectorAll('.dash-nav .nav-pill').forEach(link => {
        const next = [...page.querySelectorAll('.dash-nav .nav-pill')].find(item => item.getAttribute('href') === link.getAttribute('href'));
        link.classList.toggle('active', !!next?.classList.contains('active'));
        if (next?.classList.contains('active')) link.setAttribute('aria-current', 'page');
        else link.removeAttribute('aria-current');
      });
      if (push) history.pushState(null, '', url);
      window.initializeFormGuard?.(main);
      window.initializeEligibility?.();
      window.initializeDonorDashboard?.();
      window.initializeNotifications?.(main);
      window.initializeNotices?.(main);
      window.scrollTo(0, 0);
      const heading = main.querySelector('h1');
      if (heading) { heading.tabIndex = -1; heading.focus({preventScroll: true}); }
    } catch (error) {
      if (error.name === 'AbortError' || request !== pending) return;
      let notice = main.querySelector('#dashboard-navigation-notice');
      if (!notice) { notice = document.createElement('p'); notice.id = 'dashboard-navigation-notice'; main.prepend(notice); }
      window.showNotice(notice, error.message);
    } finally {
      if (request === pending) main.setAttribute('aria-busy', 'false');
    }
  }

  document.addEventListener('click', event => {
    const link = event.target.closest('a[href]');
    if (!link || !link.closest('.dash-nav, .dash-content') || event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || link.target || link.hasAttribute('download')) return;
    const url = new URL(link.href);
    if (url.origin !== location.origin || url.pathname !== location.pathname || url.hash) return;
    event.preventDefault();
    if (url.href !== location.href) navigate(url);
  });
  window.addEventListener('popstate', () => navigate(new URL(location.href), false));
})();
