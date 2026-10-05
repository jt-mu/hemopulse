<?php
/** Record successful business/security actions; never store secrets or message bodies. */
function auditEvent(PDO $pdo,?int $actor,string $action,string $table,int $id,array $details=[]): void {
    $pdo->prepare('INSERT INTO audit_logs(user_id,action_performed,affected_table,target_record_id,client_ip_address,details_json) VALUES(?,?,?,?,?,?)')->execute([$actor,$action,$table,$id,substr($_SERVER['REMOTE_ADDR']??'',0,45),json_encode($details,JSON_INVALID_UTF8_SUBSTITUTE)]);
}
