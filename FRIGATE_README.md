# Frigate NVR Integration Suite

This package provides a comprehensive set of tools for integrating Frigate NVR with your PHP application, allowing you to build advanced security camera monitoring solutions.

## Table of Contents

1. [Overview](#overview)
2. [Setup Guide](#setup-guide)
3. [Available Tools](#available-tools)
4. [Connection Troubleshooting](#connection-troubleshooting)
5. [Camera Discovery](#camera-discovery)
6. [Common RTSP URLs](#common-rtsp-urls)
7. [Frigate Features](#frigate-features)

## Overview

Frigate NVR is an open-source Network Video Recorder with AI-powered object detection, designed for security cameras. This integration suite allows you to access Frigate's features from your PHP application, enabling you to:

- View live camera feeds
- Access recorded clips and events
- Retrieve snapshots
- Monitor object detection events
- Build custom security dashboards

## Setup Guide

### Step 1: Install Frigate NVR

If you haven't already installed Frigate NVR, follow these installation options:

#### Docker Installation

```bash
docker run -d \
  --name frigate \
  --restart=unless-stopped \
  -p 5000:5000 \
  -p 8554:8554 \
  -v /path/to/config.yml:/config/config.yml \
  -v /path/to/storage:/media/frigate \
  --device /dev/dri/renderD128 \
  ghcr.io/blakeblackshear/frigate:stable
```

#### Home Assistant Add-on

If you use Home Assistant, install Frigate directly from the Home Assistant add-on store.

For detailed installation instructions, see the [Frigate Documentation](https://docs.frigate.video/installation).

### Step 2: Configure Your Integration

1. Open the setup wizard: `frigate_setup.php`
2. Enter your Frigate NVR server address (e.g., `http://192.168.1.100:5000`)
3. Test the connection using the built-in diagnostics tools
4. Review the integration guide for implementation details

## Available Tools

This package includes the following tools:

1. **FrigateAPI.php** - The main PHP wrapper for Frigate's REST API
2. **frigate_setup.php** - Setup wizard with connection testing and configuration guide
3. **frigate_example.php** - Example implementation showing how to use the API
4. **test_frigate_connection.php** - Basic connection test utility
5. **frigate_diagnostics.php** - Advanced connection diagnostics tool
6. **frigate_camera_scanner.php** - Network scanner to discover cameras on your network

## Connection Troubleshooting

If you're experiencing connection issues:

1. Verify Frigate is running and accessible through its web interface
2. Check network connectivity between your PHP server and Frigate server
3. Ensure ports 5000 (API) and 8554 (RTSP) are accessible
4. Run the diagnostics tool: `frigate_diagnostics.php`

For detailed troubleshooting steps, see the `FRIGATE_CONNECTION_TROUBLESHOOTING.md` file.

## Camera Discovery

To find security cameras on your network:

1. Run the camera scanner: `frigate_camera_scanner.php`
2. The tool will scan your local network for devices with camera-related ports
3. For each camera found, it will suggest possible RTSP URL patterns
4. Use these RTSP URLs in your Frigate configuration

## Common RTSP URLs

Here are common RTSP URL patterns for different camera brands:

| Camera Brand | RTSP URL Pattern | Notes |
|-------------|-----------------|-------|
| Hikvision | rtsp://username:password@192.168.1.x:554/Streaming/Channels/101 | Main stream |
| Hikvision | rtsp://username:password@192.168.1.x:554/Streaming/Channels/102 | Sub stream |
| Amcrest / Dahua | rtsp://username:password@192.168.1.x:554/cam/realmonitor?channel=1&subtype=0 | Main stream |
| Reolink | rtsp://username:password@192.168.1.x:554/h264Preview_01_main | Main stream |
| Reolink | rtsp://username:password@192.168.1.x:554/h264Preview_01_sub | Sub stream |
| Generic ONVIF | rtsp://username:password@192.168.1.x:554/onvif/profile0/media.smp | ONVIF protocol |

## Frigate Features

Frigate NVR offers several powerful features:

- **AI Object Detection**: Accurately detect people, vehicles, animals, and other objects
- **Low Resource Usage**: Efficiently runs on modest hardware
- **RTSP Restreaming**: Access your camera streams from any device
- **Recording**: Record continuously or on-motion with configurable retention
- **Live View**: View all your cameras in one web interface
- **Timeline**: Browse recordings with thumbnails
- **Clips**: Save short clips of events
- **Snapshots**: Capture still images from your cameras

## Implementation Example

```php
<?php
// Include the API wrapper
require_once 'FrigateAPI.php';

// Initialize with your Frigate server address
$frigate = new FrigateAPI('http://your-frigate-ip:5000');

// Get list of cameras
$cameras = $frigate->getCameras();

// Display a live stream
$mjpegUrl = $frigate->getMjpegUrl('your_camera_name');
echo "<img src='$mjpegUrl' alt='Live camera'>";

// Get recent events
$events = $frigate->getEvents(['limit' => 10]);
foreach ($events as $event) {
    echo date('Y-m-d H:i:s', $event['timestamp']);
    echo " - Camera: {$event['camera']}, Object: {$event['label']}<br>";
}
?>
```

## Additional Resources

- [Frigate Documentation](https://docs.frigate.video/)
- [Frigate GitHub Repository](https://github.com/blakeblackshear/frigate)
- [ONVIF Protocol Information](https://www.onvif.org/)