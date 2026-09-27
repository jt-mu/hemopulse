<?php
function reportData(PDO $pdo,array $user): array {
    $where=[]; $params=[]; $staff=in_array($user['role_name'],['Admin','Staff'],true);
    if (!$staff) { $where[]='a.donor_id=?'; $params[]=$user['user_id']; }
    dateFilters('c.campaign_date',$where,$params);
    $clause=$where?' WHERE '.implode(' AND ',$where):'';
    $base=' FROM appointments a JOIN campaigns c ON c.campaign_id=a.campaign_id';
    $s=$pdo->prepare('SELECT a.appointment_status,COUNT(*) AS total'.$base.$clause.' GROUP BY a.appointment_status'); $s->execute($params);
    $counts=array_fill_keys(['Pending','Confirmed','Checked-In','Completed','Deferred','Cancelled','No-Show'],0);
    foreach($s->fetchAll() as $row) $counts[$row['appointment_status']]=(int)$row['total'];
    $s=$pdo->prepare("SELECT DATE_FORMAT(c.campaign_date,'%Y-%m') AS month,COUNT(*) AS total".$base.$clause.' GROUP BY month ORDER BY month'); $s->execute($params); $monthly=$s->fetchAll();
    $s=$pdo->prepare('SELECT c.campaign_id,c.title,COUNT(*) AS total'.$base.$clause.' GROUP BY c.campaign_id,c.title ORDER BY total DESC,c.campaign_id DESC LIMIT 5'); $s->execute($params); $popular=$s->fetchAll();
    $activity=[];
    if ($staff) $activity=$pdo->query("SELECT l.action_performed,l.affected_table,l.target_record_id,l.log_timestamp,CONCAT(u.first_name,' ',u.last_name) AS actor FROM audit_logs l LEFT JOIN users u ON u.user_id=l.user_id ORDER BY l.log_id DESC LIMIT 10")->fetchAll();
    return ['counts'=>$counts,'total'=>array_sum($counts),'monthly'=>$monthly,'popular'=>$popular,'activity'=>$activity,'generated_at'=>date('Y-m-d H:i:s'),'date_basis'=>'Donation event date'];
}
function exportReport(array $report): void {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="hemopulse-report.csv"');
    $out=fopen('php://output','w');
    $write=static function(array $row) use($out): void {
        foreach($row as &$value) { $value=(string)$value; if(preg_match('/^[=+@\-\t\r\n]/',ltrim($value,' '))) $value="'".$value; }
        fputcsv($out,$row);
    };
    $write(['HemoPulse registration report',$report['generated_at']]); $write(['Date basis',$report['date_basis']]); $write(['Status','Count']);
    foreach($report['counts'] as $status=>$count) $write([$status,$count]);
    $write(['Total',$report['total']]); $write([]); $write(['Month','Registrations']); foreach($report['monthly'] as $row) $write([$row['month'],$row['total']]);
    $write([]); $write(['Most requested campaign','Registrations']); foreach($report['popular'] as $row) $write([$row['title'],$row['total']]);
    fclose($out); exit;
}
