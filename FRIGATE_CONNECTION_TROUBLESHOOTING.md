# Frigate NVR Connection Troubleshooting Guide

If you're experiencing connection issues with Frigate NVR, follow this step-by-step guide to diagnose and resolve the problem.

## Connection Timeout Error

If you see an error like:

```text
CONNECTION ERROR: Frigate API request failed: Connection timed out after 15000 milliseconds
```

This indicates that your PHP application can't establish a connection with the Frigate NVR server. Here's how to troubleshoot:

## Step 1: Verify Frigate NVR is Running

1. Open a web browser and try to access your Frigate instance:

   
```text
http://192.168.1.1:5000
```

2. If the web interface doesn't load, check if Frigate is actually running on that machine.

3. If you're using Docker, check the container status:

   
```bash
docker ps | grep frigate
```

4. Check Frigate logs for any errors:

   
```bash
docker logs frigate
```

## Step 2: Check Network Connectivity

1. Ping the Frigate host to see if it's reachable:

   
```bash
ping 192.168.1.1
```

2. Check if the port is open and accessible:

### Windows

   
```powershell
Test-NetConnection -ComputerName 192.168.1.1 -Port 5000
```

### Linux/MacOS

   
```bash
nc -zv 192.168.1.1 5000
```

3. Verify you're on the same network as the Frigate server or that proper routing is in place.

## Step 3: Verify IP Address and Port

1. Double-check the IP address of your Frigate server:
   - If using Docker, run `docker inspect frigate | grep IPAddress`
   - If using Home Assistant, check the add-on configuration
   - Consider using a hostname if your device has a dynamic IP

2. Verify the correct port:
   - Default Frigate port is 5000
   - Check your Frigate configuration to confirm the port hasn't been changed

## Step 4: Check Firewall Settings

1. Temporarily disable any firewalls on the Frigate server to test if that's the issue:
   - Windows: Windows Defender Firewall
   - Linux: `sudo ufw disable` (Ubuntu) or `sudo systemctl stop firewalld` (CentOS)

2. If disabling the firewall resolves the issue, add a firewall rule to allow traffic on port 5000.

## Step 5: Check for Proxy or Network Restrictions

1. If you're in a corporate or restricted network environment, proxy settings might be blocking the connection.

2. Try connecting from a different network (e.g., mobile hotspot) to rule out network restrictions.

## Step 6: Adjust Connection Timeout

If your network is slow or has high latency, try increasing the connection timeout:

1. Open `FrigateAPI.php`
2. Find the constructor and increase the timeout value:

   
```php
public function __construct($baseUrl, $timeout = 30) { // Increased from 10 to 30 seconds
    $this->baseUrl = rtrim($baseUrl, '/');
    $this->timeout = $timeout;
}
```

## Step 7: Advanced Debugging

1. Try accessing the Frigate API directly with cURL:

   
```bash
curl -v http://192.168.1.1:5000/api/version
```

2. Check for any SSL/TLS issues if you're using HTTPS.

3. Try using a specific IP version:

   
```bash
curl -v -4 http://192.168.1.1:5000/api/version  # Force IPv4
curl -v -6 http://192.168.1.1:5000/api/version  # Force IPv6
```

## Alternative Connection Options

If you still can't connect directly, consider these alternatives:

1. **Reverse Proxy**: Set up Nginx or Apache as a reverse proxy to your Frigate instance.

2. **VPN Connection**: If Frigate is on a different network, establish a VPN connection.

3. **Port Forwarding**: If accessing from outside your network, set up proper port forwarding on your router.

## Common Issues and Solutions

1. **Docker Network Issues**:
   - Ensure the Docker container has proper network access
   - Try using host network mode: `--network host`

2. **Virtual Machine Networking**:
   - If Frigate is running in a VM, check VM network settings
   - Ensure bridged networking is used instead of NAT

3. **IP Address Conflicts**:
   - Check for IP address conflicts on your network
   - Try assigning a static IP to your Frigate server

4. **DNS Issues**:
   - If using a hostname, verify DNS resolution
   - Try using the IP address directly instead of hostname

## Getting Help

If you're still having issues:

- Visit the [Frigate Community Forum](https://github.com/blakeblackshear/frigate/discussions)
- Check the [Frigate Discord](https://discord.com/invite/3p77PZNptd)
- Open an issue on [GitHub](https://github.com/blakeblackshear/frigate/issues)

1. Add a blank line before the triple backticks
2. Add a language identifier right after the opening triple backticks (like `bash`, `python`, `javascript`, etc.)
3. Make sure there's a blank line after the closing triple backticks

For example:

```markdown
Some text or instructions...

```bash
# Your Frigate connection troubleshooting commands
ping frigate.local

ping frigate.local

curl http://frigate:5000/api/version
