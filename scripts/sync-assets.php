<?php

$root = dirname(__DIR__);
if (! is_dir($root.'/public/assets')) {
    mkdir($root.'/public/assets', 0755, true);
}
$css = file_get_contents($root.'/resources/css/app.css');
$bootstrap = $root.'/node_modules/bootstrap/dist/css/bootstrap-grid.min.css';
if (! is_file($bootstrap)) {
    fwrite(STDERR, "Run npm ci before synchronizing assets.\n");
    exit(1);
}
$css = str_replace("@import 'bootstrap/dist/css/bootstrap-grid.min.css';", file_get_contents($bootstrap), $css);
if (file_put_contents($root.'/public/assets/app.css', $css) === false) {
    exit(1);
}
if (! copy($root.'/resources/js/app.js', $root.'/public/assets/app.js')) {
    exit(1);
}
echo "Standalone assets synchronized.\n";
