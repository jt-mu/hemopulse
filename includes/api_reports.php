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
    if ($staff) $activity=$pdo->query("SELECT l.details_json,l.action_performed,l.affected_table,l.target_record_id,l.log_timestamp,CONCAT(u.first_name,' ',u.last_name) AS actor FROM audit_logs l LEFT JOIN users u ON u.user_id=l.user_id ORDER BY l.log_id DESC LIMIT 10")->fetchAll();
    $metrics=[];$stock=[];
    if($staff){
        require_once __DIR__.'/api_workflows.php';expireInventory($pdo);
        $metrics['Today’s appointments']=(int)$pdo->query("SELECT COUNT(*) FROM appointments a JOIN campaigns c ON c.campaign_id=a.campaign_id WHERE c.campaign_date=CURDATE() AND a.appointment_status IN ('Pending','Confirmed','Checked-In')")->fetchColumn();
        $metrics['Eligibility reviews']=(int)$pdo->query("SELECT COUNT(*) FROM eligibility_checks e WHERE outcome='Needs Review' AND NOT EXISTS(SELECT 1 FROM eligibility_checks n WHERE n.user_id=e.user_id AND n.eligibility_id>e.eligibility_id)")->fetchColumn();
        $metrics['Checked-in donors']=(int)$pdo->query("SELECT COUNT(*) FROM appointments WHERE appointment_status='Checked-In'")->fetchColumn();
        $metrics['Units awaiting processing']=(int)$pdo->query("SELECT COALESCE(SUM(units_available),0) FROM blood_inventory WHERE inventory_status='Pending' AND expiration_date>CURDATE()")->fetchColumn();
        $metrics['Upcoming campaigns']=(int)$pdo->query("SELECT COUNT(*) FROM campaigns WHERE campaign_status IN ('Active','Published') AND TIMESTAMP(campaign_date,end_time)>NOW()")->fetchColumn();
        foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $blood){
            $s=$pdo->prepare("SELECT COALESCE(SUM(units_available),0) FROM blood_inventory WHERE blood_type=? AND inventory_status='Available' AND expiration_date>CURDATE()");$s->execute([$blood]);$units=(int)$s->fetchColumn();
            $s=$pdo->prepare('SELECT minimum_units FROM inventory_thresholds WHERE blood_type=?');$s->execute([$blood]);$threshold=$s->fetchColumn();$threshold=$threshold===false?5:(int)$threshold;
            $s=$pdo->prepare('SELECT inventory_status,COALESCE(SUM(units_available),0) AS units,COALESCE(SUM(units_available*volume_ml_per_unit),0) AS volume FROM blood_inventory WHERE blood_type=? GROUP BY inventory_status');$s->execute([$blood]);$states=[];foreach($s->fetchAll() as $row)$states[$row['inventory_status']]=$row;
            $stock[]=['blood_type'=>$blood,'units'=>$units,'pending'=>(int)($states['Pending']['units']??0),'reserved'=>(int)($states['Reserved']['units']??0),'expired'=>(int)($states['Expired']['units']??0),'used'=>(int)($states['Used']['units']??0),'discarded'=>(int)($states['Discarded']['units']??0)+(int)($states['Disposed']['units']??0),'available_volume_ml'=>(int)($states['Available']['volume']??0),'minimum'=>$threshold,'status'=>$units===0?'Out of stock':($units<$threshold?'Low stock':'Sufficient stock')];
        }
    }
    return ['metrics'=>$metrics,'stock'=>$stock,'counts'=>$counts,'total'=>array_sum($counts),'monthly'=>$monthly,'popular'=>$popular,'activity'=>$activity,'generated_at'=>date('Y-m-d H:i:s'),'date_basis'=>'Donation event date'];
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
