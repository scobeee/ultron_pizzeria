<?php
/* ═══════════════════════════════════════════════════════════════
   FORNO DEL VICOLO — Gestione ordini asporto
   ═══════════════════════════════════════════════════════════════ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non consentito.']);
    exit;
}

// ─── Leggi input JSON ─────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['items']) || !is_array($data['items'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Payload non valido.']);
    exit;
}

// ─── Sanifica una stringa ─────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// ─── Prezzi lato server (fonte di verità) ────────────────────
// Nessuno può mandare prezzi falsi dal browser.
$PRICES = [
    'margherita' => 9,
    'diavola'    => 12,
    'quattro'    => 13,
    'orto'       => 13,
    'vicolo'     => 14,
    'nduja'      => 14,
    'bufala'     => 15,
    'porchetta'  => 15,
    'fritto'     => 8,
    'bruschetta' => 6,
    'burrata'    => 9,
    'salumi'     => 12,
    'tiramisu'   => 6,
    'panna'      => 5,
    'nutella'    => 7,
    'acqua'      => 2,
    'birra'      => 5,
    'vino'       => 18,
    'bibite'     => 3,
    'limoncello' => 4,
];

// ─── Validazione articoli ─────────────────────────────────────
$errors = [];
$items  = [];
$total  = 0;

foreach ($data['items'] as $item) {
    $id  = clean($item['id']  ?? '');
    $qty = (int)($item['qty'] ?? 0);
    $name = clean($item['name'] ?? $id);

    if ($id === '' || $qty <= 0) continue;
    if (!isset($PRICES[$id])) {
        $errors[] = "Articolo sconosciuto: $id";
        continue;
    }
    if ($qty > 20) {
        $errors[] = "Quantità eccessiva per: $id";
        continue;
    }

    $price = $PRICES[$id];
    $items[]  = compact('id', 'name', 'qty', 'price');
    $total   += $price * $qty;
}

if (empty($items)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'Nessun articolo valido nell\'ordine.']);
    exit;
}
if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// ─── Salva su CSV ─────────────────────────────────────────────
$dir  = __DIR__ . '/data';
$file = $dir . '/ordini.csv';

if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}

$isNew = !file_exists($file);
$fh = fopen($file, 'a');
if ($fh === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore interno.']);
    exit;
}

if ($isNew) {
    fputcsv($fh, ['id', 'timestamp', 'articoli', 'totale_eur', 'stato'], ',', '"', '\\');
}

$id = uniqid('ORD-', true);
$articoliStr = implode(' | ', array_map(
    fn($i) => "{$i['qty']}x {$i['name']} (€{$i['price']})",
    $items
));

fputcsv($fh, [
    $id,
    date('Y-m-d H:i:s'),
    $articoliStr,
    number_format($total, 2, '.', ''),
    'ricevuto',
], ',', '"', '\\');
fclose($fh);

// ─── Risposta ─────────────────────────────────────────────────
echo json_encode([
    'ok'      => true,
    'id'      => $id,
    'total'   => $total,
    'items'   => $items,
    'message' => "Ordine ricevuto! Sarà pronto in 25–35 minuti. Ritiro: Via Melzo 18, Milano.",
]);
