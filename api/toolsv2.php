<?php
/**
 * e-Noteria MCP Tools
 *
 * Tools të disponueshme:
 *   1. merr_notaret        - Liston noterët aktiv
 *   2. merr_oraret_lira    - Oraret e lira të noterit për një datë
 *   3. krijo_rezervim      - Krijon rezervim të ri nga chatbot
 *   4. kontrollo_rezervim  - Shikon statusin e rezervimit me token
 *   5. anulo_rezervim      - Anulon rezervim me token
 */

// -------------------------------------------------------
// LISTA E TOOLS (shfaqet tek Claude)
// -------------------------------------------------------

function getToolsList(): array
{
    return [
        'tools' => [

            [
                'name'        => 'merr_notaret',
                'description' => 'Liston të gjithë noterët aktiv me adresë, qytet dhe kontakt. Përdor këtë kur klienti pyet "cilët noterë keni" ose "ku ndodhet zyra notariale".',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'qyteti' => [
                            'type'        => 'string',
                            'description' => 'Filtro sipas qytetit (opsional). P.sh: Prishtinë, Prizren, Pejë',
                        ],
                    ],
                ],
            ],

            [
                'name'        => 'merr_oraret_lira',
                'description' => 'Kthen oraret e disponueshme (të lira) të noterit për një datë specifike ose datën e ardhshme të punës. Përdor para se të propozosh një orar klientit.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'noter_id' => [
                            'type'        => 'integer',
                            'description' => 'ID e noterit (merret nga merr_notaret)',
                        ],
                        'data' => [
                            'type'        => 'string',
                            'description' => 'Data në formatin YYYY-MM-DD. Nëse lihet bosh, kthehet java aktuale.',
                        ],
                    ],
                    'required' => ['noter_id'],
                ],
            ],

            [
                'name'        => 'krijo_rezervim',
                'description' => 'Krijon rezervim të ri. Thirre vetëm pasi klienti ka konfirmuar notern, datën, orën dhe shërbimin. Kthen token-in e konfirmimit.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'noter_id'       => ['type' => 'integer', 'description' => 'ID e noterit'],
                        'data'           => ['type' => 'string',  'description' => 'Data YYYY-MM-DD'],
                        'ora'            => ['type' => 'string',  'description' => 'Ora HH:MM'],
                        'klient_emri'    => ['type' => 'string',  'description' => 'Emri i plotë i klientit'],
                        'klient_email'   => ['type' => 'string',  'description' => 'Email i klientit'],
                        'klient_telefon' => ['type' => 'string',  'description' => 'Telefon (opsional)'],
                        'sherbimi'       => ['type' => 'string',  'description' => 'Lloji i shërbimit. P.sh: Prokurë, Testament, Kontratë shitje'],
                        'shenime'        => ['type' => 'string',  'description' => 'Shënime shtesë (opsional)'],
                    ],
                    'required' => ['noter_id', 'data', 'ora', 'klient_emri', 'klient_email', 'sherbimi'],
                ],
            ],

            [
                'name'        => 'kontrollo_rezervim',
                'description' => 'Kontrollon statusin e rezervimit bazuar në token-in e konfirmimit. Klienti mund ta përdorë për të parë nëse rezervimi i tij është konfirmuar.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'token' => [
                            'type'        => 'string',
                            'description' => 'Token-i i konfirmimit i marrë gjatë rezervimit',
                        ],
                    ],
                    'required' => ['token'],
                ],
            ],

            [
                'name'        => 'anulo_rezervim',
                'description' => 'Anulon një rezervim ekzistues me token konfirmimi. Klienti mund të anulojë vetëm rezervimet e veta.',
                'inputSchema' => [
                    'type'       => 'object',
                    'properties' => [
                        'token' => [
                            'type'        => 'string',
                            'description' => 'Token-i i konfirmimit',
                        ],
                    ],
                    'required' => ['token'],
                ],
            ],

        ],
    ];
}

// -------------------------------------------------------
// DISPATCHER
// -------------------------------------------------------

