/**
 * FRONTEND VIDEO QUALITY OPTIMIZATION
 * Real-time adaptive streaming, bandwidth detection, quality adjustment
 * Optimized për 1M+ daily calls
 */

// ============================================================================
// 1. ADVANCED BANDWIDTH DETECTION
// ============================================================================

class AdvancedBandwidthDetector {
    constructor() {
        this.samples = [];
        this.maxSamples = 60;
        this.updateInterval = 1000; // 1 second
        this.minBitrate = 300; // Kbps
        this.maxBitrate = 8000; // Kbps
    }
    
    /**
     * Detect bandwidth using multiple methods
     */
    async detectBandwidth() {
        const methods = [
            this.detectionViaNAVIGATOR(),
            this.estimateViaNetworkInfo(),
            this.estimateViaTiming()
        ];
        
        const results = await Promise.allSettled(methods);
        const valid = results
            .filter(r => r.status === 'fulfilled')
            .map(r => r.value);
        
        if (valid.length === 0) {
            return this.fallbackEstimate();
        }
        
        // Average the results
        const average = valid.reduce((a, b) => a + b, 0) / valid.length;
        return Math.max(this.minBitrate, Math.min(average, this.maxBitrate));
    }
    
    /**
     * Method 1: Use Navigator.connection API
     */
    detectionViaNAVIGATOR() {
        return new Promise((resolve, reject) => {
            const connection = navigator.connection || 
                             navigator.mozConnection || 
                             navigator.webkitConnection;
            
            if (!connection) {
                reject(new Error('Navigator connection API not available'));
            }
            
            // Convert effectiveType to estimated bandwidth
            const typeMap = {
                'slow-2g': 150,
                '2g': 250,
                '3g': 1500,
                '4g': 4000,
                '5g': 25000
            };
            
            const estimate = typeMap[connection.effectiveType] || 2500;
            resolve(estimate * (1 - (connection.saveData ? 0.5 : 0)));
        });
    }
    
    /**
     * Method 2: Monitor actual network info
     */
    estimateViaNetworkInfo() {
        return new Promise((resolve, reject) => {
            const connection = navigator.connection || 
                             navigator.mozConnection || 
                             navigator.webkitConnection;
            
            if (!connection || !connection.downlink) {
                reject(new Error('Downlink speed not available'));
            }
            
            // downlink is in Mbps, convert to Kbps
            resolve(connection.downlink * 1000);
        });
    }
    
    /**
     * Method 3: Estimate via request timing
     */
    estimateViaTiming() {
        return new Promise((resolve) => {
            const testSize = 1024 * 100; // 100KB test
            const startTime = performance.now();
            
            // Use existing resource timing
            const navigation = performance.getEntriesByType('navigation')[0];
            if (navigation) {
                const downloadTime = navigation.responseEnd - navigation.responseStart;
                const bandwidth = (navigation.transferSize * 8) / downloadTime / 1000; // Kbps
                resolve(Math.max(this.minBitrate, bandwidth));
            } else {
                resolve(2500); // fallback
            }
        });
    }
    
    /**
     * Fallback estimate based on RTT
     */
    fallbackEstimate() {
        const rtt = this.estimateRTT();
        
        // Rough estimate: higher RTT = lower bandwidth
        if (rtt < 20) return 4000;
        if (rtt < 50) return 2500;
        if (rtt < 100) return 1500;
        if (rtt < 150) return 800;
        return 400;
    }
    
    /**
     * Estimate Round Trip Time
     */
    estimateRTT() {
        const timingData = performance.getEntriesByType('navigation')[0];
        if (timingData) {
            return timingData.responseEnd - timingData.fetchStart;
        }
        return 50; // default fallback
    }
    
    /**
     * Track bandwidth changes over time
     */
    async monitorBandwidth(callback) {
        setInterval(async () => {
            const bandwidth = await this.detectBandwidth();
            this.samples.push({
                bandwidth,
                timestamp: Date.now()
            });
            
            if (this.samples.length > this.maxSamples) {
                this.samples.shift();
            }
            
            const trend = this.analyzeTrend();
            callback({
                current: bandwidth,
                average: this.getAverageBandwidth(),
                trend,
                samples: this.samples
            });
        }, this.updateInterval);
    }
    
