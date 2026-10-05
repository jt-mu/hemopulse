<section id="donor-api-panel" aria-label="Manage registrations" data-csrf="<?= h(csrfToken()) ?>">
  <h3 class="dash-title">Manage My Registrations</h3>
  <div id="donor-summary" class="row g-2 mb-3"></div>
  <p id="donor-api-notice" role="status" aria-live="polite"></p>
  <form id="donor-filters" class="donor-filters">
    <label>Search campaign<input name="q" maxlength="100" type="search"></label>
    <label>Status<select name="status"><option value="">All statuses</option><?php foreach(['Pending','Confirmed','Checked-In','Completed','Deferred','Cancelled','No-Show'] as $status): ?><option><?= $status ?></option><?php endforeach; ?></select></label>
    <label>From date<input name="from" type="date"></label><label>To date<input name="to" type="date"></label>
    <label>Sort<select name="sort"><option value="newest">Newest first</option><option value="date">Event date</option></select></label>
    <button class="btn-dash-yellow" type="submit">Apply filters</button>
  </form>
  <div id="donor-records" aria-live="polite"></div>
  <div class="donor-pagination"><button type="button" id="donor-previous">Previous</button><span id="donor-page"></span><button type="button" id="donor-next">Next</button></div>
</section>
