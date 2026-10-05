<?php
// Read-only schema metadata; does not export application records or credentials.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/database.php';
$pdo=getDBConnection();
$tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);$schema=[];
foreach($tables as $table){
    $columns=$pdo->prepare('SELECT COLUMN_NAME,DATA_TYPE,COLUMN_TYPE,IS_NULLABLE,COLUMN_KEY FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=? AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');$columns->execute([DB_NAME,$table]);
    $keys=$pdo->prepare('SELECT COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=? AND TABLE_NAME=? AND REFERENCED_TABLE_NAME IS NOT NULL');$keys->execute([DB_NAME,$table]);
    $schema[$table]=['columns'=>$columns->fetchAll(),'foreign_keys'=>$keys->fetchAll()];
}
echo json_encode($schema,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