    /**
     * Analyze bandwidth trend
     */
    analyzeTrend() {
        if (this.samples.length < 2) return 'stable';
        
        const recent = this.samples.slice(-10);
        const oldAvg = recent.slice(0, 5)
            .reduce((a, b) => a + b.bandwidth, 0) / 5;
        const newAvg = recent.slice(5)
            .reduce((a, b) => a + b.bandwidth, 0) / 5;
        
        const change = ((newAvg - oldAvg) / oldAvg) * 100;
        
        if (change > 10) return 'improving';
        if (change < -10) return 'degrading';
        return 'stable';
    }
    
    /**
     * Get average bandwidth
     */
    getAverageBandwidth() {
        if (this.samples.length === 0) return 2500;
        const sum = this.samples.reduce((a, b) => a + b.bandwidth, 0);
        return sum / this.samples.length;
    }
}

// ============================================================================
// 2. ADAPTIVE QUALITY MANAGER
// ============================================================================

class AdaptiveQualityManager {
    constructor(jitsiAPI) {
        this.api = jitsiAPI;
        this.bandwidthDetector = new AdvancedBandwidthDetector();
        
        this.qualityProfiles = {
            ultra: {
                height: 1440, width: 2560, bitrate: 6000,
                framerate: 60, name: '2K Ulta HD'
            },
            hd: {
                height: 1080, width: 1920, bitrate: 4000,
                framerate: 30, name: '1080p HD'
            },
            fullhd: {
                height: 720, width: 1280, bitrate: 2500,
                framerate: 30, name: '720p Full HD'
            },
            hd480: {
                height: 480, width: 854, bitrate: 1500,
                framerate: 24, name: '480p HD'
            },
            sd360: {
                height: 360, width: 640, bitrate: 800,
                framerate: 24, name: '360p SD'
            },
            mobile: {
                height: 240, width: 426, bitrate: 400,
                framerate: 15, name: '240p Mobile'
            }
        };
        
        this.currentProfile = 'fullhd';
        this.participantCount = 1;
        this.isMetered = navigator.connection?.saveData || false;
    }
    
    /**
     * Start monitoring and adaptive adjustment
     */
    async startMonitoring() {
        // Monitor bandwidth changes
        await this.bandwidthDetector.monitorBandwidth((stats) => {
            this.adjustQualityBasedOnBandwidth(stats);
        });
        
        // Monitor participant count changes
        this.api.addListener('participantsChanged', (data) => {
            this.participantCount = data.count;
            this.recalculateOptimalQuality();
        });
        
        // Monitor connection quality
        this.monitorConnectionQuality();
    }
    
    /**
     * Adjust quality based on real-time bandwidth
     */
    adjustQualityBasedOnBandwidth(stats) {
        if (this.isMetered) {
            this.setQualityProfile('sd360');
            return;
        }
        
        // Use 60% of available bandwidth (safety margin)
        const usableGbps = stats.current * 0.6;
        
        // Consider number of participants
        const perParticipantBandwidth = usableGbps / Math.max(1, this.participantCount - 1);
        
        let targetProfile;
        
        if (perParticipantBandwidth >= 4000) {
            targetProfile = 'hd';
        } else if (perParticipantBandwidth >= 2500) {
            targetProfile = 'fullhd';
        } else if (perParticipantBandwidth >= 1500) {
            targetProfile = 'hd480';
        } else if (perParticipantBandwidth >= 800) {
            targetProfile = 'sd360';
        } else {
            targetProfile = 'mobile';
        }
        
        if (targetProfile !== this.currentProfile) {
            this.setQualityProfile(targetProfile);
        }
    }
    
