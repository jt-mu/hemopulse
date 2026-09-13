<?php
// includes/auth_guard.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Ensures the user has an active session.
 */
function checkAuthenticated(): void {
    if (!isset($_SESSION['user_id'])) {
        header('Location: ../login.php?error=unauthorized');
        exit();
    }
}

/**
 * Validates that the active session has one of the required roles.
 *
 * @param array $allowedRoles List of valid role names (e.g., ['Staff', 'Admin'])
 */
function authorizeRoles(array $allowedRoles): void {
    checkAuthenticated();
    if (!isset($_SESSION['role_name']) || !in_array($_SESSION['role_name'], $allowedRoles, true)) {
        http_response_code(403);
        die(json_encode([
            'status'  => 'error',
            'message' => 'Access Denied: You do not possess the required permissions.'
        ]));
    }
}