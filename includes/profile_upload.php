<?php
function profileStorage(): string { return getenv('HEMOPULSE_PROFILE_DIR') ?: __DIR__ . '/../storage/profiles'; }
function validateProfileUpload(array $file): array {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('Choose an image no larger than 2 MB.');
    if (($file['size'] ?? 0) < 1 || $file['size'] > 2 * 1024 * 1024) throw new InvalidArgumentException('Your profile picture must be no larger than 2 MB.');
    $path = $file['tmp_name'] ?? '';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if (!isset($types[$mime])) throw new InvalidArgumentException('Use a JPEG, PNG or WebP image.');
    $size = @getimagesize($path);
    if (!$size || $size['mime'] !== $mime || $size[0] > 4096 || $size[1] > 4096) throw new InvalidArgumentException('Use a valid image with dimensions up to 4096 × 4096.');
    return [$mime, $types[$mime]];
}