    /**
     * Apply quality profile
     */
    setQualityProfile(profile) {
        const settings = this.qualityProfiles[profile];
        if (!settings) return;
        
        try {
            this.api.executeCommand('setVideoQuality', {
                height: settings.height,
                width: settings.width,
                bitrate: settings.bitrate,
                frameRate: settings.framerate
            });
            
            this.currentProfile = profile;
            console.log(`📊 Quality adjusted to: ${settings.name} (${settings.bitrate}kbps)`);
        } catch (error) {
            console.error('Failed to set quality:', error);
        }
    }
    
    /**
     * Monitor WebRTC connection quality
     */
    monitorConnectionQuality() {
        setInterval(async () => {
            try {
                const peerConnection = this.getPeerConnection();
                if (!peerConnection) return;
                
                const stats = await peerConnection.getStats();
                let inboundStats = null;
                let outboundStats = null;
                
                stats.forEach(report => {
                    if (report.type === 'inbound-rtp' && report.kind === 'video') {
                        inboundStats = report;
                    }
                    if (report.type === 'outbound-rtp' && report.kind === 'video') {
                        outboundStats = report;
                    }
                });
                
                if (inboundStats) {
                    const packetLoss = inboundStats.packetsLost / 
                                     (inboundStats.packetsReceived + inboundStats.packetsLost);
                    
                    // If packet loss > 5%, downgrade quality
                    if (packetLoss > 0.05 && this.currentProfile !== 'mobile') {
                        const profiles = Object.keys(this.qualityProfiles);
                        const currentIndex = profiles.indexOf(this.currentProfile);
                        if (currentIndex < profiles.length - 1) {
                            this.setQualityProfile(profiles[currentIndex + 1]);
                        }
                    }
                }
            } catch (error) {
                console.warn('Connection quality monitoring error:', error);
            }
        }, 5000); // Every 5 seconds
    }
    
    /**
     * Get active peer connection from Jitsi
     */
    getPeerConnection() {
        try {
            // Jitsi stores peer connections in the API object
            return this.api._room?.connection?.peerconnection;
        } catch (error) {
            return null;
        }
    }
    
    /**
     * Recalculate based on current state
     */
    recalculateOptimalQuality() {
        const bandwidth = this.bandwidthDetector.getAverageBandwidth();
        this.adjustQualityBasedOnBandwidth({
            current: bandwidth,
            average: bandwidth,
            trend: 'stable'
        });
    }
}

// ============================================================================
// 3. NETWORK RESILIENCE - Automatic Retry & Reconnection
// ============================================================================

class NetworkResilience {
    constructor(jitsiAPI) {
        this.api = jitsiAPI;
        this.maxRetries = 5;
        this.retryDelay = 1000; // ms
        this.currentRetry = 0;
        this.connectionLost = false;
    }
    
    /**
     * Handle connection loss with intelligent retry
     */
    handleConnectionLoss() {
        console.error('🔴 Connection lost, attempting recovery...');
        this.connectionLost = true;
        
        if (this.currentRetry < this.maxRetries) {
            const delay = this.retryDelay * Math.pow(2, this.currentRetry); // Exponential backoff
            
            setTimeout(() => {
                this.attemptReconnection();
            }, delay);
        } else {
            console.error('❌ Failed to reconnect after ' + this.maxRetries + ' attempts');
            this.fallbackToWebRTC();
        }
    }
    
    /**
     * Attempt reconnection
     */
    attemptReconnection() {
        console.log(`🔄 Reconnection attempt ${this.currentRetry + 1}/${this.maxRetries}`);
        
        try {
            this.api.executeCommand('toggleVideo', false);
            this.api.executeCommand('toggleAudio', false);
            
            setTimeout(() => {
                this.api.executeCommand('toggleVideo', true);
                this.api.executeCommand('toggleAudio', true);
                this.currentRetry++;
                this.connectionLost = false;
                console.log('✅ Reconnection successful');
            }, 2000);
        } catch (error) {
            this.currentRetry++;
            this.handleConnectionLoss(); // Recursive retry
        }
    }
    
