<?php
require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();

// Load template para may background ka
$page = $pdf->setSourceFile(__DIR__."/../templates/guardianship.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);

// -------------------------
// DRAW GRID FOR TESTING
// -------------------------
$pdf->SetFont('Arial','',6);
$pdf->SetTextColor(150,150,150);

// Vertical lines (x-axis)
for ($x = 0; $x <= 210; $x += 5) {
    $pdf->Line($x, 0, $x, 297);
    $pdf->Text($x + 1, 3, $x); // coordinate label
}

// Horizontal lines (y-axis)
for ($y = 0; $y <= 297; $y += 5) {
    $pdf->Line(0, $y, 210, $y);
    $pdf->Text(1, $y - 1, $y); // coordinate label
}

// -------------------------
// SAMPLE TEST TEXT
// -------------------------
$pdf->SetFont("Arial","B",10);
$pdf->SetTextColor(255,0,0);

// Put markers for easier checking
$pdf->Text(80, 126, "GUARDIAN NAME");
$pdf->Text(150, 126, "ADDRESS");

$pdf->Text(55, 154, "CHILD NAME");
$pdf->Text(110, 154, "CHILD AGE");
$pdf->Text(25, 160, "BIRTHPLACE");
$pdf->Text(65, 160, "BIRTHDATE");

$pdf->Text(27, 183, "PURPOSE");
$pdf->Text(45, 202, "DAY");
$pdf->Text(70, 202, "MONTH");

// -------------------------
// OUTPUT PDF
// -------------------------
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="guardianship_tester.pdf"');
$pdf->Output();
exit;
