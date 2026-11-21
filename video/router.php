<?php
// Local-only router for recording the Veye demo video with PHP's built-in server:
//   php -S localhost:8135 -t web video/router.php
// It serves Veye unchanged and adds video/veye-autopilot.js to each page. Never deployed.
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if ($uri === '/__autopilot.js') {
    header('Content-Type: application/javascript');
    readfile(__DIR__ . '/veye-autopilot.js');
    return true;
}
$root = realpath(__DIR__ . '/../web');
$file = $root . str_replace('/', DIRECTORY_SEPARATOR, $uri === '/' ? '/index.php' : $uri);
if (is_dir($file)) {
    $file = rtrim($file, '\\/') . DIRECTORY_SEPARATOR . 'index.php';
}
if (!is_file($file) || substr($file, -4) !== '.php') {
    return false; // static files are served by the built-in server from -t web
}
$script = str_replace('\\', '/', substr($file, strlen($root)));
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $script;
$_SERVER['SCRIPT_FILENAME'] = $file;
chdir(dirname($file));
ob_start(static function (string $html): string {
    return stripos($html, '</body>') === false ? $html
        : str_ireplace('</body>', '<script src="/__autopilot.js"></script></body>', $html);
});
require $file;