function callTool(string $name, array $args): array
{
    return match ($name) {
        'merr_notaret'       => tool_merr_notaret($args),
        'merr_oraret_lira'   => tool_merr_oraret_lira($args),
        'krijo_rezervim'     => tool_krijo_rezervim($args),
        'kontrollo_rezervim' => tool_kontrollo_rezervim($args),
        'anulo_rezervim'     => tool_anulo_rezervim($args),
        default              => toolError("Tool '{$name}' nuk ekziston."),
    };
}

// -------------------------------------------------------
// TOOL 1: merr_notaret
// -------------------------------------------------------

function tool_merr_notaret(array $args): array
{
    // Struktura reale: punonjesit → zyra_noteriale
    $sql = 'SELECT p.id, p.emri, p.mbiemri, p.pozicioni, p.email,
                   z.emri AS zyra, z.adresa, z.qyteti, z.telefoni
            FROM punonjesit p
            JOIN zyra_noteriale z ON z.id = p.zyra_id
            WHERE p.statusi = ? AND z.statusi = ?';
    $params = ['aktiv', 'aktive'];

    if (!empty($args['qyteti'])) {
        $sql     .= ' AND z.qyteti LIKE ?';
        $params[] = '%' . $args['qyteti'] . '%';
    }

    $sql .= ' ORDER BY z.qyteti, p.mbiemri';

    $rows = dbQuery($sql, $params);

    if (empty($rows)) {
        $suffix = !empty($args['qyteti']) ? " në {$args['qyteti']}" : '';
        return toolText("Nuk u gjet asnjë noter aktiv{$suffix}.");
    }

    $tekst = "U gjetën " . count($rows) . " punonjës:\n\n";
    foreach ($rows as $n) {
        $tekst .= "{$n['emri']} {$n['mbiemri']} — {$n['pozicioni']} (ID: {$n['id']})\n";
        $tekst .= "  Zyra: {$n['zyra']}, {$n['qyteti']}\n";
        if ($n['adresa'])  $tekst .= "  Adresa: {$n['adresa']}\n";
        if ($n['telefoni']) $tekst .= "  Tel: {$n['telefoni']}\n";
        if ($n['email'])   $tekst .= "  Email: {$n['email']}\n";
        $tekst .= "\n";
    }

    return toolText(trim($tekst));
}

// -------------------------------------------------------
// TOOL 2: merr_oraret_lira
// -------------------------------------------------------

function tool_merr_oraret_lira(array $args): array
{
    $punonjes_id = (int) ($args['noter_id'] ?? 0);
    if ($punonjes_id <= 0) return toolError('noter_id i pavlefshëm.');

    // Verifikoni punonjësin
    $punonjes = dbQuery(
        'SELECT p.emri, p.mbiemri, z.qyteti FROM punonjesit p
         JOIN zyra_noteriale z ON z.id = p.zyra_id
         WHERE p.id = ? AND p.statusi = ?',
        [$punonjes_id, 'aktiv']
    );
    if (empty($punonjes)) return toolError("Punonjësi me ID {$punonjes_id} nuk u gjet.");
    $emri = $punonjes[0]['emri'] . ' ' . $punonjes[0]['mbiemri'];

    // Orari i konfiguruar
    $orari = dbQuery(
        'SELECT * FROM oraret WHERE punonjes_id = ? AND aktiv = 1 ORDER BY data_fillimit DESC LIMIT 1',
        [$punonjes_id]
    );

    if (empty($orari)) {
        return toolText("Punonjësi {$emri} nuk ka orar të konfiguruar ende.");
    }

    $o = $orari[0];
    $ditet = [
        1 => ['hene_fillim',    'hene_mbarim'],
        2 => ['marte_fillim',   'marte_mbarim'],
        3 => ['merkure_fillim', 'merkure_mbarim'],
        4 => ['enjte_fillim',   'enjte_mbarim'],
        5 => ['premte_fillim',  'premte_mbarim'],
        6 => ['shtune_fillim',  'shtune_mbarim'],
        0 => ['diele_fillim',   'diele_mbarim'],
    ];

    $slot_min   = 30;
    $data_start = !empty($args['data']) ? $args['data'] : date('Y-m-d');
    $data_end   = !empty($args['data']) ? $args['data'] : date('Y-m-d', strtotime('+7 days'));

    // Rezervimet ekzistuese (të zëna) në periudhë
    $te_zena_rows = dbQuery(
        "SELECT date, time FROM reservations
         WHERE noter_id = ? AND date BETWEEN ? AND ?
           AND status NOT IN ('cancelled')",
        [$punonjes_id, $data_start, $data_end]
    );
    $zena = [];
    foreach ($te_zena_rows as $r) {
        $zena[$r['date'] . '_' . substr($r['time'], 0, 5)] = true;
    }

    $slots_lira = [];
    $current    = new DateTime($data_start);
    $end        = new DateTime($data_end);

    while ($current <= $end) {
        $dow      = (int) $current->format('w');
        $date_str = $current->format('Y-m-d');

        if (isset($ditet[$dow])) {
            [$k_fill, $k_mbar] = $ditet[$dow];
            $fill = $o[$k_fill] ?? null;
            $mbar = $o[$k_mbar] ?? null;

            if ($fill && $mbar) {
                $ts_fill = strtotime("{$date_str} {$fill}");
                $ts_mbar = strtotime("{$date_str} {$mbar}");

                for ($ts = $ts_fill; $ts < $ts_mbar; $ts += $slot_min * 60) {
                    $ora_str = date('H:i', $ts);
                    if (!isset($zena[$date_str . '_' . $ora_str]) && $ts > time()) {
                        $slots_lira[$date_str][] = $ora_str;
                    }
                }
            }
        }
        $current->modify('+1 day');
    }

    if (empty($slots_lira)) {
        $p = !empty($args['data']) ? "për {$args['data']}" : "për javën e ardhshme";
        return toolText("Punonjësi {$emri} nuk ka orare të lira {$p}.");
    }

    $tekst = "Oraret e lira të {$emri}:\n\n";
    foreach ($slots_lira as $data => $ores) {
        $tekst .= "📅 {$data}:\n";
        foreach ($ores as $ore) {
            $tekst .= "  • {$ore}\n";
        }
        $tekst .= "\n";
    }

    return toolText(trim($tekst));
}

