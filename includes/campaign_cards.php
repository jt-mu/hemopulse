<?php foreach ($campaigns as $campaign): $status = campaignRegistrationStatus($campaign);
$title=strtolower($campaign['title']);$kind=str_contains($title,'weekend')?'weekend':((str_contains($title,'student')||str_contains($title,'university'))?'student':((str_contains($title,'corporate')||str_contains($title,'workplace'))?'corporate':'barangay')); ?>
<article class="upcoming-card" data-location="<?= h($campaign['location_venue']) ?>" data-status="<?= $status ?>">
  <h3 class="upcoming-card-title"><?= h($campaign['title']) ?></h3>
  <?php if(str_contains($campaign['description']??'','Fictional')||str_contains($campaign['description']??'','fictional')): ?><p class="form-hint-note">Fictional prototype event</p><?php endif; ?>
  <div class="card-img-box sm-img"><img src="images/<?= $kind ?>-blood-donation.jpg" alt="<?= h(ucfirst($kind)) ?> blood donation campaign" loading="lazy"></div>
  <p><?= h($campaign['location_venue']) ?></p>
  <div class="chip-date"><?= h($campaign['campaign_date']) ?></div>
  <p><?= h(substr($campaign['start_time'], 0, 5)) ?>–<?= h(substr($campaign['end_time'], 0, 5)) ?></p>
  <p class="campaign-status status-<?= strtolower($status) ?>"><?= $status ?></p>
  <?php if ($status !== 'OPEN'): ?>
    <button type="button" class="btn-figma-yellow btn-sm" disabled><?= $status === 'FULL' ? 'Registration Full' : 'Registration Closed' ?></button>
  <?php else: ?>
    <a class="btn-figma-yellow btn-sm" href="dashboard.php?campaign_id=<?= (int)$campaign['campaign_id'] ?>">Register Now</a>
  <?php endif; ?>
</article>
<?php endforeach; ?>
