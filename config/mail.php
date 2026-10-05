<?php
// Local previews are intentional for development. Configure MAIL_MODE=smtp for authenticated SMTP delivery.
return [
    'transport' => getenv('MAIL_MODE') ?: (getenv('HEMOPULSE_MAIL_TRANSPORT') ?: 'preview'),
    'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
    'smtp_port' => (int)(getenv('SMTP_PORT') ?: 587),
    'smtp_security' => getenv('SMTP_SECURITY') ?: 'tls',
    'smtp_username' => getenv('SMTP_USERNAME') ?: '',
    'smtp_password' => getenv('SMTP_PASSWORD') ?: '',
    'reply_to' => getenv('MAIL_REPLY_TO') ?: '',
    'from' => getenv('HEMOPULSE_MAIL_FROM') ?: 'no-reply@hemopulse.local',
    'app_url' => rtrim(getenv('HEMOPULSE_APP_URL') ?: 'http://localhost/hemopulse', '/'),
    'preview_dir' => getenv('HEMOPULSE_MAIL_PREVIEW_DIR') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hemopulse-mail-previews',
];
