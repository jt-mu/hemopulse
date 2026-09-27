<div class="nav-dropdown">
  <button type="button" class="dropdown-trigger" aria-expanded="false" aria-controls="account-menu">Account ▾</button>
  <div class="dropdown-menu" id="account-menu">
    <?php if ($user): ?>
      <a href="dashboard.php"><?php if (!empty($user['profile_image'])): ?><img class="menu-avatar" src="profile_photo.php" alt=""><?php endif; ?>My Account - <?= h($user['first_name'] . ' ' . $user['last_name']) ?></a>
      <a href="dashboard.php">Donate</a>
      <a href="helpdesk.php">Help Desk</a>
      <form action="backend/auth_handler.php" method="post">
        <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="dropdown-signout">Sign Out</button>
      </form>
    <?php else: ?>
      <a href="#login" onclick="openAuthModal('login'); return false;">Login</a>
      <a href="#register" onclick="openAuthModal('register'); return false;">Register</a>
      <a href="#login" onclick="openAuthModal('login'); return false;">Donate</a>
      <a href="helpdesk.php">Help Desk</a>
    <?php endif; ?>
  </div>
</div>
