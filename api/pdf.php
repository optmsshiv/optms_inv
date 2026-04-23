<?php
require '/home1/edrppymy/public_html/invoiceoptms/vendor/autoload.php';
echo class_exists('\Mpdf\Mpdf') ? 'mPDF OK ✓' : 'mPDF NOT FOUND ✗';