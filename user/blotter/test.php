<?php
require_once '../../vendor/autoload.php';
use setasign\Fpdi\Fpdi;

// ======== STATIC TEST VALUES ========
$dateToday = date('F j, Y');

$complainant_name = "Juan Dela Cruz";
$complainant_age = "35"; 
$complainant_address = "Purok 5, Brgy. Malinis";

$suspect_name = "Pedro Santos";
$suspect_address = "Sitio Matalino, Brgy. Masaya";
$suspect_contact = "09998887777";

$incident_datetime = "November 23, 2025 08:30 AM";
$incident_location = "Purok 5 Basketball Court";

$incident_nature = "Malakas na ingay tuwing gabi, nakakaabala sa buong kapitbahayan."; // NEW

$agreement_terms = "Nagkasundo ang mga partido na mag-usap at magkaayos sa pamamagitan ng barangay mediation upang maiwasan ang anumang alitan sa hinaharap.";

// ======== TEMPLATE PATH ========
$templateFile = __DIR__ . '/../templates/agree.pdf';
if (!file_exists($templateFile)) {
    die("Template file not found: $templateFile");
}

// ======== MAIN PDF TEST ========
$pdf = new Fpdi();
$pdf->AddPage();
$pdf->setSourceFile($templateFile);
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl);

$pdf->SetFont('Times', '', 12);
$pdf->SetTextColor(0,0,0);

// ======== SAMPLE XY POSITIONS ========
// Date
$pdf->SetXY(30, 77);  $pdf->Write(0, $dateToday);

// Complainant info
$pdf->SetXY(60, 91);  $pdf->Write(0, $complainant_name);
$pdf->SetXY(126, 91); $pdf->Write(0, $complainant_age);
$pdf->SetXY(25, 98);  $pdf->Write(0, $complainant_address);

// Incident info
$pdf->SetXY(27, 106); $pdf->Write(0,  $incident_datetime);
$pdf->SetXY(95, 106); $pdf->Write(0, $incident_location);

// Suspect info
$pdf->SetXY(60, 125); $pdf->Write(0, $suspect_name);
$pdf->SetXY(27, 130); $pdf->Write(0, $suspect_address);
// Incident Nature (Reason for conflict)
$pdf->SetXY(20, 142); 
$pdf->MultiCell(170, 6, "Reason for Conflict: " . $incident_nature, 0, 'L');

// Agreement terms
$pdf->SetXY(20, 163); 
$pdf->MultiCell(170, 6, "Agreement Terms: " . $agreement_terms, 0, 'L');

// ======== OUTPUT PDF ========
$pdf->Output();
exit;
?>
