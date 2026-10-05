<?php
require_once __DIR__.'/bootstrap.php';
/** Bearer links expire; only hashes are stored. No public numeric-ID lookup. */
function contactThread(PDO $pdo,string $token): ?array {
    if(!preg_match('/^[a-f0-9]{64}$/D',$token))return null;
    $s=$pdo->prepare('SELECT * FROM contact_messages WHERE thread_hash=? AND thread_expires_at>NOW()');$s->execute([hash('sha256',$token)]);return $s->fetch()?:null;
}
