<?php
// Local previews are intentional for development. Configure 'mail' for real delivery.
return [
    'transport' => getenv('HEMOPULSE_MAIL_TRANSPORT') ?: 'preview',
    'from' => getenv('HEMOPULSE_MAIL_FROM') ?: 'no-reply@hemopulse.local',
    'app_url' => rtrim(getenv('HEMOPULSE_APP_URL') ?: 'http://localhost/hemopulse-main', '/'),
    'preview_dir' => getenv('HEMOPULSE_MAIL_PREVIEW_DIR') ?: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'hemopulse-mail-previews',
];
