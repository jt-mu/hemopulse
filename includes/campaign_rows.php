<?php foreach ($campaigns as $campaign):
    $status = campaignRegistrationStatus($campaign); ?>
<tr data-location="<?= h($campaign['location_venue']) ?>" data-status="<?= $status ?>">
  <td><?= h($campaign['title']) ?></td><td><?= h($campaign['campaign_date']) ?></td>
  <td><?= h(substr($campaign['start_time'], 0, 5)) ?>–<?= h(substr($campaign['end_time'], 0, 5)) ?></td>
  <td><?= h($campaign['location_venue']) ?></td><td><?= $status ?></td>
</tr>
<?php endforeach; ?>
