<?php 
require_once(__DIR__ . '/../../fpdf/fpdf.php');
require_once(__DIR__ . '/../../fpdi/src/autoload.php');

$pdf = new \setasign\Fpdi\Fpdi();
$pdf->AddPage();

// Load RESIDENCY template
$page = $pdf->setSourceFile(__DIR__."/../templates/RESIDENCY.pdf");
$tpl = $pdf->importPage(1);
$pdf->useTemplate($tpl, 0, 0, 210);

// Optional: Draw grid for testing positions
$pdf->SetFont('Arial','',6);
$pdf->SetTextColor(150,150,150);

for ($x = 0; $x <= 210; $x += 5) {
    $pdf->Line($x, 0, $x, 297);
    $pdf->Text($x + 1, 3, $x);
}

for ($y = 0; $y <= 297; $y += 5) {
    $pdf->Line(0, $y, 210, $y);
    $pdf->Text(1, $y - 1, $y);
}

// Set font and color for actual data
$pdf->SetFont("Arial","B",10);
$pdf->SetTextColor(0,0,0);

// Sample data
$full_name = "Juan D. Santos";
$resident_address = "123 P. dela Cruz St";
$years_lived_word = "Eight";
$years_lived_number = "8";
$purpose = "Employment";                         // PURPOSE
$issued_day = "02";
$issued_month_year = "December 2025";

// Place sample data on template (adjust X, Y coordinates as needed)
$pdf->Text(90, 120, $full_name);                  // Full Name
$pdf->Text(55, 129, $resident_address);           // Resident Address
$pdf->Text(85, 150, $full_name);                  // (Optional duplicate, remove if not needed)
$pdf->Text(40, 156, $years_lived_word);           // Years Lived (word)
$pdf->Text(67, 156, "($years_lived_number)");     // Years Lived (number)
$pdf->Text(30, 180, $purpose);                   // PURPOSE
$pdf->Text(45, 195, $issued_day);                 // Issued Day
$pdf->Text(75, 195, $issued_month_year);          // Issued Month & Year


header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="residency_test.pdf"');
$pdf->Output();
exit;
?>
