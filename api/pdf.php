<?php
// Step 1: find a writable temp dir
$dirs = [
    sys_get_temp_dir(),
    '/tmp',
    '/home1/edrppymy/tmp',
    '/home1/edrppymy/public_html/invoiceoptms/tmp',
];
foreach ($dirs as $d) {
    echo $d . ' — ' . (is_writable($d) ? 'WRITABLE ✓' : 'NOT writable ✗') . '<br>';
}
exit;