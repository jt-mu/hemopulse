<?php
require_once __DIR__ . '/../includes/auth_guard.php';
authorizeRoles(['Staff','Admin']);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
jsonResponse(['status'=>'error','message'=>'Recipient fulfillment is not part of this donor-focused application.'],410);