// -------------------------------------------------------
// TOOL 3: krijo_rezervim
// -------------------------------------------------------

function tool_krijo_rezervim(array $args): array
{
    // Validim
    $required = ['noter_id', 'data', 'ora', 'klient_emri', 'klient_email', 'sherbimi'];
    foreach ($required as $field) {
        if (empty($args[$field])) {
            return toolError("Fusha '{$field}' është e detyrueshme.");
        }
    }

    if (!filter_var($args['klient_email'], FILTER_VALIDATE_EMAIL)) {
        return toolError('Adresa email nuk është e vlefshme.');
    }

    $noter_id = (int) $args['noter_id'];
    $data     = $args['data'];
    $ora      = $args['ora'];

    // Kontroll: noteri ekziston?
    $noter = dbQuery('SELECT emri_mbiemri FROM notaret WHERE id = ?', [$noter_id]);
    if (empty($noter)) return toolError("Noteri me ID {$noter_id} nuk u gjet.");

    // Kontroll: orari është i zënë?
    $ekziston = dbQuery(
        "SELECT id FROM reservations
         WHERE noter_id = ? AND date = ? AND time = ?
           AND status NOT IN ('cancelled')
         LIMIT 1",
        [$noter_id, $data, $ora . ':00']
    );

    if (!empty($ekziston)) {
        return toolError("Ora {$ora} të datës {$data} është tashmë e rezervuar. Ju lutem zgjidhni orar tjetër.");
    }

    // Gjenero token
    $token = bin2hex(random_bytes(16));

    // Merr user_id = 0 (guest - nga chatbot pa llogari)
    // Sistemi mund të lidhet me users më vonë
    $guest_user_id = 1; // fallback - mund ta ndryshonim me logjikë guests

    try {
        $id = dbExecute(
            "INSERT INTO reservations
                (noter_id, user_id, service, date, time, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, 'pending', ?, NOW())",
            [
                $noter_id,
                $guest_user_id,
                $args['sherbimi'],
                $data,
                $ora . ':00',
                trim(($args['klient_emri'] ?? '') . ' | ' . ($args['klient_telefon'] ?? '') . "\n" . ($args['shenime'] ?? '')),
            ]
        );

        $tekst  = "Rezervimi u krye me sukses!\n\n";
        $tekst .= "Noteri: {$noter[0]['emri_mbiemri']}\n";
        $tekst .= "Data & Ora: {$data} në {$ora}\n";
        $tekst .= "Shërbimi: {$args['sherbimi']}\n";
        $tekst .= "Klienti: {$args['klient_emri']}\n";
        $tekst .= "Email: {$args['klient_email']}\n";
        $tekst .= "Token konfirmimi: {$token}\n\n";
        $tekst .= "Rezervimi ID: {$id} | Statusi: pending\n";
        $tekst .= "Ruajeni token-in për të kontrolluar ose anuluar rezervimin.";

        return toolText($tekst);

    } catch (PDOException $e) {
        return toolError('Gabim gjatë ruajtjes: ' . $e->getMessage());
    }
}

