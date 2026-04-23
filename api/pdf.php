<?php
// Kill ALL output buffers first
if (ob_get_level()) ob_end_clean();

// Suppress any error output to screen (errors corrupt PDF binary)
ini_set('display_errors', 0);
error_reporting(0);

require '/home1/edrppymy/public_html/invoiceoptms/vendor/autoload.php';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'    => 'utf-8',
        'format'  => 'A4',
        'tempDir' => '/home1/edrppymy/public_html/invoiceoptms/tmp',
    ]);
    $mpdf->WriteHTML('<h1>Test</h1><p>Working!</p>');
    $mpdf->Output('test.pdf', 'D');
    exit;
} catch (Exception $e) {
    while (ob_get_level()) ob_end_clean();
    http_response_code(500);
    header('Content-Type: text/plain');
    echo $e->getMessage();
    exit;
}