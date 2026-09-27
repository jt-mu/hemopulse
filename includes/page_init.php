<?php
require_once __DIR__ . '/bootstrap.php';
$user = null;
try { $user = currentUser(); }
catch (Throwable $e) { error_log($e->getMessage()); flash('Account information is temporarily unavailable.'); }