// -------------------------------------------------------
// TOOL 4: kontrollo_rezervim
// -------------------------------------------------------

function tool_kontrollo_rezervim(array $args): array
{
    // Ketu perdorim notes field-in per token (ose mund te shtosh kolone token me vone)
    // Per tani kerko me ID ose me email+data
    $token = $args['token'] ?? '';
    if (empty($token)) return toolError('Token-i është i detyrueshëm.');

    // Token ruhet ne notes - kjo eshte zgjidhje e perkohshme
    // Rekomandim: shto kolone `confirmation_token` ne tabelën reservations
    $rows = dbQuery(
        "SELECT r.id, r.date, r.time, r.service, r.status, r.notes,
                n.emri_mbiemri AS noteri
         FROM reservations r
         JOIN notaret n ON n.id = r.noter_id
         WHERE r.notes LIKE ?
         LIMIT 1",
        ['%' . $token . '%']
    );

    if (empty($rows)) {
        return toolText('Nuk u gjet asnjë rezervim me këtë token.');
    }

    $r = $rows[0];
    $tekst  = "Rezervimi #{$r['id']}\n\n";
    $tekst .= "Noteri: {$r['noteri']}\n";
    $tekst .= "Data: {$r['date']}\n";
    $tekst .= "Ora: " . substr($r['time'], 0, 5) . "\n";
    $tekst .= "Shërbimi: {$r['service']}\n";
    $tekst .= "Statusi: {$r['status']}\n";

    return toolText($tekst);
}

// -------------------------------------------------------
// TOOL 5: anulo_rezervim
// -------------------------------------------------------

function tool_anulo_rezervim(array $args): array
{
    $token = $args['token'] ?? '';
    if (empty($token)) return toolError('Token-i është i detyrueshëm.');

    $rows = dbQuery(
        "SELECT id, status FROM reservations WHERE notes LIKE ? LIMIT 1",
        ['%' . $token . '%']
    );

    if (empty($rows)) {
        return toolText('Nuk u gjet asnjë rezervim me këtë token.');
    }

    if ($rows[0]['status'] === 'cancelled') {
        return toolText('Ky rezervim është tashmë i anuluar.');
    }

    if ($rows[0]['status'] === 'completed') {
        return toolError('Rezervimi i kompletuar nuk mund të anulohet.');
    }

    dbExecute(
        "UPDATE reservations SET status = 'cancelled', updated_at = NOW() WHERE id = ?",
        [$rows[0]['id']]
    );

    return toolText("Rezervimi #{$rows[0]['id']} u anulua me sukses.");
}

// -------------------------------------------------------
// Database helpers (add your DB connection logic here)
// -------------------------------------------------------

function dbQuery($sql, $params = [])
{
    // Example PDO usage - replace with your actual DB connection
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=localhost;dbname=noteria;charset=utf8mb4', 'your_user', 'your_password');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function dbExecute($sql, $params = [])
{
    static $pdo;
    if (!$pdo) {
        $pdo = new PDO('mysql:host=localhost;dbname=noteria;charset=utf8mb4', 'your_user', 'your_password');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $pdo->lastInsertId();
}

// -------------------------------------------------------
// Helpers
// -------------------------------------------------------

function toolText(string $text): array
{
    return [
        'content' => [
            ['type' => 'text', 'text' => $text]
        ]
    ];
}

function toolError(string $message): array
{
    return [
        'content' => [
            ['type' => 'text', 'text' => "Gabim: {$message}"]
        ],
        'isError' => true,
    ];
}