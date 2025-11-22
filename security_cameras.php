<?php
session_start();
require_once 'confidb.php';
require_once 'includes/security_functions.php';

// Check if user is logged in and has admin rights
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$title = 'Sistemi i Kamerave të Sigurisë - Noteria';
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Get active cameras
$cameras = getActiveSecurityCameras($pdo);

// Get recent alerts
$recentAlerts = getUnprocessedSecurityAlerts($pdo, 5);

// For the current user
$user_id = $_SESSION['user_id'];
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/security-cameras.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="shortcut icon" href="img/favicon.ico" type="image/x-icon">
</head>
<body>
    <div class="admin-container">
        <!-- Side Menu -->
        <?php include 'includes/admin_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h2>Sistemi i Kamerave të Sigurisë</h2>
                <div class="breadcrumb">
                    <span>Noteria</span>
                    <span>Admin</span>
                    <span>Kamerat e Sigurisë</span>
                </div>
            </div>
            
            <!-- Success/Error messages -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Security Camera Dashboard -->
            <div class="security-dashboard">
                <div class="camera-controls">
                    <div class="camera-selector">
                        <h3>Kamerat e Disponueshme</h3>
                        <div class="camera-list">
                            <?php foreach ($cameras as $camera): ?>
                                <div class="camera-item" data-camera-id="<?php echo $camera['id']; ?>">
                                    <div class="camera-status <?php echo $camera['status']; ?>"></div>
                                    <div class="camera-name"><?php echo htmlspecialchars($camera['name']); ?></div>
                                    <div class="camera-location"><?php echo htmlspecialchars($camera['location']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button id="add-camera-btn" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Shto Kamerë
                        </button>
                    </div>
                    
                    <div class="camera-actions">
                        <button id="record-btn" class="btn btn-danger" disabled>
                            <i class="fas fa-record-vinyl"></i> Incizon
                        </button>
                        <button id="screenshot-btn" class="btn btn-secondary" disabled>
                            <i class="fas fa-camera"></i> Shkrepje
                        </button>
                        <button id="fullscreen-btn" class="btn btn-secondary" disabled>
                            <i class="fas fa-expand"></i> Ekran të plotë
                        </button>
                        <button id="config-btn" class="btn btn-secondary" disabled>
                            <i class="fas fa-cog"></i> Konfigurime
                        </button>
                    </div>
                </div>
                
                <div class="camera-view-container">
                    <div id="camera-view" class="camera-view">
                        <div class="no-camera-selected">
                            <i class="fas fa-video-slash"></i>
                            <p>Zgjidhni një kamerë për të shikuar</p>
                        </div>
                    </div>
                    <div class="camera-info">
                        <div id="camera-name">Asnjë kamerë e zgjedhur</div>
                        <div id="camera-status">Status: N/A</div>
                        <div id="recording-status">Incizim: Jo</div>
                    </div>
                </div>
                
                <div class="alerts-panel">
                    <h3>Alarmet e Fundit</h3>
                    <div class="alerts-list">
                        <?php if (empty($recentAlerts)): ?>
                            <div class="no-alerts">
                                <i class="fas fa-check-circle"></i>
                                <p>Nuk ka alarme të reja</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recentAlerts as $alert): ?>
                                <div class="alert-item alert-level-<?php echo $alert['alert_level']; ?>">
                                    <div class="alert-icon">
                                        <?php 
                                        $icon = 'exclamation-triangle';
                                        if ($alert['alert_type'] === 'motion') $icon = 'running';
                                        if ($alert['alert_type'] === 'person') $icon = 'user';
                                        if ($alert['alert_type'] === 'vehicle') $icon = 'car';
                                        if ($alert['alert_type'] === 'offline') $icon = 'power-off';
                                        ?>
                                        <i class="fas fa-<?php echo $icon; ?>"></i>
                                    </div>
                                    <div class="alert-details">
                                        <div class="alert-camera"><?php echo htmlspecialchars($alert['camera_name']); ?></div>
                                        <div class="alert-type"><?php echo ucfirst($alert['alert_type']); ?> Alert</div>
                                        <div class="alert-time"><?php echo date('d M Y H:i:s', strtotime($alert['alert_time'])); ?></div>
                                    </div>
                                    <div class="alert-actions">
                                        <button class="btn btn-sm btn-primary view-alert" data-alert-id="<?php echo $alert['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-success resolve-alert" data-alert-id="<?php echo $alert['id']; ?>">
                                            <i class="fas fa-check"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Add Camera Modal -->
            <div id="add-camera-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>Shto Kamerë të Re</h3>
                    <form id="add-camera-form">
                        <div class="form-group">
                            <label for="camera-name">Emri i Kamerës</label>
                            <input type="text" id="camera-name" name="camera_name" required>
                        </div>
                        <div class="form-group">
                            <label for="camera-location">Vendndodhja</label>
                            <input type="text" id="camera-location" name="camera_location" required>
                        </div>
                        <div class="form-group">
                            <label for="camera-url">URL / RTSP Stream</label>
                            <input type="text" id="camera-url" name="camera_url" required>
                        </div>
                        <div class="form-group">
                            <label for="camera-type">Lloji i Kamerës</label>
                            <select id="camera-type" name="camera_type">
                                <option value="ip">IP Camera</option>
                                <option value="rtsp">RTSP Stream</option>
                                <option value="onvif">ONVIF</option>
                                <option value="webcam">Webcam</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <div class="form-group half">
                                <label for="camera-username">Përdoruesi</label>
                                <input type="text" id="camera-username" name="camera_username">
                            </div>
                            <div class="form-group half">
                                <label for="camera-password">Fjalëkalimi</label>
                                <input type="password" id="camera-password" name="camera_password">
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="camera-zyra">Zyra</label>
                            <select id="camera-zyra" name="camera_zyra">
                                <?php
                                // Fetch offices for dropdown
                                $stmt = $pdo->query("SELECT id, emri FROM zyrat ORDER BY emri");
                                while ($zyra = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo '<option value="' . $zyra['id'] . '">' . htmlspecialchars($zyra['emri']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Opsionet</label>
                            <div class="checkbox-group">
                                <label>
                                    <input type="checkbox" name="enable_motion" checked> Aktivizo detektimin e lëvizjeve
                                </label>
                                <label>
                                    <input type="checkbox" name="enable_recording"> Aktivizo incizimin automatik
                                </label>
                                <label>
                                    <input type="checkbox" name="enable_alerts" checked> Aktivizo alarmet
                                </label>
                            </div>
                        </div>
                        <div class="modal-actions">
                            <button type="submit" class="btn btn-primary">Ruaj Kamerën</button>
                            <button type="button" class="btn btn-secondary close-btn">Anullo</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Camera Config Modal -->
            <div id="config-camera-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h3>Konfigurimi i Kamerës</h3>
                    <form id="config-camera-form">
                        <input type="hidden" id="config-camera-id" name="camera_id">
                        
                        <div class="config-tabs">
                            <div class="tab-headers">
                                <div class="tab-header active" data-tab="general">Të përgjithshme</div>
                                <div class="tab-header" data-tab="motion">Detektimi i lëvizjeve</div>
                                <div class="tab-header" data-tab="recording">Incizimi</div>
                                <div class="tab-header" data-tab="alerts">Alarmet</div>
                                <div class="tab-header" data-tab="advanced">Avancuar</div>
                            </div>
                            
                            <div class="tab-content active" id="general-tab">
                                <div class="form-group">
                                    <label for="config-camera-name">Emri i Kamerës</label>
                                    <input type="text" id="config-camera-name" name="camera_name" required>
                                </div>
                                <div class="form-group">
                                    <label for="config-camera-location">Vendndodhja</label>
                                    <input type="text" id="config-camera-location" name="camera_location" required>
                                </div>
                                <div class="form-group">
                                    <label for="config-camera-url">URL / RTSP Stream</label>
                                    <input type="text" id="config-camera-url" name="camera_url" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-group half">
                                        <label for="config-camera-username">Përdoruesi</label>
                                        <input type="text" id="config-camera-username" name="camera_username">
                                    </div>
                                    <div class="form-group half">
                                        <label for="config-camera-password">Fjalëkalimi</label>
                                        <input type="password" id="config-camera-password" name="camera_password">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="config-camera-status">Statusi</label>
                                    <select id="config-camera-status" name="camera_status">
                                        <option value="active">Aktiv</option>
                                        <option value="inactive">Joaktiv</option>
                                        <option value="maintenance">Në mirëmbajtje</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="tab-content" id="motion-tab">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" id="config-motion-enabled" name="motion_enabled">
                                        Aktivizo detektimin e lëvizjeve
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label for="config-motion-sensitivity">Ndjeshmëria</label>
                                    <input type="range" id="config-motion-sensitivity" name="motion_sensitivity" min="1" max="10" value="5">
                                    <div class="range-value">5</div>
                                </div>
                                <div class="form-group">
                                    <label for="config-motion-zones">Zonat e detektimit</label>
                                    <div id="motion-zones-editor" class="motion-zones-editor">
                                        <!-- This will be filled with JavaScript -->
                                        <div class="zone-canvas-container">
                                            <canvas id="zone-canvas"></canvas>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Opsionet e detektimit</label>
                                    <div class="checkbox-group">
                                        <label>
                                            <input type="checkbox" name="detect_persons" checked> Detekto personat
                                        </label>
                                        <label>
                                            <input type="checkbox" name="detect_vehicles"> Detekto automjetet
                                        </label>
                                        <label>
                                            <input type="checkbox" name="detect_animals"> Detekto kafshët
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="config-motion-cooldown">Koha e pushimit mes detektimeve (sekonda)</label>
                                    <input type="number" id="config-motion-cooldown" name="motion_cooldown" min="0" value="10">
                                </div>
                            </div>
                            
                            <div class="tab-content" id="recording-tab">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" id="config-recording-enabled" name="recording_enabled">
                                        Aktivizo incizimin automatik
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Mënyra e incizimit</label>
                                    <div class="radio-group">
                                        <label>
                                            <input type="radio" name="recording_mode" value="continuous" checked> I vazhdueshëm
                                        </label>
                                        <label>
                                            <input type="radio" name="recording_mode" value="motion"> Vetëm me lëvizje
                                        </label>
                                        <label>
                                            <input type="radio" name="recording_mode" value="scheduled"> Sipas orarit
                                        </label>
                                    </div>
                                </div>
                                <div id="recording-schedule-container" class="form-group" style="display: none;">
                                    <label>Orari i incizimit</label>
                                    <div class="schedule-editor">
                                        <!-- This will be filled with JavaScript -->
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="config-recording-quality">Cilësia e incizimit</label>
                                    <select id="config-recording-quality" name="recording_quality">
                                        <option value="low">E ulët (480p)</option>
                                        <option value="medium" selected>Mesatare (720p)</option>
                                        <option value="high">E lartë (1080p)</option>
                                        <option value="original">Origjinale</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="config-recording-retention">Ruajtja e incizimeve (ditë)</label>
                                    <input type="number" id="config-recording-retention" name="recording_retention" min="1" value="7">
                                </div>
                            </div>
                            
                            <div class="tab-content" id="alerts-tab">
                                <div class="form-group">
                                    <label>
                                        <input type="checkbox" id="config-alerts-enabled" name="alerts_enabled">
                                        Aktivizo alarmet
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Llojet e alarmeve</label>
                                    <div class="checkbox-group">
                                        <label>
                                            <input type="checkbox" name="alert_motion" checked> Lëvizje
                                        </label>
                                        <label>
                                            <input type="checkbox" name="alert_person" checked> Person
                                        </label>
                                        <label>
                                            <input type="checkbox" name="alert_vehicle"> Automjet
                                        </label>
                                        <label>
                                            <input type="checkbox" name="alert_offline" checked> Kamera offline
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Metodat e njoftimit</label>
                                    <div class="checkbox-group">
                                        <label>
                                            <input type="checkbox" name="notify_dashboard" checked> Panel kontrolli
                                        </label>
                                        <label>
                                            <input type="checkbox" name="notify_email"> Email
                                        </label>
                                        <label>
                                            <input type="checkbox" name="notify_sms"> SMS
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="config-alert-recipients">Marrësit e njoftimeve</label>
                                    <textarea id="config-alert-recipients" name="alert_recipients" placeholder="Email-at e ndarë me presje"></textarea>
                                </div>
                                <div class="form-group">
                                    <label for="config-alert-cooldown">Koha e pushimit mes alarmeve (minuta)</label>
                                    <input type="number" id="config-alert-cooldown" name="alert_cooldown" min="0" value="5">
                                </div>
                            </div>
                            
                            <div class="tab-content" id="advanced-tab">
                                <div class="form-group">
                                    <label for="config-camera-protocol">Protokoli</label>
                                    <select id="config-camera-protocol" name="camera_protocol">
                                        <option value="rtsp">RTSP</option>
                                        <option value="http">HTTP</option>
                                        <option value="https">HTTPS</option>
                                        <option value="onvif">ONVIF</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="config-camera-port">Porti</label>
                                    <input type="number" id="config-camera-port" name="camera_port" placeholder="554 for RTSP, 80 for HTTP">
                                </div>
                                <div class="form-group">
                                    <label for="config-camera-path">Shtegu</label>
                                    <input type="text" id="config-camera-path" name="camera_path" placeholder="/stream/channel/0">
                                </div>
                                <div class="form-group">
                                    <label for="config-camera-fps">FPS</label>
                                    <input type="number" id="config-camera-fps" name="camera_fps" min="1" max="30" value="15">
                                </div>
                                <div class="form-group">
                                    <label>Opsionet shtesë</label>
                                    <div class="checkbox-group">
                                        <label>
                                            <input type="checkbox" name="use_ssl"> Përdor SSL
                                        </label>
                                        <label>
                                            <input type="checkbox" name="verify_cert"> Verifiko certifikatat
                                        </label>
                                        <label>
                                            <input type="checkbox" name="reconnect_automatically" checked> Rilidhu automatikisht
                                        </label>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="config-camera-custom">Parametra të personalizuar (JSON)</label>
                                    <textarea id="config-camera-custom" name="camera_custom" placeholder='{"param1": "value1", "param2": "value2"}'></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="modal-actions">
                            <button type="submit" class="btn btn-primary">Ruaj Ndryshimet</button>
                            <button type="button" class="btn btn-danger delete-camera">Fshi Kamerën</button>
                            <button type="button" class="btn btn-secondary close-btn">Anullo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert View Modal -->
    <div id="alert-view-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Detajet e Alarmit</h3>
            <div class="alert-view-content">
                <div class="alert-image">
                    <img id="alert-image" src="" alt="Alert Image">
                </div>
                <div class="alert-info">
                    <div class="alert-info-row">
                        <span class="label">Kamera:</span>
                        <span id="alert-camera-name"></span>
                    </div>
                    <div class="alert-info-row">
                        <span class="label">Lloji:</span>
                        <span id="alert-type"></span>
                    </div>
                    <div class="alert-info-row">
                        <span class="label">Niveli:</span>
                        <span id="alert-level"></span>
                    </div>
                    <div class="alert-info-row">
                        <span class="label">Koha:</span>
                        <span id="alert-time"></span>
                    </div>
                </div>
            </div>
            <div class="modal-actions">
                <button id="goto-camera-btn" class="btn btn-primary">Shiko Kamerën</button>
                <button id="resolve-alert-btn" class="btn btn-success">Shëno si të zgjidhur</button>
                <button type="button" class="btn btn-secondary close-btn">Mbyll</button>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="js/security-cameras.js"></script>
</body>
</html>