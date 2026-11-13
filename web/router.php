<?php
// Local dev only (php -S). Mirrors .htaccess denials. Not used on Hostinger.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (preg_match('#^/(inc)/|\.(sql|md|lock|log|cache)$|/\.|/default\.php$#', $path)) {
    http_response_code(403);
    exit('403');
}
return false;
