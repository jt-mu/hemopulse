<?php
// Router for PHP's development server. Apache uses .htaccess instead.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (preg_match('~^/api/([a-z-]+(?:/[0-9]+)?)$~D',$path,$match)) { $_SERVER['PATH_INFO']='/'.$match[1]; require __DIR__.'/api/index.php'; return true; }
if (preg_match('~^/(?:database|tests|includes|config|docs|scripts|deployment|storage)(?:/|$)~',$path)||preg_match('/\.(?:sql|md|env|log|eml)$/i',$path)||$path==='/router.php') { http_response_code(404); return true; }
return false;
