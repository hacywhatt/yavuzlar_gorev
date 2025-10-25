<?php
session_start();

//  fpdf kutuphanesi kullanildi 
require('fpdf186/fpdf.php'); 


if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?message=" . urlencode("PDF indirmek için giriş yapmanız gerekmektedir."));
    exit();
}

$database_file = 'database.sqlite';
$user_id = $_SESSION['user_id'];
$ticket_id = $_GET['ticket_id'] ?? null;

if (empty($ticket_id) || !is_numeric($ticket_id)) {
    die("HATA: Geçersiz Bilet ID'si.");
}

try {
    $db = new PDO("sqlite:$database_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


    $sql = "SELECT 
                t.id as ticket_id, t.seat_number, t.purchase_price, t.is_cancelled,
                u.username,
                tr.departure_city, tr.arrival_city, tr.trip_date, tr.departure_time,
                f.name as firma_name
            FROM tickets t
            JOIN users u ON t.user_id = u.id
            JOIN trips tr ON t.trip_id = tr.id
            JOIN firms f ON tr.firma_id = f.id
            WHERE t.id = ? AND t.user_id = ?";
            
    $stmt = $db->prepare($sql);
    $stmt->execute([$ticket_id, $user_id]);
    $ticket_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ticket_data) {
        die("HATA: Bilet bulunamadı veya size ait değil.");
    }
    //iptal biletler indirilemez
    if ($ticket_data['is_cancelled']) {
         die("HATA: İptal edilmiş bilet indirilemez.");
    }

    
    // pdf olusturma
    $pdf = new FPDF('P', 'mm', 'A4');
    $pdf->AddPage();


    
    
    $pdf->SetFont('Arial', 'B', 16); 
    $pdf->SetTextColor(0, 51, 102); // koyumavi?
    $pdf->Cell(0, 10, iconv('UTF-8', 'ISO-8859-9', ' YAVUZLAR BILET SATIN ALMA PLATFORMU'), 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->SetTextColor(0, 0, 0); 
    $pdf->Cell(0, 5, 'OTOBUS BILETI CIKTISI', 0, 1, 'C');
    $pdf->Ln(10); 

    
    $pdf->SetFillColor(200, 220, 255);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'SEFER VE BILET DETAYLARI', 1, 1, 'L', true);
    $pdf->Ln(2);

    
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(50, 8, 'Firma Adi:', 0);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-9', $ticket_data['firma_name']), 0, 1);
    
    $pdf->Cell(50, 8, 'Yolcu Adi:', 0);
    $pdf->Cell(0, 8, iconv('UTF-8', 'ISO-8859-9', strtoupper($ticket_data['username'])), 0, 1);
    
    $pdf->Cell(50, 8, 'Bilet ID:', 0);
    $pdf->Cell(0, 8, $ticket_data['ticket_id'], 0, 1);
    
    $pdf->Ln(5);

    $pdf->SetFillColor(230, 230, 230);
    $pdf->SetFont('Arial', 'B', 14);
    $pdf->Cell(0, 10, 'Guzergah: ' . iconv('UTF-8', 'ISO-8859-9', $ticket_data['departure_city'] . ' -> ' . $ticket_data['arrival_city']), 1, 1, 'C', true);

    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(50, 8, 'Tarih:', 0);
    $pdf->Cell(0, 8, $ticket_data['trip_date'], 0, 1);

    $pdf->Cell(50, 8, 'Kalkis Saati:', 0);
    $pdf->Cell(0, 8, $ticket_data['departure_time'], 0, 1);
    
    $pdf->Ln(10);

    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetDrawColor(0, 51, 102);
    $pdf->SetLineWidth(0.5);

    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(95, 15, 'KOLTUK NO', 1, 0, 'C', true);
    $pdf->Cell(0, 15, 'FIYAT', 1, 1, 'C', true);
    
    $pdf->SetFont('Arial', 'B', 30);
    $pdf->SetTextColor(255, 0, 0); // kirmizi?
    $pdf->Cell(95, 20, $ticket_data['seat_number'], 1, 0, 'C', true);
    
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->SetTextColor(0, 128, 0); // yesil?
    $pdf->Cell(0, 20, number_format($ticket_data['purchase_price'], 2) . ' TL', 1, 1, 'C', true);
    
    $pdf->Ln(20);

    // d idnirme
    $filename = "Bilet_" . $ticket_data['departure_city'] . "_" . $ticket_data['arrival_city'] . "_" . $ticket_data['ticket_id'] . ".pdf";
    $pdf->Output('D', $filename);
    exit;

} catch (Exception $e) {
    die("PDF oluşturma hatası: " . $e->getMessage());
}

?>

