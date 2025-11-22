// Security Camera System - Front-end JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Global variables
    let currentCameraId = null;
    let cameras = [];
    let recordingState = false;
    
    // DOM elements
    const cameraView = document.getElementById('camera-view');
    const cameraList = document.querySelector('.camera-list');
    const recordBtn = document.getElementById('record-btn');
    const screenshotBtn = document.getElementById('screenshot-btn');
    const fullscreenBtn = document.getElementById('fullscreen-btn');
    const configBtn = document.getElementById('config-btn');
    const addCameraBtn = document.getElementById('add-camera-btn');
    
    // Modal elements
    const addCameraModal = document.getElementById('add-camera-modal');
    const configCameraModal = document.getElementById('config-camera-modal');
    const alertViewModal = document.getElementById('alert-view-modal');
    const closeButtons = document.querySelectorAll('.close, .close-btn');
    
    // Load cameras from API
    function loadCameras() {
        fetch('api_security_cameras.php?action=get_cameras')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    cameras = data.cameras;
                    renderCameraList();
                } else {
                    showNotification('error', data.message || 'Failed to load cameras');
                }
            })
            .catch(error => {
                console.error('Error loading cameras:', error);
                showNotification('error', 'Network error while loading cameras');
            });
    }
    
    // Render camera list
    function renderCameraList() {
        cameraList.innerHTML = '';
        
        if (cameras.length === 0) {
            const noCamera = document.createElement('div');
            noCamera.className = 'no-cameras';
            noCamera.innerHTML = '<i class="fas fa-video-slash"></i><p>No cameras available</p>';
            cameraList.appendChild(noCamera);
            return;
        }
        
        cameras.forEach(camera => {
            const cameraItem = document.createElement('div');
            cameraItem.className = 'camera-item';
            if (camera.id === currentCameraId) {
                cameraItem.classList.add('active');
            }
            cameraItem.dataset.cameraId = camera.id;
            
            cameraItem.innerHTML = `
                <div class="camera-status ${camera.status}"></div>
                <div class="camera-name">${escapeHTML(camera.name)}</div>
                <div class="camera-location">${escapeHTML(camera.location)}</div>
            `;
            
            cameraItem.addEventListener('click', () => {
                selectCamera(camera.id);
            });
            
            cameraList.appendChild(cameraItem);
        });
    }
    
    // Select a camera
    function selectCamera(cameraId) {
        // Remove active class from all camera items
        document.querySelectorAll('.camera-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Add active class to selected camera
        const selectedItem = document.querySelector(`.camera-item[data-camera-id="${cameraId}"]`);
        if (selectedItem) {
            selectedItem.classList.add('active');
        }
        
        currentCameraId = cameraId;
        
        // Get camera details
        const camera = cameras.find(c => c.id === cameraId);
        if (!camera) return;
        
        // Update camera info
        document.getElementById('camera-name').textContent = camera.name;
        document.getElementById('camera-status').textContent = `Status: ${formatStatus(camera.status)}`;
        document.getElementById('recording-status').textContent = `Incizim: Jo`;
        
        // Enable control buttons
        recordBtn.disabled = false;
        screenshotBtn.disabled = false;
        fullscreenBtn.disabled = false;
        configBtn.disabled = false;
        
        // Load camera feed
        loadCameraFeed(camera);
        
        // Log camera access
        logCameraAccess(camera.id, 'view');
    }
    
    // Load camera feed
    function loadCameraFeed(camera) {
        // Clear current view
        cameraView.innerHTML = '';
        
        // For a real implementation, you would connect to an actual video stream here
        // For demo purposes, we'll use a placeholder or simulated feed
        
        if (camera.status === 'active') {
            // For demo: use a video element with a placeholder or static image
            const videoElement = document.createElement('video');
            videoElement.id = 'camera-video';
            videoElement.autoplay = true;
            videoElement.muted = true;
            videoElement.controls = false;
            videoElement.style.maxWidth = '100%';
            videoElement.style.maxHeight = '100%';
            
            // In a real implementation, you would set the source to the camera's RTSP stream
            // converted to HLS or WebRTC, or use a library like JSMpeg
            // For demo, we'll use a placeholder video or static image that cycles
            
            // Placeholder approach - in real app you'd connect to actual camera stream
            // videoElement.src = "path/to/stream/for/" + camera.id;
            
            // For demo, simulate a feed with static images that change
            simulateCameraFeed(videoElement, camera.id);
            
            cameraView.appendChild(videoElement);
        } else {
            // Show status message for non-active cameras
            const statusMessage = document.createElement('div');
            statusMessage.className = 'camera-status-message';
            
            if (camera.status === 'maintenance') {
                statusMessage.innerHTML = `
                    <i class="fas fa-tools"></i>
                    <p>Kamera është në mirëmbajtje</p>
                `;
            } else if (camera.status === 'offline') {
                statusMessage.innerHTML = `
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Kamera është offline</p>
                `;
            } else {
                statusMessage.innerHTML = `
                    <i class="fas fa-power-off"></i>
                    <p>Kamera është joaktive</p>
                `;
            }
            
            cameraView.appendChild(statusMessage);
        }
    }
    
    // Simulate camera feed for demo purposes
    function simulateCameraFeed(videoElement, cameraId) {
        // In a real app, you would connect to actual camera stream
        // For demo, we'll cycle through some static images to simulate a feed
        
        const feedContainer = document.createElement('div');
        feedContainer.className = 'simulated-feed';
        feedContainer.style.width = '100%';
        feedContainer.style.height = '100%';
        feedContainer.style.backgroundColor = '#000';
        feedContainer.style.display = 'flex';
        feedContainer.style.justifyContent = 'center';
        feedContainer.style.alignItems = 'center';
        feedContainer.style.position = 'relative';
        
        const feedImage = document.createElement('img');
        feedImage.style.maxWidth = '100%';
        feedImage.style.maxHeight = '100%';
        
        // Add timestamp overlay
        const timestamp = document.createElement('div');
        timestamp.className = 'timestamp-overlay';
        timestamp.style.position = 'absolute';
        timestamp.style.bottom = '10px';
        timestamp.style.right = '10px';
        timestamp.style.backgroundColor = 'rgba(0,0,0,0.5)';
        timestamp.style.color = '#fff';
        timestamp.style.padding = '5px 8px';
        timestamp.style.borderRadius = '3px';
        timestamp.style.fontSize = '12px';
        
        // Add camera name overlay
        const nameOverlay = document.createElement('div');
        nameOverlay.className = 'name-overlay';
        nameOverlay.style.position = 'absolute';
        nameOverlay.style.top = '10px';
        nameOverlay.style.left = '10px';
        nameOverlay.style.backgroundColor = 'rgba(0,0,0,0.5)';
        nameOverlay.style.color = '#fff';
        nameOverlay.style.padding = '5px 8px';
        nameOverlay.style.borderRadius = '3px';
        nameOverlay.style.fontSize = '12px';
        
        const camera = cameras.find(c => c.id === cameraId);
        if (camera) {
            nameOverlay.textContent = camera.name;
        }
        
        feedContainer.appendChild(feedImage);
        feedContainer.appendChild(timestamp);
        feedContainer.appendChild(nameOverlay);
        
        cameraView.appendChild(feedContainer);
        
        // Cycle through demo images
        let imageIndex = 1;
        const totalImages = 5; // Number of demo images
        
        function updateFeed() {
            // Update timestamp
            const now = new Date();
            timestamp.textContent = now.toLocaleTimeString() + ' ' + now.toLocaleDateString();
            
            // In a real app, the image would be the actual camera feed
            // For demo, cycle through some static images
            feedImage.src = `img/camera_feeds/demo${imageIndex}.jpg`;
            
            // Cycle to next image
            imageIndex = (imageIndex % totalImages) + 1;
        }
        
        // Initial update
        updateFeed();
        
        // Update every few seconds to simulate video
        return setInterval(updateFeed, 3000);
    }
    
    // Record button handler
    recordBtn.addEventListener('click', function() {
        if (!currentCameraId) return;
        
        recordingState = !recordingState;
        
        if (recordingState) {
            // Start recording
            recordBtn.innerHTML = '<i class="fas fa-stop"></i> Stop Recording';
            recordBtn.classList.remove('btn-danger');
            recordBtn.classList.add('btn-warning');
            
            // Add recording indicator
            const recordingIndicator = document.createElement('div');
            recordingIndicator.id = 'recording-indicator';
            recordingIndicator.className = 'recording-indicator';
            recordingIndicator.innerHTML = '<i class="fas fa-circle"></i> Recording';
            cameraView.appendChild(recordingIndicator);
            
            // Update recording status
            document.getElementById('recording-status').textContent = 'Incizim: Po';
            
            // In a real app, you would start actual recording here
            startRecording(currentCameraId);
        } else {
            // Stop recording
            recordBtn.innerHTML = '<i class="fas fa-record-vinyl"></i> Incizon';
            recordBtn.classList.remove('btn-warning');
            recordBtn.classList.add('btn-danger');
            
            // Remove recording indicator
            const indicator = document.getElementById('recording-indicator');
            if (indicator) {
                indicator.remove();
            }
            
            // Update recording status
            document.getElementById('recording-status').textContent = 'Incizim: Jo';
            
            // In a real app, you would stop actual recording here
            stopRecording(currentCameraId);
        }
    });
    
    // Screenshot button handler
    screenshotBtn.addEventListener('click', function() {
        if (!currentCameraId) return;
        
        // In a real app, you would capture actual screenshot here
        takeScreenshot(currentCameraId);
        
        showNotification('success', 'Screenshot taken');
    });
    
    // Fullscreen button handler
    fullscreenBtn.addEventListener('click', function() {
        if (!cameraView) return;
        
        if (!document.fullscreenElement) {
            cameraView.requestFullscreen().catch(err => {
                showNotification('error', `Error going fullscreen: ${err.message}`);
            });
        } else {
            document.exitFullscreen();
        }
    });
    
    // Config button handler
    configBtn.addEventListener('click', function() {
        if (!currentCameraId) return;
        
        const camera = cameras.find(c => c.id === currentCameraId);
        if (!camera) return;
        
        // Populate form with camera details
        document.getElementById('config-camera-id').value = camera.id;
        document.getElementById('config-camera-name').value = camera.name;
        document.getElementById('config-camera-location').value = camera.location;
        document.getElementById('config-camera-url').value = camera.url;
        document.getElementById('config-camera-username').value = camera.username || '';
        document.getElementById('config-camera-status').value = camera.status;
        
        // Load camera configurations
        fetch(`api_security_cameras.php?action=get_camera_configs&camera_id=${camera.id}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.configs) {
                    // Populate configuration fields
                    document.getElementById('config-motion-enabled').checked = data.configs.motion_enabled === '1';
                    if (data.configs.motion_sensitivity) {
                        document.getElementById('config-motion-sensitivity').value = data.configs.motion_sensitivity;
                        document.querySelector('#config-motion-sensitivity + .range-value').textContent = data.configs.motion_sensitivity;
                    }
                    
                    document.getElementById('config-recording-enabled').checked = data.configs.recording_enabled === '1';
                    if (data.configs.recording_mode) {
                        document.querySelector(`input[name="recording_mode"][value="${data.configs.recording_mode}"]`).checked = true;
                        
                        if (data.configs.recording_mode === 'scheduled') {
                            document.getElementById('recording-schedule-container').style.display = 'block';
                        }
                    }
                    
                    if (data.configs.recording_quality) {
                        document.getElementById('config-recording-quality').value = data.configs.recording_quality;
                    }
                    
                    if (data.configs.recording_retention) {
                        document.getElementById('config-recording-retention').value = data.configs.recording_retention;
                    }
                    
                    document.getElementById('config-alerts-enabled').checked = data.configs.alerts_enabled === '1';
                    
                    if (data.configs.alert_recipients) {
                        document.getElementById('config-alert-recipients').value = data.configs.alert_recipients;
                    }
                    
                    if (data.configs.alert_cooldown) {
                        document.getElementById('config-alert-cooldown').value = data.configs.alert_cooldown;
                    }
                    
                    // Advanced tab
                    if (data.configs.camera_protocol) {
                        document.getElementById('config-camera-protocol').value = data.configs.camera_protocol;
                    }
                    
                    if (data.configs.camera_port) {
                        document.getElementById('config-camera-port').value = data.configs.camera_port;
                    }
                    
                    if (data.configs.camera_path) {
                        document.getElementById('config-camera-path').value = data.configs.camera_path;
                    }
                    
                    if (data.configs.camera_fps) {
                        document.getElementById('config-camera-fps').value = data.configs.camera_fps;
                    }
                    
                    // Checkboxes
                    document.querySelector('input[name="alert_motion"]').checked = data.configs.alert_motion === '1';
                    document.querySelector('input[name="alert_person"]').checked = data.configs.alert_person === '1';
                    document.querySelector('input[name="alert_vehicle"]').checked = data.configs.alert_vehicle === '1';
                    document.querySelector('input[name="alert_offline"]').checked = data.configs.alert_offline === '1';
                    
                    document.querySelector('input[name="notify_dashboard"]').checked = data.configs.notify_dashboard === '1';
                    document.querySelector('input[name="notify_email"]').checked = data.configs.notify_email === '1';
                    document.querySelector('input[name="notify_sms"]').checked = data.configs.notify_sms === '1';
                    
                    document.querySelector('input[name="use_ssl"]').checked = data.configs.use_ssl === '1';
                    document.querySelector('input[name="verify_cert"]').checked = data.configs.verify_cert === '1';
                    document.querySelector('input[name="reconnect_automatically"]').checked = data.configs.reconnect_automatically === '1';
                    
                    // Custom settings as JSON
                    if (data.configs.camera_custom) {
                        document.getElementById('config-camera-custom').value = data.configs.camera_custom;
                    }
                }
            })
            .catch(error => {
                console.error('Error loading camera configs:', error);
            });
        
        // Show the modal
        configCameraModal.style.display = 'block';
    });
    
    // Add Camera button handler
    addCameraBtn.addEventListener('click', function() {
        // Reset form
        document.getElementById('add-camera-form').reset();
        
        // Show the modal
        addCameraModal.style.display = 'block';
    });
    
    // Close buttons for modals
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            addCameraModal.style.display = 'none';
            configCameraModal.style.display = 'none';
            alertViewModal.style.display = 'none';
        });
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        if (event.target === addCameraModal) {
            addCameraModal.style.display = 'none';
        }
        if (event.target === configCameraModal) {
            configCameraModal.style.display = 'none';
        }
        if (event.target === alertViewModal) {
            alertViewModal.style.display = 'none';
        }
    });
    
    // Handle add camera form submission
    document.getElementById('add-camera-form').addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'add_camera');
        
        // Collect checkbox values
        formData.append('enable_motion', document.querySelector('input[name="enable_motion"]').checked ? '1' : '0');
        formData.append('enable_recording', document.querySelector('input[name="enable_recording"]').checked ? '1' : '0');
        formData.append('enable_alerts', document.querySelector('input[name="enable_alerts"]').checked ? '1' : '0');
        
        fetch('api_security_cameras.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || 'Camera added successfully');
                addCameraModal.style.display = 'none';
                
                // Reload cameras
                loadCameras();
            } else {
                showNotification('error', data.message || 'Failed to add camera');
            }
        })
        .catch(error => {
            console.error('Error adding camera:', error);
            showNotification('error', 'Network error while adding camera');
        });
    });
    
    // Handle camera config form submission
    document.getElementById('config-camera-form').addEventListener('submit', function(event) {
        event.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'update_camera');
        
        // Collect checkbox values from various tabs
        // Motion tab
        formData.append('motion_enabled', document.getElementById('config-motion-enabled').checked ? '1' : '0');
        formData.append('detect_persons', document.querySelector('input[name="detect_persons"]').checked ? '1' : '0');
        formData.append('detect_vehicles', document.querySelector('input[name="detect_vehicles"]').checked ? '1' : '0');
        formData.append('detect_animals', document.querySelector('input[name="detect_animals"]').checked ? '1' : '0');
        
        // Recording tab
        formData.append('recording_enabled', document.getElementById('config-recording-enabled').checked ? '1' : '0');
        const recordingMode = document.querySelector('input[name="recording_mode"]:checked');
        if (recordingMode) {
            formData.append('recording_mode', recordingMode.value);
        }
        
        // Alerts tab
        formData.append('alerts_enabled', document.getElementById('config-alerts-enabled').checked ? '1' : '0');
        formData.append('alert_motion', document.querySelector('input[name="alert_motion"]').checked ? '1' : '0');
        formData.append('alert_person', document.querySelector('input[name="alert_person"]').checked ? '1' : '0');
        formData.append('alert_vehicle', document.querySelector('input[name="alert_vehicle"]').checked ? '1' : '0');
        formData.append('alert_offline', document.querySelector('input[name="alert_offline"]').checked ? '1' : '0');
        
        formData.append('notify_dashboard', document.querySelector('input[name="notify_dashboard"]').checked ? '1' : '0');
        formData.append('notify_email', document.querySelector('input[name="notify_email"]').checked ? '1' : '0');
        formData.append('notify_sms', document.querySelector('input[name="notify_sms"]').checked ? '1' : '0');
        
        // Advanced tab
        formData.append('use_ssl', document.querySelector('input[name="use_ssl"]').checked ? '1' : '0');
        formData.append('verify_cert', document.querySelector('input[name="verify_cert"]').checked ? '1' : '0');
        formData.append('reconnect_automatically', document.querySelector('input[name="reconnect_automatically"]').checked ? '1' : '0');
        
        fetch('api_security_cameras.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification('success', data.message || 'Camera updated successfully');
                configCameraModal.style.display = 'none';
                
                // Reload cameras
                loadCameras();
                
                // If current camera was updated, reload its feed
                if (currentCameraId === parseInt(formData.get('camera_id'))) {
                    const camera = cameras.find(c => c.id === currentCameraId);
                    if (camera) {
                        camera.name = formData.get('camera_name');
                        camera.location = formData.get('camera_location');
                        camera.status = formData.get('camera_status');
                        
                        // Update info
                        document.getElementById('camera-name').textContent = camera.name;
                        document.getElementById('camera-status').textContent = `Status: ${formatStatus(camera.status)}`;
                    }
                }
            } else {
                showNotification('error', data.message || 'Failed to update camera');
            }
        })
        .catch(error => {
            console.error('Error updating camera:', error);
            showNotification('error', 'Network error while updating camera');
        });
    });
    
    // Handle delete camera button
    document.querySelector('.delete-camera').addEventListener('click', function() {
        if (!confirm('Are you sure you want to delete this camera?')) {
            return;
        }
        
        const cameraId = document.getElementById('config-camera-id').value;
        
        fetch(`api_security_cameras.php?action=delete_camera&camera_id=${cameraId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message || 'Camera deleted successfully');
                    configCameraModal.style.display = 'none';
                    
                    // Reload cameras
                    loadCameras();
                    
                    // If current camera was deleted, clear the view
                    if (currentCameraId === parseInt(cameraId)) {
                        currentCameraId = null;
                        cameraView.innerHTML = `
                            <div class="no-camera-selected">
                                <i class="fas fa-video-slash"></i>
                                <p>Select a camera to view</p>
                            </div>
                        `;
                        document.getElementById('camera-name').textContent = 'No camera selected';
                        document.getElementById('camera-status').textContent = 'Status: N/A';
                        document.getElementById('recording-status').textContent = 'Recording: No';
                        
                        // Disable control buttons
                        recordBtn.disabled = true;
                        screenshotBtn.disabled = true;
                        fullscreenBtn.disabled = true;
                        configBtn.disabled = true;
                    }
                } else {
                    showNotification('error', data.message || 'Failed to delete camera');
                }
            })
            .catch(error => {
                console.error('Error deleting camera:', error);
                showNotification('error', 'Network error while deleting camera');
            });
    });
    
    // Handle view alert button
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('view-alert') || event.target.closest('.view-alert')) {
            const button = event.target.classList.contains('view-alert') ? event.target : event.target.closest('.view-alert');
            const alertId = button.dataset.alertId;
            
            if (alertId) {
                viewAlert(alertId);
            }
        }
    });
    
    // Handle resolve alert button
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('resolve-alert') || event.target.closest('.resolve-alert')) {
            const button = event.target.classList.contains('resolve-alert') ? event.target : event.target.closest('.resolve-alert');
            const alertId = button.dataset.alertId;
            
            if (alertId) {
                resolveAlert(alertId);
            }
        }
    });
    
    // View alert details
    function viewAlert(alertId) {
        fetch(`api_security_cameras.php?action=get_alert&alert_id=${alertId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.alert) {
                    const alert = data.alert;
                    
                    // Populate alert view
                    document.getElementById('alert-camera-name').textContent = alert.camera_name;
                    document.getElementById('alert-type').textContent = formatAlertType(alert.alert_type);
                    document.getElementById('alert-level').textContent = formatAlertLevel(alert.alert_level);
                    document.getElementById('alert-time').textContent = new Date(alert.alert_time).toLocaleString();
                    
                    // Set image if available
                    const alertImage = document.getElementById('alert-image');
                    if (alert.image_path) {
                        alertImage.src = alert.image_path;
                        alertImage.style.display = 'block';
                    } else {
                        // Use placeholder based on alert type
                        alertImage.src = `img/alerts/${alert.alert_type}_placeholder.jpg`;
                        alertImage.style.display = 'block';
                    }
                    
                    // Set up go to camera button
                    const gotoCameraBtn = document.getElementById('goto-camera-btn');
                    gotoCameraBtn.dataset.cameraId = alert.camera_id;
                    gotoCameraBtn.addEventListener('click', function() {
                        alertViewModal.style.display = 'none';
                        selectCamera(parseInt(this.dataset.cameraId));
                    });
                    
                    // Set up resolve alert button
                    const resolveAlertBtn = document.getElementById('resolve-alert-btn');
                    resolveAlertBtn.dataset.alertId = alert.id;
                    resolveAlertBtn.addEventListener('click', function() {
                        resolveAlert(this.dataset.alertId);
                        alertViewModal.style.display = 'none';
                    });
                    
                    // Show modal
                    alertViewModal.style.display = 'block';
                } else {
                    showNotification('error', data.message || 'Failed to load alert details');
                }
            })
            .catch(error => {
                console.error('Error loading alert details:', error);
                showNotification('error', 'Network error while loading alert');
            });
    }
    
    // Resolve an alert
    function resolveAlert(alertId) {
        fetch(`api_security_cameras.php?action=resolve_alert&alert_id=${alertId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', data.message || 'Alert resolved successfully');
                    
                    // Remove alert from list
                    const alertItem = document.querySelector(`.alert-item[data-alert-id="${alertId}"]`);
                    if (alertItem) {
                        alertItem.remove();
                        
                        // Check if no more alerts
                        const alertsList = document.querySelector('.alerts-list');
                        if (alertsList.children.length === 0) {
                            alertsList.innerHTML = `
                                <div class="no-alerts">
                                    <i class="fas fa-check-circle"></i>
                                    <p>No new alerts</p>
                                </div>
                            `;
                        }
                    }
                } else {
                    showNotification('error', data.message || 'Failed to resolve alert');
                }
            })
            .catch(error => {
                console.error('Error resolving alert:', error);
                showNotification('error', 'Network error while resolving alert');
            });
    }
    
    // Tab system for camera config
    document.querySelectorAll('.tab-header').forEach(tab => {
        tab.addEventListener('click', function() {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            
            // Remove active class from all tab headers
            document.querySelectorAll('.tab-header').forEach(header => {
                header.classList.remove('active');
            });
            
            // Activate clicked tab
            this.classList.add('active');
            document.getElementById(`${this.dataset.tab}-tab`).classList.add('active');
        });
    });
    
    // Handle sensitivity slider
    const sensitivitySlider = document.getElementById('config-motion-sensitivity');
    if (sensitivitySlider) {
        sensitivitySlider.addEventListener('input', function() {
            document.querySelector('#config-motion-sensitivity + .range-value').textContent = this.value;
        });
    }
    
    // Handle recording mode change
    document.querySelectorAll('input[name="recording_mode"]').forEach(input => {
        input.addEventListener('change', function() {
            const scheduleContainer = document.getElementById('recording-schedule-container');
            if (this.value === 'scheduled' && scheduleContainer) {
                scheduleContainer.style.display = 'block';
            } else if (scheduleContainer) {
                scheduleContainer.style.display = 'none';
            }
        });
    });
    
    // Start actual recording (in a real app)
    function startRecording(cameraId) {
        // In a real app, you would call an API to start recording
        fetch(`api_security_cameras.php?action=start_recording&camera_id=${cameraId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Recording started:', data.recording_id);
                } else {
                    showNotification('error', data.message || 'Failed to start recording');
                    // Reset recording state
                    recordingState = false;
                    recordBtn.innerHTML = '<i class="fas fa-record-vinyl"></i> Incizon';
                    recordBtn.classList.remove('btn-warning');
                    recordBtn.classList.add('btn-danger');
                    
                    const indicator = document.getElementById('recording-indicator');
                    if (indicator) {
                        indicator.remove();
                    }
                }
            })
            .catch(error => {
                console.error('Error starting recording:', error);
                showNotification('error', 'Network error while starting recording');
                // Reset recording state
                recordingState = false;
                recordBtn.innerHTML = '<i class="fas fa-record-vinyl"></i> Incizon';
                recordBtn.classList.remove('btn-warning');
                recordBtn.classList.add('btn-danger');
                
                const indicator = document.getElementById('recording-indicator');
                if (indicator) {
                    indicator.remove();
                }
            });
    }
    
    // Stop recording (in a real app)
    function stopRecording(cameraId) {
        // In a real app, you would call an API to stop recording
        fetch(`api_security_cameras.php?action=stop_recording&camera_id=${cameraId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Recording saved successfully');
                } else {
                    showNotification('error', data.message || 'Failed to stop recording');
                }
            })
            .catch(error => {
                console.error('Error stopping recording:', error);
                showNotification('error', 'Network error while stopping recording');
            });
    }
    
    // Take screenshot (in a real app)
    function takeScreenshot(cameraId) {
        // In a real app, you would call an API to capture a screenshot
        fetch(`api_security_cameras.php?action=take_screenshot&camera_id=${cameraId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('success', 'Screenshot saved successfully');
                } else {
                    showNotification('error', data.message || 'Failed to take screenshot');
                }
            })
            .catch(error => {
                console.error('Error taking screenshot:', error);
                showNotification('error', 'Network error while taking screenshot');
            });
    }
    
    // Log camera access
    function logCameraAccess(cameraId, action) {
        // In a real app, you would call an API to log access
        fetch(`api_security_cameras.php?action=log_access&camera_id=${cameraId}&access_type=${action}`)
            .catch(error => {
                console.error('Error logging access:', error);
            });
    }
    
    // Format status for display
    function formatStatus(status) {
        switch (status) {
            case 'active': return 'Aktiv';
            case 'inactive': return 'Joaktiv';
            case 'maintenance': return 'Në mirëmbajtje';
            case 'offline': return 'Offline';
            default: return 'N/A';
        }
    }
    
    // Format alert type for display
    function formatAlertType(type) {
        switch (type) {
            case 'motion': return 'Lëvizje';
            case 'person': return 'Person';
            case 'vehicle': return 'Automjet';
            case 'animal': return 'Kafshë';
            case 'offline': return 'Kamera offline';
            case 'custom': return 'Të personalizuara';
            default: return type;
        }
    }
    
    // Format alert level for display
    function formatAlertLevel(level) {
        switch (level) {
            case 'low': return 'I ulët';
            case 'medium': return 'Mesatar';
            case 'high': return 'I lartë';
            case 'critical': return 'Kritik';
            default: return level;
        }
    }
    
    // Show notification
    function showNotification(type, message) {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'}`;
        notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${escapeHTML(message)}`;
        
        // Add to beginning of main content
        const mainContent = document.querySelector('.main-content');
        const firstChild = mainContent.firstChild;
        mainContent.insertBefore(notification, firstChild);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 5000);
    }
    
    // Helper function to escape HTML
    function escapeHTML(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
    
    // Initialize
    loadCameras();
});