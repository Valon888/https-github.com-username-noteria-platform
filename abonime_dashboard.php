<?php
// abonime_dashboard.php - Dashboard profesional për pagesat automatike të abonimeve
session_start();
require_once 'db_connect.php';

// Merr abonimet dhe transaksionet
$abonimet = $pdo->query("SELECT na.*, n.emri as user_emri, n.email as user_email, a.emri as abonim_emri, a.cmimi, a.kohezgjatja FROM noteri_abonimet na JOIN noteret n ON na.noter_id = n.id JOIN abonimet a ON na.abonim_id = a.id ORDER BY na.data_mbarimit ASC")->fetchAll(PDO::FETCH_ASSOC);
$transaksionet = $pdo->query("SELECT t.*, na.status as abonim_status, n.emri as user_emri, a.emri as abonim_emri FROM transaksionet t JOIN noteri_abonimet na ON t.abonim_id = na.id JOIN noteret n ON na.noter_id = n.id JOIN abonimet a ON na.abonim_id = a.id ORDER BY t.payment_date DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard Abonimesh | Noteria</title>
    <link href="https://fonts.googleapis.com/css?family=Montserrat:400,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(120deg, #e2eafc 0%, #f8fafc 100%);
            font-family: 'Montserrat', Arial, sans-serif;
            color: #222;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 1100px;
            margin: 2rem auto;
            background: #fff;
            border-radius: 1rem;
            box-shadow: 0 8px 32px rgba(30,64,175,0.08);
            padding: 2.5rem;
        }
        h1 {
            text-align: center;
            color: #1a56db;
            margin-bottom: 2rem;
            font-size: 2.5rem;
        }
        .dashboard-section {
            margin-bottom: 2.5rem;
        }
        .dashboard-section h2 {
            color: #059669;
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }
        .dashboard-section h2 i {
            margin-right: 0.5rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        th, td {
            padding: 0.75rem 1rem;
            text-align: left;
        }
        th {
            background: #f1f5f9;
            color: #1a56db;
            font-weight: 700;
        }
        tr {
            transition: background 0.2s;
        }
        tr:hover {
            background: #f8fafc;
        }
        .status {
            font-weight: 600;
            border-radius: 0.5rem;
            padding: 0.25rem 0.75rem;
            display: inline-block;
        }
        .status.aktiv { background: #ecfdf5; color: #059669; }
        .status.pezulluar { background: #fef2f2; color: #ef4444; }
        .status.skaduar { background: #fbbf24; color: #fff; }
        .status.sukses { background: #d1fae5; color: #059669; }
        .status.deshtuar { background: #fee2e2; color: #ef4444; }
        .method-stripe { color: #635bff; }
        .method-tink { color: #059669; }
        .lloji-mujor { color: #1a56db; font-weight: 700; }
        .lloji-vjetor { color: #fbbf24; font-weight: 700; }
        .icon-btn {
            background: none;
            border: none;
            color: #1a56db;
            font-size: 1.1rem;
            cursor: pointer;
            transition: color 0.2s;
        }
        .icon-btn:hover { color: #059669; }
        @media (max-width: 900px) {
            .container { padding: 1rem; }
            th, td { padding: 0.5rem; }
        }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-credit-card"></i> Menaxhimi i Abonimeve & Pagesave</h1>
    <div class="dashboard-section">
        <h2><i class="fas fa-sync-alt"></i> Abonimet Aktive</h2>
        <table>
            <thead>
                <tr>
                    <th>Përdoruesi</th>
                    <th>Lloji</th>
                    <th>Çmimi</th>
                    <th>Metoda</th>
                    <th>Fillimi</th>
                    <th>Mbarimi</th>
                    <th>Pagesa e ardhshme</th>
                    <th>Statusi</th>
                    <th>Veprime</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($abonimet)): ?>
                <tr><td colspan="9" style="text-align:center;color:#ef4444;font-weight:600;">Nuk ka abonime aktive!</td></tr>
            <?php else: foreach ($abonimet as $a): ?>
                <tr style="<?php echo (isset($a['status']) && $a['status']=='pezulluar') ? 'background:#fff7f7;' : ((isset($a['status']) && $a['status']=='skaduar') ? 'background:#fffbe5;' : ''); ?>">
                    <td title="Emri i përdoruesit"><span style="font-weight:600;color:#1a56db;"><?php echo htmlspecialchars($a['user_emri'] ?? '-'); ?></span></td>
                    <td class="lloji-<?php echo isset($a['lloji']) ? $a['lloji'] : 'unknown'; ?>" title="Lloji i abonimit">
                        <?php echo isset($a['lloji']) ? ucfirst($a['lloji']) : '—'; ?>
                    </td>
                    <td title="Çmimi i abonimit">€<?php echo number_format($a['cmimi'] ?? 0,2); ?></td>
                    <td class="method-<?php echo isset($a['payment_method']) ? $a['payment_method'] : 'unknown'; ?>" title="Metoda e pagesës">
                        <i class="fas fa-<?php echo (isset($a['payment_method']) && $a['payment_method']=='stripe') ? 'credit-card' : 'university'; ?>"></i> <?php echo isset($a['payment_method']) ? ucfirst($a['payment_method']) : '—'; ?>
                    </td>
                    <td title="Data e fillimit"><span style="color:#059669;"><?php echo htmlspecialchars($a['data_fillimit'] ?? '-'); ?></span></td>
                    <td title="Data e mbarimit"><span style="color:#ef4444;"><?php echo htmlspecialchars($a['data_mbarimit'] ?? '-'); ?></span></td>
                    <td title="Pagesa e ardhshme"><span style="color:#fbbf24;font-weight:600;"><?php echo htmlspecialchars($a['next_payment'] ?? '—'); ?></span></td>
                    <td><span class="status <?php echo $a['status'] ?? 'unknown'; ?>" title="Statusi i abonimit"><?php echo ucfirst($a['status'] ?? '—'); ?></span></td>
                    <td>
                        <button class="icon-btn" title="Shfaq detaje" onclick="showDetails('<?php echo $a['id']; ?>')"><i class="fas fa-info-circle"></i></button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
    <div class="dashboard-section">
        <h2><i class="fas fa-list"></i> Transaksionet e Fundit</h2>
        <table>
            <thead>
                <tr>
                    <th>Përdoruesi</th>
                    <th>Lloji</th>
                    <th>Shuma</th>
                    <th>Data</th>
                    <th>Statusi</th>
                    <th>Metoda</th>
                    <th>Transaksioni</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($transaksionet as $t): ?>
                <tr>
                    <td><?php echo htmlspecialchars($t['user_id']); ?></td>
                    <td class="lloji-<?php echo $t['lloji']; ?>"><?php echo ucfirst($t['lloji']); ?></td>
                    <td>€<?php echo number_format($t['amount'],2); ?></td>
                    <td><?php echo htmlspecialchars($t['payment_date']); ?></td>
                    <td><span class="status <?php echo $t['payment_status']; ?>"><?php echo ucfirst($t['payment_status']); ?></span></td>
                    <td class="method-<?php echo $t['payment_provider']; ?>">
                        <i class="fas fa-<?php echo $t['payment_provider']=='stripe'?'credit-card':'university'; ?>"></i> <?php echo ucfirst($t['payment_provider']); ?>
                    </td>
                    <td><?php echo htmlspecialchars($t['transaction_id']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
function showDetails(id) {
    // Merr të dhënat nga PHP (për thjeshtësi, mund të përdoret AJAX për live data)
    var abonime = <?php echo json_encode($abonimet); ?>;
    var transaksione = <?php echo json_encode($transaksionet); ?>;
    var a = abonime.find(x => x.id == id);
    if (!a) return alert('Abonimi nuk u gjet!');
    var html = `<div style='padding:1.5rem;'>
        <h2 style='color:#1a56db;'><i class='fas fa-info-circle'></i> Detaje Abonimi</h2>
        <p><b>Përdoruesi:</b> ${a.user_emri}</p>
        <p><b>Lloji:</b> ${a.lloji}</p>
        <p><b>Çmimi:</b> €${a.cmimi}</p>
        <p><b>Metoda:</b> ${a.payment_method}</p>
        <p><b>Fillimi:</b> ${a.data_fillimit}</p>
        <p><b>Mbarimi:</b> ${a.data_mbarimit}</p>
        <p><b>Pagesa e ardhshme:</b> ${a.next_payment}</p>
        <p><b>Statusi:</b> ${a.status}</p>
        <hr>
        <h3 style='color:#059669;'><i class='fas fa-list'></i> Transaksionet</h3>
        <ul style='max-height:200px;overflow:auto;'>`;
    transaksione.filter(t => t.abonim_id == id).forEach(t => {
        html += `<li><b>${t.payment_date}</b> - €${t.amount} - <span style='color:${t.payment_status=='sukses'?'#059669':'#ef4444'}'>${t.payment_status}</span></li>`;
    });
    html += `</ul></div>`;
    var modal = document.createElement('div');
    modal.id = 'abonim-modal';
    modal.style.position = 'fixed';
    modal.style.top = '0';
    modal.style.left = '0';
    modal.style.width = '100vw';
    modal.style.height = '100vh';
    modal.style.background = 'rgba(30,64,175,0.12)';
    modal.style.zIndex = '9999';
    modal.innerHTML = `<div style='background:#fff;max-width:400px;margin:5% auto;padding:2rem;border-radius:1rem;box-shadow:0 8px 32px rgba(30,64,175,0.18);position:relative;'>${html}<button onclick='document.body.removeChild(document.getElementById("abonim-modal"))' style='position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.5rem;color:#ef4444;cursor:pointer;'><i class='fas fa-times'></i></button></div>`;
    document.body.appendChild(modal);
}
</script>
</body>
</html>
