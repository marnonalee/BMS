<?php
require_once '../../vendor/autoload.php';
use setasign\Fpdi\Fpdi;

function generateAgreementPDF($blotterData, $suspectData, $agreement_terms) {
    $templateQuery = $GLOBALS['conn']->query("SELECT file_path FROM certificate_templates WHERE template_for='Agreement' LIMIT 1");
    if ($templateQuery->num_rows === 0) throw new Exception("Template for Agreement not found.");
    $templateFile = __DIR__ . '/../' . $templateQuery->fetch_assoc()['file_path'];
    if (!file_exists($templateFile)) throw new Exception("Template file not found: $templateFile");

    $pdf = new Fpdi();
    $pdf->AddPage();
    $pdf->setSourceFile($templateFile);
    $tpl = $pdf->importPage(1);
    $pdf->useTemplate($tpl);
    $pdf->SetFont('Times','',12);
    $pdf->SetTextColor(0,0,0);

    $pdf->SetXY(30, 77); 
    $pdf->Write(0, date('F j, Y'));
    $pdf->SetXY(28, 91); 
    $pdf->Write(0, $blotterData['complainant_name']);
    $pdf->SetXY(126, 91); 
    $pdf->Write(0, $blotterData['complainant_age'] ?? '');
    $pdf->SetXY(20, 98); 
    $pdf->Write(0, $blotterData['complainant_address']);
    $pdf->SetXY(27, 106); 
    $pdf->Write(0, $blotterData['incident_datetime'] ?? '');
    $pdf->SetXY(95, 106); 
    $pdf->Write(0, $blotterData['incident_location'] ?? '');
    $pdf->SetXY(60, 125); 
    $pdf->Write(0, $blotterData['suspect_name']);
    $pdf->SetXY(27, 130); 
    $address = isset($suspectData['resident_address']) ? ucwords(strtolower(trim($suspectData['resident_address']))) : 'N/A';
    $pdf->Write(0, $address);
    $pdf->SetXY(20, 142); 
    $pdf->MultiCell(170, 6,  ($blotterData['incident_nature'] ?? ''), 0, 'L');
    $pdf->SetXY(20, 163); 
    $pdf->MultiCell(170, 6,  $agreement_terms, 0, 'L');

    $outputDir = __DIR__ . '/../generated_agreement_cert/';
    if (!is_dir($outputDir)) mkdir($outputDir,0777,true);

    $pdfFileName = "Agreement_{$blotterData['blotter_id']}_" . time() . ".pdf";
    $pdf->Output('F', $outputDir . $pdfFileName);

    return $pdfFileName;
}
?>
