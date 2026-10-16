<?php
/* ═══════════════════════════════════════════════════════════════
   FORNO DEL VICOLO — Gestione prenotazioni
   ═══════════════════════════════════════════════════════════════ */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Accetta solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Metodo non consentito.']);
    exit;
}

// ─── Leggi input (JSON o form-data) ──────────────────────────
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data) {
    $data = $_POST;
}

// ─── Sanifica una stringa ─────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// ─── Validazione ─────────────────────────────────────────────
$errors = [];

$name   = clean($data['name']   ?? '');
$email  = clean($data['email']  ?? '');
$phone  = clean($data['phone']  ?? '');
$date   = clean($data['date']   ?? '');
$time   = clean($data['time']   ?? '');
$guests = clean($data['guests'] ?? '');
$occasion = clean($data['occasion'] ?? '');
$notes  = clean($data['notes']  ?? '');

if ($name === '')  $errors['name']   = 'Nome obbligatorio.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))
                   $errors['email']  = 'Email non valida.';
if ($phone === '') $errors['phone']  = 'Telefono obbligatorio.';
if ($date === '')  $errors['date']   = 'Data obbligatoria.';
if ($guests === '') $errors['guests'] = 'Numero di persone obbligatorio.';

// La data non può essere nel passato
if ($date !== '') {
    $today = new DateTime('today');
    $chosen = DateTime::createFromFormat('Y-m-d', $date);
    if (!$chosen || $chosen < $today) {
        $errors['date'] = 'La data deve essere oggi o futura.';
    }
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'errors' => $errors]);
    exit;
}

// ─── Salva su file CSV (semplice, senza DB) ───────────────────
$file = __DIR__ . '/data/prenotazioni.csv';

// Crea la cartella data/ se non esiste
if (!is_dir(__DIR__ . '/data')) {
    mkdir(__DIR__ . '/data', 0750, true);
}

// Intestazione se il file è nuovo
$isNew = !file_exists($file);
$fh = fopen($file, 'a');
if ($fh === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Errore interno. Riprova più tardi.']);
    exit;
}

if ($isNew) {
    fputcsv($fh, ['id', 'timestamp', 'nome', 'email', 'telefono', 'data', 'orario', 'persone', 'occasione', 'note'], ',', '"', '\\');
}

$id = uniqid('PRN-', true);
fputcsv($fh, [
    $id,
    date('Y-m-d H:i:s'),
    $name,
    $email,
    $phone,
    $date,
    $time,
    $guests,
    $occasion,
    $notes,
], ',', '"', '\\');
fclose($fh);

// ─── Risposta ─────────────────────────────────────────────────
echo json_encode([
    'ok'      => true,
    'id'      => $id,
    'message' => "Grazie $name! La prenotazione è stata ricevuta. Ti confermiamo entro 30 minuti.",
]);
