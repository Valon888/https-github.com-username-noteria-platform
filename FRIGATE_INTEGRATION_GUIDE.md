# Integrating Frigate NVR with Your PHP Application

This guide will help you set up and connect Frigate NVR with your PHP application for security camera monitoring.

## Prerequisites

1. **Frigate NVR Installed**
   - [Frigate NVR](https://frigate.video) should be installed and configured with your cameras
   - Common installation methods: Docker, Home Assistant Add-on, or direct installation
   - Verify Frigate is working by accessing its web UI (default: http://frigate-host:5000)

2. **PHP Environment**
   - PHP 7.4+ with cURL extension enabled
   - Web server (Apache, Nginx, etc.) or PHP's built-in server
   - Network access from your PHP server to the Frigate NVR server

## Installation Steps

### Step 1: Set Up Frigate NVR

If you haven't already installed Frigate, follow the [official installation guide](https://docs.frigate.video/installation).

Basic Docker setup example:
```bash
docker run -d \
  --name frigate \
  --restart=unless-stopped \
  -p 5000:5000 \
  -p 8554:8554 \
  -e FRIGATE_RTSP_PASSWORD=password \
  -v /path/to/config.yml:/config/config.yml \
  -v /path/to/storage:/media/frigate \
  --device /dev/dri/renderD128 \
  ghcr.io/blakeblackshear/frigate:stable
```

### Step 2: Configure Your PHP Application

1. Copy the FrigateAPI.php, frigate_example.php, and test_frigate_connection.php files to your project directory.

2. Edit the configuration in your test files to match your Frigate setup:
   - Update the Frigate host address (`$frigateHost` or `$config['frigate_host']`)
   - Update the camera names to match your Frigate configuration

3. Test the connection:
```bash
php -f test_frigate_connection.php
```

### Step 3: Troubleshooting Connection Issues

If you encounter connection issues:

1. **Check Network Connectivity**:
```bash
# Test basic connectivity
ping your-frigate-ip

# Test API endpoint accessibility
curl -I http://your-frigate-ip:5000/api/version
```

2. **Check Firewall Settings**:
   - Ensure ports 5000 (API) and 8554 (RTSP) are accessible from your PHP server
   - For Windows, check Windows Firewall settings
   - For Docker installations, verify port mappings are correct

3. **Verify Frigate Configuration**:
   - Check that your cameras are properly configured in Frigate
   - Ensure Frigate is running with the correct network settings

4. **PHP Configuration**:
   - Verify PHP cURL extension is enabled
   - Check for any PHP errors in your logs

## Integration Examples

### Basic Camera Display

```php
<?php
require_once 'FrigateAPI.php';

$frigate = new FrigateAPI('http://your-frigate-ip:5000');

// Display a live feed
$mjpegUrl = $frigate->getMjpegUrl('your_camera_name');
echo "<img src='{$mjpegUrl}' alt='Live camera'>";
```

### Security Monitoring Dashboard

```php
<?php
require_once 'FrigateAPI.php';

$frigate = new FrigateAPI('http://your-frigate-ip:5000');
$cameras = $frigate->getCameras();

// Create a security dashboard
echo "<h1>Security Monitoring</h1>";

foreach ($cameras as $name => $details) {
    echo "<div class='camera-container'>";
    echo "<h2>{$name}</h2>";
    echo "<img src='" . $frigate->getMjpegUrl($name) . "' alt='{$name}'>";
    echo "</div>";
}

// Show recent events
$events = $frigate->getEvents(['limit' => 10]);
echo "<h2>Recent Events</h2>";
foreach ($events as $event) {
    echo "<p>".date('Y-m-d H:i:s', $event['timestamp'])." - ";
    echo "Camera: {$event['camera']}, Object: {$event['label']}</p>";
}
```

## Next Steps

1. **Implement Authentication**:
   - Add authentication to protect your camera feeds
   - Consider using PHP sessions or a proper authentication system

2. **Create a Full Security Dashboard**:
   - Combine camera feeds, events, and controls in a single interface
   - Add push notifications for important events

3. **Automate Security Responses**:
   - Trigger actions based on detected events
   - Integrate with other home automation systems

4. **Mobile Access**:
   - Develop a mobile-friendly interface
   - Consider creating a Progressive Web App (PWA)

## Resources

- [Frigate Documentation](https://docs.frigate.video/)
- [Frigate API Reference](https://docs.frigate.video/api)
- [RTSP Streaming Guide](https://docs.frigate.video/configuration/rtsp)