<?php
// Include performance monitoring
require_once 'performance.php';

// Include advanced security system
require_once 'security.php';

// Initialize security system
initSecurity();

// Session is already started by security system
// session_start();
$is_logged_in = isset($_SESSION['user_id']);

// Simulated service history data
$service_history = [
    [
        'id' => 'SRV-2026-001',
        'date' => '2026-02-08',
        'time' => '14:30',
        'office' => 'Notaria "Jusaj" - Prishtinë',
        'service' => 'Notarizim Kontrate Shitblerje',
        'amount' => '50€',
        'status' => 'Përfunduar',
        'document' => 'Kontrata_Shitblerje_2026.pdf',
        'notes' => 'Notarizim i kryer me sukses'
    ],
    [
        'id' => 'SRV-2026-002',
        'date' => '2026-02-05',
        'time' => '11:00',
        'office' => 'Notaria "Krasniqi" - Prizren',
        'service' => 'Verifikim Dokumenti',
        'amount' => '25€',
        'status' => 'Përfunduar',
        'document' => 'Deshmi_Verifikimi.pdf',
        'notes' => 'Dokumenti i vlefshëm'
    ],
    [
        'id' => 'SRV-2026-003',
        'date' => '2026-02-02',
        'time' => '09:15',
        'office' => 'Notaria "Ahmeti" - Ferizaj',
        'service' => 'Procurë',
        'amount' => '35€',
        'status' => 'Përfunduar',
        'document' => 'Procura_2026.pdf',
        'notes' => 'Procurë e përfunduar'
    ],
    [
        'id' => 'SRV-2026-004',
        'date' => '2026-01-28',
        'time' => '16:45',
        'office' => 'Notaria "Jusaj" - Prishtinë',
        'service' => 'Testamenti',
        'amount' => '60€',
        'status' => 'Përfunduar',
        'document' => 'Testamenti_Original.pdf',
        'notes' => 'Testamenti i vërtetuar'
    ],
    [
        'id' => 'SRV-2026-005',
        'date' => '2026-01-20',
        'time' => '10:30',
        'office' => 'Notaria "Llapi" - Gjakovë',
        'service' => 'Hyposek Letash',
        'amount' => '40€',
        'status' => 'Përfunduar',
        'document' => 'Hyposek_Letash.pdf',
        'notes' => 'Letrat e hyposekuara'
    ]
];
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
    <title>Historik Shërbimesh | e-Noteria | Republika e Kosovës</title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        :root {
            --rks-blue: #003366;
            --rks-blue-light: #004080;
            --rks-blue-dark: #002244;
            --rks-gold: #cfa856;
            --rks-gold-light: #e0c078;
            --bg-gray: #f2f4f8;
            --shadow-sm: 0 2px 8px rgba(0, 51, 102, 0.08);
            --shadow-md: 0 8px 24px rgba(0, 51, 102, 0.12);
            --shadow-lg: 0 16px 48px rgba(0, 51, 102, 0.16);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: var(--bg-gray);
            color: #333;
            overflow-x: hidden;
        }

        /* Top Strip */
        .gov-bar {
            background: #fff;
            border-bottom: 1px solid #e1e4e8;
            padding: 8px 0;
            font-size: 13px;
            color: #586069;
        }

        /* Navbar */
        .main-nav {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,51,102,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }
        .main-nav.scrolled {
            padding: 12px 0;
            box-shadow: 0 4px 30px rgba(0,51,102,0.12);
        }
        .brand {
            font-weight: 800;
            font-size: 26px;
            color: var(--rks-blue);
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        .brand .text-primary {
            color: var(--rks-gold) !important;
        }

        /* Hero Section */
        .history-hero {
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 50%, var(--rks-blue-dark) 100%);
            color: white;
            padding: 80px 0 60px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .history-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(207,168,86,0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            50% { transform: translateY(-20px) translateX(10px); }
        }

        /* Filters Section */
        .filters-section {
            padding: 30px 0;
            background: white;
            box-shadow: var(--shadow-sm);
        }
        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-group label {
            font-weight: 600;
            color: var(--rks-blue);
            margin: 0;
            white-space: nowrap;
        }
        .filter-group input,
        .filter-group select {
            border: 2px solid rgba(0,51,102,0.1);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 14px;
            transition: var(--transition);
        }
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: var(--rks-blue);
            box-shadow: 0 0 0 3px rgba(0,51,102,0.1);
            outline: none;
        }

        /* History Section */
        .history-section {
            padding: 60px 0;
        }
        .history-table {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        .history-table table {
            margin: 0;
        }
        .history-table thead {
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
            color: white;
        }
        .history-table thead th {
            padding: 20px;
            font-weight: 600;
            border: none;
            text-align: center;
        }
        .history-table tbody tr {
            border-bottom: 1px solid rgba(0,51,102,0.05);
            transition: var(--transition);
        }
        .history-table tbody tr:hover {
            background-color: rgba(0,51,102,0.02);
        }
        .history-table tbody td {
            padding: 16px 20px;
            vertical-align: middle;
            text-align: center;
        }
        .history-table tbody td:first-child {
            text-align: left;
            font-weight: 600;
            color: var(--rks-blue);
        }
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .service-name {
            font-weight: 600;
            color: var(--rks-blue);
        }
        .office-name {
            color: #666;
            font-size: 13px;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .btn-action {
            width: 36px;
            height: 36px;
            padding: 0;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: var(--transition);
            border: 2px solid rgba(0,51,102,0.1);
            background: white;
            color: var(--rks-blue);
        }
        .btn-action:hover {
            background: var(--rks-blue);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        /* Stats Section */
        .stats-section {
            padding: 40px 0;
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
            color: white;
            border-radius: 16px;
            margin-bottom: 40px;
        }
        .stat-card {
            text-align: center;
            padding: 20px;
        }
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--rks-gold);
        }
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 8px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        .empty-state i {
            font-size: 4rem;
            color: rgba(0,51,102,0.2);
            margin-bottom: 20px;
        }
        .empty-state h3 {
            color: var(--rks-blue);
            margin-bottom: 10px;
        }
        .empty-state p {
            color: #666;
            margin-bottom: 20px;
        }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #8b949e;
            padding: 60px 0 30px;
            position: relative;
            margin-top: 80px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .history-hero {
                padding: 60px 0 40px;
            }
            .history-hero h1 {
                font-size: 2rem;
            }
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-group label {
                display: block;
                margin-bottom: 5px;
            }
            .filter-group input,
            .filter-group select {
                width: 100%;
            }
            .history-table {
                overflow-x: auto;
            }
            .history-table table {
                min-width: 700px;
            }
            .action-buttons {
                flex-direction: column;
            }
            .stat-number {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>

    <!-- Top Strip -->
    <div class="gov-bar">
        <div class="container d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <svg width="24" height="24" viewBox="0 0 100 100" class="me-2">
                    <path d="M50 0 L90 20 L90 50 Q90 80 50 100 Q10 80 10 50 L10 20 Z" fill="#244d96"/>
                    <path d="M30 40 L50 60 L70 40" stroke="#cfa856" stroke-width="5" fill="none"/>
                </svg>
                <span>e-Noteria</span>
            </div>
            <div>
                <a href="#" class="text-decoration-none text-muted me-3">Shqip</a>
                <a href="#" class="text-decoration-none text-muted me-3">Srpski</a>
                <a href="#" class="text-decoration-none text-muted">English</a>
            </div>
        </div>
    </div>

    <!-- Main Nav -->
    <nav class="main-nav">
        <div class="container d-flex justify-content-between align-items-center">
            <a href="index.php" class="brand">e<span class="text-primary">-Noteria</span></a>

            <div class="d-flex align-items-center gap-4 d-none d-md-flex">
                <a href="services.php" class="text-dark text-decoration-none fw-medium">Shërbimet</a>
                <a href="#" class="text-dark text-decoration-none fw-medium">Lajme</a>
                <a href="ndihma.php" class="text-dark text-decoration-none fw-medium">Ndihma</a>
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="btn btn-primary px-4 rounded-pill fw-bold">Dashboard</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-outline-primary px-4 rounded-pill fw-bold">Kyçuni</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="history-hero">
        <div class="container">
            <h1 class="display-4 fw-bold mb-4">Historik i Shërbimeve</h1>
            <p class="lead mb-0">Shihni të gjitha shërbimet e marra dhe shkarkoni dokumentet tuaja</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <div class="container">
            <div class="filter-group">
                <label for="filterDate">📅 Data:</label>
                <input type="date" id="filterDate" placeholder="Filtro sipas datës">
                
                <label for="filterOffice" class="ms-3">🏢 Zyra:</label>
                <select id="filterOffice">
                    <option value="">-- Të gjitha zyrat --</option>
                    <option value="Prishtinë">Prishtinë</option>
                    <option value="Prizren">Prizren</option>
                    <option value="Ferizaj">Ferizaj</option>
                    <option value="Gjakovë">Gjakovë</option>
                </select>

                <label for="filterService" class="ms-3">📋 Shërbimi:</label>
                <select id="filterService">
                    <option value="">-- Të gjithë shërbimet --</option>
                    <option value="Notarizim">Notarizim</option>
                    <option value="Verifikim">Verifikim</option>
                    <option value="Procurë">Procurë</option>
                    <option value="Testamenti">Testamenti</option>
                </select>

                <button class="btn btn-outline-primary ms-auto" id="resetBtn">🔄 Reseto</button>
            </div>
        </div>
    </div>

    <!-- Stats Section -->
    <div class="container history-section">
        <div class="stats-section">
            <div class="row">
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number" id="totalServices">5</div>
                        <div class="stat-label">Shërbime të Marra</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number" id="totalAmount">210€</div>
                        <div class="stat-label">Totali i Paguar</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number" id="completedCount">5</div>
                        <div class="stat-label">Përfunduar</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="stat-card">
                        <div class="stat-number">100%</div>
                        <div class="stat-label">Sukses</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- History Table -->
    <div class="container history-section">
        <div class="history-table">
            <table class="table" id="historyTable">
                <thead>
                    <tr>
                        <th>ID Shërbimi</th>
                        <th>Data & Ora</th>
                        <th>Zyra Noteriale</th>
                        <th>Lloji i Shërbimit</th>
                        <th>Tarifa</th>
                        <th>Statusi</th>
                        <th>Veprime</th>
                    </tr>
                </thead>
                <tbody id="historyBody">
                    <?php foreach ($service_history as $service): ?>
                    <tr>
                        <td><?php echo $service['id']; ?></td>
                        <td>
                            <div><?php echo date('d.m.Y', strtotime($service['date'])); ?></div>
                            <div style="font-size: 12px; color: #999;">⏰ <?php echo $service['time']; ?></div>
                        </td>
                        <td>
                            <div class="service-name"><?php echo explode(' - ', $service['office'])[0]; ?></div>
                            <div class="office-name"><?php echo explode(' - ', $service['office'])[1]; ?></div>
                        </td>
                        <td><span class="service-name"><?php echo $service['service']; ?></span></td>
                        <td><strong><?php echo $service['amount']; ?></strong></td>
                        <td><span class="status-badge"><?php echo $service['status']; ?></span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action" title="Shiko Detalet" onclick="showDetails('<?php echo htmlspecialchars(json_encode($service)); ?>')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-action" title="Shkarko Dokumentin" onclick="downloadDocument('<?php echo $service['document']; ?>')">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn-action" title="Printuaj" onclick="printService('<?php echo $service['id']; ?>')">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%); color: white; border: none;">
                    <h5 class="modal-title">Detalet e Shërbimit</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="detailsContent"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" data-bs-dismiss="modal">Mbyll</button>
                    <button type="button" class="btn btn-primary" onclick="downloadFromModal()">📥 Shkarko Dokumentin</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <span style="font-weight: 800; color: white; font-size: 1.5rem; display: block; margin-bottom: 20px;">e-Noteria</span>
                    <p>Platforma SaaS për zyrat noteriale në Kosovë. Abonim mujor 150€ për akses të plotë.</p>
                </div>
                <div class="col-md-2 col-6 mb-4">
                    <h6 class="text-white mb-3">Linqe</h6>
                    <a href="rrethnesh.php" class="text-decoration-none text-muted d-block mb-2">Rreth Nesh</a>
                    <a href="Privatesia.php" class="text-decoration-none text-muted d-block mb-2">Privatësia</a>
                    <a href="terms.php" class="text-decoration-none text-muted d-block mb-2">Kushtet</a>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <h6 class="text-white mb-3">Shërbime</h6>
                    <a href="services.php" class="text-decoration-none text-muted d-block mb-2">Shërbimet</a>
                    <a href="verifikimi.php" class="text-decoration-none text-muted d-block mb-2">Verifikimi</a>
                    <a href="status.php" class="text-decoration-none text-muted d-block mb-2">Statusi</a>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <h6 class="text-white mb-3">Kontakt</h6>
                    <a href="ndihma.php" class="text-decoration-none text-muted d-block mb-2">Ndihmë</a>
                    <a href="mailto:support@e-noteria.rks-gov.net" class="text-decoration-none text-muted d-block mb-2">support@e-noteria.rks-gov.net</a>
                    <a href="tel:038200100" class="text-decoration-none text-muted d-block mb-2">038 200 100</a>
                </div>
            </div>
            <div class="border-top border-secondary pt-3 text-center mt-3" style="border-color: #555 !important;">
                <small>&copy; <?php echo date('Y'); ?> Republika e Kosovës - Ministria e Drejtësisë</small>
            </div>
        </div>
    </footer>

    <script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        let allServices = <?php echo json_encode($service_history); ?>;
        let currentService = null;

        // Show service details
        function showDetails(serviceJSON) {
            const service = JSON.parse(serviceJSON);
            currentService = service;
            
            const html = `
                <div class="mb-3">
                    <strong>📌 ID Shërbimi:</strong> ${service.id}
                </div>
                <div class="mb-3">
                    <strong>📅 Data & Ora:</strong> ${new Date(service.date).toLocaleDateString('sq-AL')} - ${service.time}
                </div>
                <div class="mb-3">
                    <strong>🏢 Zyra Noteriale:</strong> ${service.office}
                </div>
                <div class="mb-3">
                    <strong>📋 Shërbimi:</strong> ${service.service}
                </div>
                <div class="mb-3">
                    <strong>💰 Tarifa:</strong> ${service.amount}
                </div>
                <div class="mb-3">
                    <strong>✅ Statusi:</strong> <span class="badge" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">${service.status}</span>
                </div>
                <div class="mb-3">
                    <strong>📄 Dokumenti:</strong> ${service.document}
                </div>
                <div class="mb-3">
                    <strong>📝 Shënime:</strong> ${service.notes}
                </div>
            `;
            
            document.getElementById('detailsContent').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailsModal')).show();
        }

        // Download document
        function downloadDocument(filename) {
            alert(`📥 Po shkarkoni dokumentin: ${filename}`);
            // Në prodhim, kjo do të lidhet me një API për shkarkimin real
        }

        function downloadFromModal() {
            if (currentService) {
                downloadDocument(currentService.document);
            }
        }

        // Print service
        function printService(serviceId) {
            alert(`🖨️ Po printoni shërbimin: ${serviceId}`);
            window.print();
        }

        // Filter functionality
        document.getElementById('filterDate').addEventListener('change', filterServices);
        document.getElementById('filterOffice').addEventListener('change', filterServices);
        document.getElementById('filterService').addEventListener('change', filterServices);

        function filterServices() {
            const dateFilter = document.getElementById('filterDate').value;
            const officeFilter = document.getElementById('filterOffice').value;
            const serviceFilter = document.getElementById('filterService').value;

            const filteredServices = allServices.filter(service => {
                const dateMatch = !dateFilter || service.date === dateFilter;
                const officeMatch = !officeFilter || service.office.includes(officeFilter);
                const serviceMatch = !serviceFilter || service.service.includes(serviceFilter);
                return dateMatch && officeMatch && serviceMatch;
            });

            updateTable(filteredServices);
        }

        function updateTable(services) {
            const tbody = document.getElementById('historyBody');
            tbody.innerHTML = '';

            if (services.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-5"><i class="fas fa-search" style="font-size: 2rem; color: rgba(0,51,102,0.2); margin-bottom: 10px; display: block;"></i>Nuk ka shërbime që përputhen me kriteret tuaja</td></tr>';
                document.getElementById('totalServices').textContent = '0';
                document.getElementById('completedCount').textContent = '0';
                document.getElementById('totalAmount').textContent = '0€';
                return;
            }

            services.forEach(service => {
                const row = `
                    <tr>
                        <td>${service.id}</td>
                        <td>
                            <div>${new Date(service.date).toLocaleDateString('sq-AL')}</div>
                            <div style="font-size: 12px; color: #999;">⏰ ${service.time}</div>
                        </td>
                        <td>
                            <div class="service-name">${service.office.split(' - ')[0]}</div>
                            <div class="office-name">${service.office.split(' - ')[1]}</div>
                        </td>
                        <td><span class="service-name">${service.service}</span></td>
                        <td><strong>${service.amount}</strong></td>
                        <td><span class="status-badge">${service.status}</span></td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action" title="Shiko Detalet" onclick="showDetails('${JSON.stringify(service).replace(/'/g, "\\'")}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn-action" title="Shkarko Dokumentin" onclick="downloadDocument('${service.document}')">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn-action" title="Printuaj" onclick="printService('${service.id}')">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });

            // Update stats
            const totalAmount = services.reduce((sum, s) => sum + parseInt(s.amount), 0);
            const completed = services.filter(s => s.status === 'Përfunduar').length;
            
            document.getElementById('totalServices').textContent = services.length;
            document.getElementById('completedCount').textContent = completed;
            document.getElementById('totalAmount').textContent = totalAmount + '€';
        }

        // Reset filters
        document.getElementById('resetBtn').addEventListener('click', function() {
            document.getElementById('filterDate').value = '';
            document.getElementById('filterOffice').value = '';
            document.getElementById('filterService').value = '';
            updateTable(allServices);
        });

        // Navbar scroll effect
        const navbar = document.querySelector('.main-nav');
        window.addEventListener('scroll', function() {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>

    <?php include 'chat-widget.php'; ?>
</body>
</html>


