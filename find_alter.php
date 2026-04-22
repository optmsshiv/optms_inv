<?php
// Upload this to your server root (e.g. http://invcs.optms.co.in/find_alter.php)
// Open it in browser — it will find every PHP file with ALTER TABLE
// DELETE THIS FILE after you find the bug!

$root    = $_SERVER['DOCUMENT_ROOT'];
$results = [];

$iter = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iter as $file) {
    if ($file->getExtension() !== 'php') continue;
    $path    = $file->getPathname();
    $content = @file_get_contents($path);
    if ($content === false) continue;

    // Find lines with ALTER TABLE
    if (stripos($content, 'ALTER TABLE') === false) continue;

    $lines = explode("\n", $content);
    foreach ($lines as $num => $line) {
        if (stripos($line, 'ALTER TABLE') !== false) {
            $results[] = [
                'file' => str_replace($root, '', $path),
                'line' => $num + 1,
                'code' => trim($line),
            ];
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
echo '<pre style="font-family:monospace;font-size:13px;padding:20px">';
echo '<strong>ALTER TABLE occurrences found: ' . count($results) . "</strong>\n\n";
foreach ($results as $r) {
    echo "FILE: {$r['file']}  LINE: {$r['line']}\n";
    echo "CODE: {$r['code']}\n";
    echo str_repeat('-', 80) . "\n";
}
if (empty($results)) {
    echo "No ALTER TABLE found in any PHP file.\n";
    echo "The issue is likely a MySQL trigger or event — run this in phpMyAdmin:\n\n";
    echo "SHOW TRIGGERS FROM your_database_name WHERE `Table` = 'invoices';\n";
    echo "SHOW EVENTS FROM your_database_name;\n";
}
echo '</pre>';