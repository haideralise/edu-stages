<?php
header('X-Cache-Enabled: False');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

// Strip /edu prefix so Laravel sees "/" for root route
if (isset($_SERVER['REQUEST_URI'])) {
    $_SERVER['REQUEST_URI'] = preg_replace('#^/edu#', '', $_SERVER['REQUEST_URI']) ?: '/';
}
if (isset($_SERVER['SCRIPT_NAME'])) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require __DIR__.'/public/index.php';