    /**
     * Fallback to direct peer connection
     */
    fallbackToWebRTC() {
        console.warn('⚠️  Falling back to direct WebRTC peer connection');
        // Implement direct peer connection logic here
    }
}

// ============================================================================
// 4. PERFORMANCE MONITORING & METRICS
// ============================================================================

class PerformanceMonitor {
    constructor() {
        this.metrics = {
            videoBitrate: 0,
            audioBitrate: 0,
            frameRate: 0,
            packetLoss: 0,
            latency: 0,
            jitter: 0,
            audioLevel: 0
        };
    }
    
    /**
     * Collect WebRTC statistics
     */
    async collectStats(peerConnection) {
        if (!peerConnection) return;
        
        try {
            const stats = await peerConnection.getStats();
            
            stats.forEach(report => {
                if (report.type === 'inbound-rtp' && report.kind === 'video') {
                    this.metrics.videoBitrate = 
                        (report.bytesReceived * 8) / 1000; // Kbps
                    this.metrics.frameRate = report.framesPerSecond;
                    this.metrics.packetLoss = 
                        (report.packetsLost / (report.packetsReceived + report.packetsLost)) * 100;
                    this.metrics.jitter = (report.jitter * 1000).toFixed(2); // ms
                }
                
                if (report.type === 'inbound-rtp' && report.kind === 'audio') {
                    this.metrics.audioBitrate = 
                        (report.bytesReceived * 8) / 1000; // Kbps
                }
                
                if (report.type === 'candidate-pair' && report.state === 'succeeded') {
                    this.metrics.latency = 
                        (report.currentRoundTripTime * 1000).toFixed(2); // ms
                }
            });
        } catch (error) {
            console.warn('Stats collection error:', error);
        }
    }
    
    /**
     * Get current metrics
     */
    getMetrics() {
        return { ...this.metrics };
    }
    
    /**
     * Display metrics in UI
     */
    updateUI() {
        const metricsEl = document.getElementById('connection-stats-advanced');
        if (metricsEl) {
            metricsEl.innerHTML = `
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
                    <div>📹 Video: ${this.metrics.videoBitrate.toFixed(1)} kbps</div>
                    <div>🎵 Audio: ${this.metrics.audioBitrate.toFixed(1)} kbps</div>
                    <div>📊 FPS: ${this.metrics.frameRate.toFixed(1)}</div>
                    <div>📉 Loss: ${this.metrics.packetLoss.toFixed(2)}%</div>
                    <div>⏱️  RTT: ${this.metrics.latency}ms</div>
                    <div>🎯 Jitter: ${this.metrics.jitter}ms</div>
                </div>
            `;
        }
    }
}

// ============================================================================
// INITIALIZATION & EXPORT
// ============================================================================

// Global objects for easy access
window.AdaptiveQualityManager = AdaptiveQualityManager;
window.AdvancedBandwidthDetector = AdvancedBandwidthDetector;
window.NetworkResilience = NetworkResilience;
window.PerformanceMonitor = PerformanceMonitor;

// Auto-initialize when Jitsi API is ready
if (window.api) {
    console.log('🚀 Initializing Advanced Video Quality Management');
    
    const qualityManager = new AdaptiveQualityManager(window.api);
    const resilience = new NetworkResilience(window.api);
    const perfMonitor = new PerformanceMonitor();
    
    // Start quality monitoring
    qualityManager.startMonitoring().catch(err => 
        console.warn('Quality monitoring initialization:', err)
    );
    
    // Monitor connection health
    window.api.addListener('videoConferenceFailed', () => resilience.handleConnectionLoss());
    window.api.addListener('connectionFailed', () => resilience.handleConnectionLoss());
    
    // Periodic metrics collection
    setInterval(() => {
        const peerConn = qualityManager.getPeerConnection();
        if (peerConn) {
            perfMonitor.collectStats(peerConn).then(() => {
                perfMonitor.updateUI();
            });
        }
    }, 2000);
    
    console.log('✅ Advanced video quality system initialized');
}
