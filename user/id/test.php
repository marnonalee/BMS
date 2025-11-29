<?php
require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');
use setasign\Fpdi\Fpdi;

$pdf = new FPDI();
$pdf->AddPage();

$templatePath = __DIR__ . '/../templates/Barangay_id.pdf';
$pdf->setSourceFile($templatePath);
$page1 = $pdf->importPage(1);
$pdf->useTemplate($page1);

$pdf->SetFont('Helvetica','',6);

$pdf->SetXY(67,39.5);  $pdf->Write(0,"LAST NAME TEST");
$pdf->SetXY(67,45.5);  $pdf->Write(0,"FIRST NAME TEST");
$pdf->SetXY(67,51.4);  $pdf->Write(0,"MIDDLE NAME TEST");
$pdf->SetXY(67,57);    $pdf->Write(0,"BIRTHDATE TEST");
$pdf->SetXY(115,29.3); $pdf->Write(0,"ADDRESS TEST");
$pdf->SetXY(115,34.5); $pdf->Write(0,"BIRTH PLACE");
$pdf->SetXY(154,34.5); $pdf->Write(0,"SEX TEST");
$pdf->SetXY(154,40);   $pdf->Write(0,"RESIDENCY TEST");
$pdf->SetXY(115,51.8);   $pdf->Write(0,"EMERGENCY NAME");
$pdf->SetXY(154,51.8);   $pdf->Write(0,"EMERGENCY CONTACT");

$pdf->SetDrawColor(255,0,0);
$pdf->Rect(35,40,20,20); // picture box
$pdf->Rect(67,61,30,4);   // signature box

$pdf->SetXY(115,40); 
$pdf->Write(0,"VALID UNTIL: 12/31/2025");

$pdf->Output();
?>
