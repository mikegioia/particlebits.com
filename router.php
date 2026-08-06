<?php

/**
 * Router for PHP's built-in web server, mirroring the clean-URL
 * rewriting that the production host performs: extensionless
 * URLs like /2018/six-privacy-spheres are served from their
 * corresponding .html files.
 *
 *   php -S localhost:8000 -t dist/particlebits.com router.php
 */

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = $_SERVER['DOCUMENT_ROOT'] . $path;

// Existing files and directory indexes are served by the
// built-in server's default behavior
if (file_exists($file)) {
    return false;
}

// Serve extensionless URLs from their .html files, like production
if (file_exists("$file.html")) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile("$file.html");
    return true;
}

// The server must not fall through here: with a router present
// it would serve a parent index.html instead of a 404
http_response_code(404);
header('Content-Type: text/html; charset=UTF-8');
printf('<h1>404 Not Found</h1><p>%s was not found on this server.</p>',
    htmlspecialchars($path, ENT_QUOTES, 'UTF-8'));
