<?php
/* ═══════════════════════════════════════════════════════════════
   FORNO DEL VICOLO — Pannello admin (prenotazioni e ordini)

   ⚠️  In produzione proteggi questa pagina con HTTP Basic Auth
       o spostala fuori dalla webroot.
   ═══════════════════════════════════════════════════════════════ */

// ─── Autenticazione minimale ──────────────────────────────────
// Cambia utente e password prima del deploy!
define('ADMIN_USER', 'admin');
define('ADMIN_PASS', 'vicolo2024');

session_start();

$loggedIn = isset($_SESSION['admin_ok']) && $_SESSION['admin_ok'] === true;

// Login
if (!$loggedIn) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user'], $_POST['pass'])) {
        if ($_POST['user'] === ADMIN_USER && $_POST['pass'] === ADMIN_PASS) {
            session_regenerate_id(true);
            $_SESSION['admin_ok'] = true;
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }
        $loginError = 'Credenziali errate.';
    }
    ?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Forno del Vicolo</title>
  <style>
    body{font-family:sans-serif;display:grid;place-items:center;min-height:100vh;background:#f5f0e6;margin:0}
    form{background:#fff;padding:40px;border-radius:8px;box-shadow:0 4px 24px rgba(0,0,0,.1);min-width:300px}
    h1{font-size:1.3rem;margin:0 0 24px}
    label{display:block;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:5px;margin-top:14px}
    input{width:100%;padding:10px 12px;border:1px solid #ccc;border-radius:4px;font-size:.9rem;box-sizing:border-box}
    button{margin-top:20px;width:100%;padding:12px;background:#b6432d;color:#fff;border:none;border-radius:4px;font-size:.9rem;cursor:pointer}
    .err{color:#b6432d;font-size:.8rem;margin-top:10px}
  </style>
</head>
<body>
  <form method="post">
    <h1>🍕 Pannello Admin</h1>
    <label for="user">Utente</label>
    <input type="text" id="user" name="user" required autocomplete="username" />
    <label for="pass">Password</label>
    <input type="password" id="pass" name="pass" required autocomplete="current-password" />
    <button type="submit">Accedi</button>
    <?php if (!empty($loginError)): ?>
      <p class="err"><?= htmlspecialchars($loginError) ?></p>
    <?php endif; ?>
  </form>
</body>
</html>
    <?php
    exit;
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// ─── Helpers ──────────────────────────────────────────────────
function readCsv(string $path): array {
    if (!file_exists($path)) return [];
    $rows = [];
    $fh   = fopen($path, 'r');
    $headers = fgetcsv($fh, null, ',', '"', '\\');
    while (($row = fgetcsv($fh, null, ',', '"', '\\')) !== false) {
        if ($headers && count($headers) === count($row)) {
            $rows[] = array_combine($headers, $row);
        }
    }
    fclose($fh);
    return array_reverse($rows); // più recenti in cima
}

$prenotazioni = readCsv(__DIR__ . '/data/prenotazioni.csv');
$ordini       = readCsv(__DIR__ . '/data/ordini.csv');

$tab = $_GET['tab'] ?? 'prenotazioni';
?>
<!doctype html>
<html lang="it">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Admin — Forno del Vicolo</title>
  <style>
    :root{--tomato:#b6432d;--ink:#292c23;--paper:#f5f0e6;--line:rgba(41,44,35,.15)}
    *{box-sizing:border-box}
    body{font-family:'DM Sans',sans-serif;background:var(--paper);color:var(--ink);margin:0;font-size:14px}
    header{background:var(--ink);color:#fffaf1;padding:14px 28px;display:flex;align-items:center;justify-content:space-between}
    header h1{margin:0;font-size:1.1rem}
    header a{color:rgba(255,250,241,.65);font-size:.75rem;text-decoration:none}
    header a:hover{color:#fffaf1}
    .tabs{display:flex;gap:4px;padding:24px 28px 0;border-bottom:1px solid var(--line)}
    .tab-btn{padding:9px 18px;border:none;background:transparent;font-size:.8rem;font-weight:600;color:#777;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px}
    .tab-btn.active{color:var(--tomato);border-bottom-color:var(--tomato)}
    .content{padding:28px}
    table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.06)}
    th{background:var(--ink);color:#fffaf1;padding:10px 14px;text-align:left;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em}
    td{padding:10px 14px;border-bottom:1px solid var(--line);font-size:.82rem;vertical-align:top}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:#faf6ee}
    .badge{display:inline-block;padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:700;background:#e8f5e9;color:#2e7d32}
    .empty{padding:40px;text-align:center;color:#999}
    .stat-row{display:flex;gap:20px;margin-bottom:24px;flex-wrap:wrap}
    .stat{background:#fff;border-radius:8px;padding:18px 22px;flex:1;min-width:140px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
    .stat strong{display:block;font-size:2rem;color:var(--tomato);line-height:1}
    .stat span{font-size:.75rem;color:#888;margin-top:4px;display:block}
  </style>
</head>
<body>
<header>
  <h1>🍕 Forno del Vicolo — Admin</h1>
  <a href="?logout=1">Esci</a>
</header>

<nav class="tabs">
  <button class="tab-btn <?= $tab === 'prenotazioni' ? 'active' : '' ?>"
          onclick="location='?tab=prenotazioni'">
    Prenotazioni (<?= count($prenotazioni) ?>)
  </button>
  <button class="tab-btn <?= $tab === 'ordini' ? 'active' : '' ?>"
          onclick="location='?tab=ordini'">
    Ordini asporto (<?= count($ordini) ?>)
  </button>
</nav>

<div class="content">

<?php if ($tab === 'prenotazioni'): ?>
  <div class="stat-row">
    <div class="stat"><strong><?= count($prenotazioni) ?></strong><span>Prenotazioni totali</span></div>
    <?php
      $oggi = date('Y-m-d');
      $oggi_count = count(array_filter($prenotazioni, fn($r) => ($r['data'] ?? '') === $oggi));
    ?>
    <div class="stat"><strong><?= $oggi_count ?></strong><span>Oggi</span></div>
  </div>

  <?php if (empty($prenotazioni)): ?>
    <p class="empty">Nessuna prenotazione ancora.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Data/Ora invio</th><th>Nome</th><th>Email</th>
        <th>Tel.</th><th>Data</th><th>Orario</th><th>Persone</th><th>Occasione</th><th>Note</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($prenotazioni as $r): ?>
      <tr>
        <td><code style="font-size:.7rem"><?= htmlspecialchars($r['id'] ?? '') ?></code></td>
        <td><?= htmlspecialchars($r['timestamp'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['nome'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['email'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['telefono'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['data'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['orario'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['persone'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['occasione'] ?? '—') ?></td>
        <td><?= htmlspecialchars($r['note'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>

<?php else: ?>
  <?php
    $totaleOrdini = array_sum(array_column($ordini, 'totale_eur'));
  ?>
  <div class="stat-row">
    <div class="stat"><strong><?= count($ordini) ?></strong><span>Ordini totali</span></div>
    <div class="stat"><strong>€ <?= number_format($totaleOrdini, 0, ',', '.') ?></strong><span>Incasso totale</span></div>
  </div>

  <?php if (empty($ordini)): ?>
    <p class="empty">Nessun ordine ancora.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>ID</th><th>Timestamp</th><th>Articoli</th><th>Totale</th><th>Stato</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ordini as $r): ?>
      <tr>
        <td><code style="font-size:.7rem"><?= htmlspecialchars($r['id'] ?? '') ?></code></td>
        <td><?= htmlspecialchars($r['timestamp'] ?? '') ?></td>
        <td><?= htmlspecialchars($r['articoli'] ?? '') ?></td>
        <td>€ <?= htmlspecialchars($r['totale_eur'] ?? '') ?></td>
        <td><span class="badge"><?= htmlspecialchars($r['stato'] ?? '') ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
<?php endif; ?>

</div>
</body>
</html>
