<?php
require_once __DIR__ . '/bootstrap.php';
function checkAuthenticated(): void {
    try { $user = currentUser(); }
    catch (Throwable $e) { error_log($e->getMessage()); jsonResponse(['status' => 'error', 'message' => 'Account service unavailable.'], 503); }
    if (!$user) jsonResponse(['status' => 'error', 'message' => 'Please log in.'], 401);
    $_SESSION['role_name'] = $user['role_name'];
}
function authorizeRoles(array $allowedRoles): void {
    checkAuthenticated();
    if (!in_array($_SESSION['role_name'], $allowedRoles, true)) jsonResponse(['status' => 'error', 'message' => 'You do not have permission for this action.'], 403);
}
