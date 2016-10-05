<?php

require_once "dompdf-master/dompdf_config.inc.php";
$html = '';
$html=ob_start();
require_once 'test.php';

$html = ob_get_clean();
$pname = "test.pdf";
$theme_root = "a_pdfs/" . $pname;
if (get_magic_quotes_gpc())
    $html = stripslashes($html);
$dompdf = new DOMPDF();
$dompdf->load_html($html);
$dompdf->render();
$dompdf->stream("test.pdf");
$output = $dompdf->output();
try {
    file_put_contents($theme_root, $output);
} catch (Exception $e) {
    echo $e;
}
die;
