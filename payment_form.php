<?php
$success = $error = '';
$request_method = $_SERVER['REQUEST_METHOD'] ?? '';
if ($request_method === 'POST') {
    $payer_name = trim($_POST['payer_name'] ?? '');
    $payer_iban = trim($_POST['payer_iban'] ?? '');
    $amount = floatval($_POST['amount'] ?? 0);
    $bank_name = trim($_POST['bank_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $password = $_POST['password'] ?? '';

    // Server-side validation
    if (!$payer_name || !$payer_iban || !$amount || !$bank_name || !$description) {
        $error = 'Ju lutemi plotësoni të gjitha fushat.';
    } elseif (!preg_match('/^XK[0-9]{2}[0-9]{16}$/', $payer_iban)) {
        $error = 'IBAN i pavlefshëm. Formati duhet të jetë: XK + 2 shifra kontrolli + 16 shifra llogarie.';
    } elseif ($amount < 10) {
        $error = 'Shuma minimale është 10€.';
    } else {
        // Këtu mund të shtoni logjikën për ruajtjen në DB ose procesimin e pagesës
        $success = 'Pagesa u krye me sukses!';
    }
}
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forma e Pagesës | Noteria</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e2eafc 0%, #f8fafc 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .payment-card {
            max-width: 480px;
            margin: auto;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(44,108,223,0.12);
            background: #fff;
            padding: 32px 28px;
        }
        .payment-title {
            font-size: 2rem;
            font-weight: 700;
            color: #244d96;
            margin-bottom: 18px;
        }
        .payment-subtitle {
            color: #888;
            font-size: 1.1rem;
            margin-bottom: 24px;
        }
        .form-label {
            font-weight: 600;
            color: #244d96;
        }
        .form-control, .form-select {
            border-radius: 8px;
            font-size: 1.05rem;
            padding: 12px 14px;
        }
        .btn-pay {
            background: linear-gradient(90deg,#244d96 0%,#cfa856 100%);
            color: #fff;
            font-weight: 700;
            border-radius: 10px;
            font-size: 1.15rem;
            padding: 14px 0;
            box-shadow: 0 4px 16px rgba(44,108,223,0.08);
            transition: background 0.2s;
        }
        .btn-pay:hover {
            background: linear-gradient(90deg,#1e3c72 0%,#b8860b 100%);
        }
        .secure {
            color: #388e3c;
            font-size: 1rem;
            margin-bottom: 18px;
        }
        .info {
            color: #184fa3;
            background: #e2eafc;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 18px;
            font-size: 1rem;
            text-align: center;
        }
        .error {
            color: #d32f2f;
            background: #ffeaea;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 18px;
            font-size: 1rem;
            text-align: center;
        }
        .success {
            color: #388e3c;
            background: #eafaf1;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 18px;
            font-size: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="payment-card">
        <div class="text-center mb-4">
            <i class="fas fa-credit-card fa-3x text-primary mb-2"></i>
            <div class="payment-title">Forma e Pagesës</div>
            <div class="payment-subtitle">Transaksionet mbrohen me enkriptim bankar 256-bit SSL.</div>
        </div>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($success): ?>
            <div class="success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <form method="post" autocomplete="off">
            <div class="mb-3">
                <label for="payer_name" class="form-label">Emri dhe Mbiemri</label>
                <input type="text" class="form-control" id="payer_name" name="payer_name" placeholder="Shkruani emrin e plotë" required value="<?= htmlspecialchars($_POST['payer_name'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="payer_iban" class="form-label">IBAN</label>
                <input type="text" class="form-control" id="payer_iban" name="payer_iban" placeholder="Shembull: XK051212012345678901" maxlength="20" required value="<?= htmlspecialchars($_POST['payer_iban'] ?? '') ?>">
                <div class="form-text">Shembull IBAN-i: <b>XK051212012345678901</b> (pa hapësira)</div>
            </div>
            <div class="mb-3">
                <label for="amount" class="form-label">Shuma (€)</label>
                <input type="number" class="form-control" id="amount" name="amount" min="10" step="0.01" placeholder="Shkruani shumën" required value="<?= htmlspecialchars($_POST['amount'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label for="bank_name" class="form-label">Zgjidh Bankën</label>
                <select class="form-select" id="bank_name" name="bank_name" required>
                    <option value="">-- Zgjidhni Bankën --</option>
                    <option value="Banka Ekonomike" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'Banka Ekonomike') ? 'selected' : '' ?>>Banka Ekonomike</option>
                    <option value="BKT" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'BKT') ? 'selected' : '' ?>>Banka Kombëtare Tregtare</option>
                    <option value="Credins" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'Credins') ? 'selected' : '' ?>>Credins Bank</option>
                    <option value="ProCredit" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'ProCredit') ? 'selected' : '' ?>>ProCredit Bank</option>
                    <option value="Raiffeisen" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'Raiffeisen') ? 'selected' : '' ?>>Raiffeisen Bank</option>
                    <option value="NLB" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'NLB') ? 'selected' : '' ?>>NLB Banka</option>
                    <option value="TEB" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'TEB') ? 'selected' : '' ?>>TEB Banka</option>
                    <option value="Paysera" <?= (isset($_POST['bank_name']) && $_POST['bank_name'] == 'Paysera') ? 'selected' : '' ?>>Paysera</option>
                </select>
            </div>
            <div class="mb-3">
                <label for="description" class="form-label">Përshkrimi</label>
                <textarea class="form-control" id="description" name="description" rows="2" placeholder="Përshkruani pagesën" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Fjalëkalimi</label>
                <input type="password" class="form-control" id="password" name="password" placeholder="Lëre bosh nëse nuk do ta ndryshosh">
            </div>
            <button type="submit" class="btn btn-pay w-100 mt-3">
                <i class="fas fa-paper-plane me-2"></i> Paguaj Tani
            </button>
        </form>
    </div>
</body>
</html>

