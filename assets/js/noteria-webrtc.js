// WebRTC Client for Noteria video calls
// Përdoret për të menaxhuar lidhjet WebRTC nga ana e klientit

class NoteriaWebRTC {
    constructor(config) {
        // Konfigurimet
        this.roomId = config.roomId;
        this.userId = config.userId;
        this.username = config.username;
        this.localVideoElement = config.localVideoElement;
        this.remoteVideoElement = config.remoteVideoElement;
        this.signalingUrl = config.signalingUrl || 'video_call_signaling.php';
        this.onConnectionStateChange = config.onConnectionStateChange || (() => {});
        this.onRemoteStreamAdded = config.onRemoteStreamAdded || (() => {});
        this.onRemoteStreamRemoved = config.onRemoteStreamRemoved || (() => {});
        this.onLocalStreamAdded = config.onLocalStreamAdded || (() => {});
        this.onMessage = config.onMessage || (() => {});
        this.iceServers = config.iceServers || [
            { urls: 'stun:stun.l.google.com:19302' },
            { urls: 'stun:stun1.l.google.com:19302' },
            { urls: 'stun:stun2.l.google.com:19302' }
        ];
        
        // Variablat e gjendjes
        this.peerConnection = null;
        this.localStream = null;
        this.remoteStream = null;
        this.isInitiator = false;
        this.isConnected = false;
        this.lastTimestamp = 0;
        this.pollInterval = null;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;
        
        // Gjendja e mediave
        this.isAudioMuted = false;
        this.isVideoOff = false;
        this.isScreenSharing = false;
        
        // Bind të metodave
        this.sendToSignalingServer = this.sendToSignalingServer.bind(this);
        this.handleSignalingMessage = this.handleSignalingMessage.bind(this);
        this.createPeerConnection = this.createPeerConnection.bind(this);
        this.handleIceCandidate = this.handleIceCandidate.bind(this);
        this.handleRemoteTrack = this.handleRemoteTrack.bind(this);
        this.handleConnectionStateChange = this.handleConnectionStateChange.bind(this);
        this.createOffer = this.createOffer.bind(this);
        this.createAnswer = this.createAnswer.bind(this);
        this.addIceCandidate = this.addIceCandidate.bind(this);
        this.startPolling = this.startPolling.bind(this);
        this.stopPolling = this.stopPolling.bind(this);
        this.reconnect = this.reconnect.bind(this);
    }
    
    // Inicimi i lidhjes
    async init() {
        try {
            // Merr media stream lokal
            this.localStream = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: 'user'
                }
            });
            
            // Shfaq video lokale
            if (this.localVideoElement) {
                this.localVideoElement.srcObject = this.localStream;
            }
            
            // Lajmëro callback
            this.onLocalStreamAdded(this.localStream);
            
            // Bashkohu në dhomë
            await this.joinRoom();
            
            // Fillo të marrësh mesazhe nga serveri
            this.startPolling();
            
            return true;
        } catch (error) {
            console.error('Gabim në inicimin e WebRTC:', error);
            
            // Provo të lidhet vetëm me audio nëse video dështon
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({
                    audio: true,
                    video: false
                });
                
                // Shfaq video lokale (do të jetë vetëm audio)
                if (this.localVideoElement) {
                    this.localVideoElement.srcObject = this.localStream;
                }
                
                // Lajmëro callback
                this.onLocalStreamAdded(this.localStream);
                
                // Bashkohu në dhomë
                await this.joinRoom();
                
                // Fillo të marrësh mesazhe nga serveri
                this.startPolling();
                
                return true;
            } catch (audioError) {
                console.error('Gabim në marrjen e audios:', audioError);
                return false;
            }
        }
    }
    
    // Funksion për të u bashkuar në dhomë
    async joinRoom() {
        try {
            const response = await this.sendToSignalingServer({
                action: 'join',
                roomId: this.roomId,
                userId: this.userId,
                username: this.username
            });
            
            if (response.success) {
                console.log('U bashkua në dhomën:', this.roomId);
                return true;
            } else {
                console.error('Gabim në bashkimin në dhomë:', response.error);
                return false;
            }
        } catch (error) {
            console.error('Gabim në bashkimin në dhomë:', error);
            return false;
        }
    }
    
    // Funksion për të dërguar të dhëna në serverin e sinjalizimit
    async sendToSignalingServer(data) {
        try {
            const response = await fetch(this.signalingUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            return await response.json();
        } catch (error) {
            console.error('Gabim në dërgimin e të dhënave në server:', error);
            throw error;
        }
    }
    
    // Funksion për të filluar polling
    startPolling() {
        this.stopPolling(); // Pastro ndonjë interval ekzistues
        
        this.pollInterval = setInterval(async () => {
            try {
                const response = await this.sendToSignalingServer({
                    action: 'poll',
                    roomId: this.roomId,
                    userId: this.userId,
                    lastTimestamp: this.lastTimestamp
                });
                
                if (response.success) {
                    this.lastTimestamp = response.timestamp;
                    
                    // Trajto mesazhet e reja
                    response.messages.forEach(this.handleSignalingMessage);
                }
            } catch (error) {
                console.error('Gabim në polling:', error);
                
                // Provo të rilidhet nëse polling dështon
                this.reconnectAttempts++;
                if (this.reconnectAttempts <= this.maxReconnectAttempts) {
                    console.log(`Tentativa ${this.reconnectAttempts} për rilidhje...`);
                    setTimeout(() => {
                        this.reconnect();
                    }, 2000);
                } else {
                    console.error('Arritën numri maksimal i tentativave për rilidhje');
                    this.stopPolling();
                }
            }
        }, 1000); // Kontrollo çdo sekondë për mesazhe të reja
    }
    
    // Funksion për të ndaluar polling
    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }
    
    // Funksion për të trajtuar mesazhet nga serveri i sinjalizimit
    handleSignalingMessage(message) {
        switch (message.type) {
            case 'join':
                // Një përdorues i ri u bashkua, nis lidhjen nëse ende nuk është krijuar
                console.log('Përdoruesi u bashkua:', message.data.userId);
                
                if (!this.peerConnection) {
                    this.isInitiator = true;
                    this.createPeerConnection();
                    this.createOffer();
                }
                break;
                
            case 'offer':
                // Ofertë e re nga përdoruesi tjetër
                console.log('Ofertë e marrë nga:', message.data.userId);
                
                if (!this.peerConnection) {
                    this.createPeerConnection();
                }
                
                this.peerConnection.setRemoteDescription(new RTCSessionDescription(message.data.offer))
                    .then(() => {
                        console.log('Remote description e vendosur pas ofertës');
                        return this.createAnswer();
                    })
                    .catch(error => {
                        console.error('Gabim në vendosjen e remote description:', error);
                    });
                break;
                
            case 'answer':
                // Përgjigje nga përdoruesi tjetër
                console.log('Përgjigje e marrë nga:', message.data.userId);
                
                this.peerConnection.setRemoteDescription(new RTCSessionDescription(message.data.answer))
                    .then(() => {
                        console.log('Remote description e vendosur pas përgjigjes');
                    })
                    .catch(error => {
                        console.error('Gabim në vendosjen e remote description:', error);
                    });
                break;
                
            case 'candidate':
                // ICE kandidat nga përdoruesi tjetër
                console.log('ICE kandidat i marrë nga:', message.data.userId);
                
                if (this.peerConnection) {
                    this.addIceCandidate(message.data.candidate);
                } else {
                    console.warn('U mor kandidat ICE, por nuk ka lidhje peer.');
                }
                break;
                
            case 'leave':
                // Përdoruesi tjetër është larguar
                console.log('Përdoruesi u largua:', message.data.userId);
                
                // Mbyll lidhjen
                this.close();
                break;
                
            default:
                console.warn('Mesazh i panjohur:', message);
        }
        
        // Thirr callback për mesazhe
        this.onMessage(message);
    }
    
    // Krijo lidhjen peer
    createPeerConnection() {
        try {
            this.peerConnection = new RTCPeerConnection({
                iceServers: this.iceServers
            });
            
            // Shto tracks lokale
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    this.peerConnection.addTrack(track, this.localStream);
                });
            }
            
            // Dëgjo për ICE kandidatë
            this.peerConnection.onicecandidate = this.handleIceCandidate;
            
            // Dëgjo për tracks të largët
            this.peerConnection.ontrack = this.handleRemoteTrack;
            
            // Dëgjo për ndryshime gjendje
            this.peerConnection.oniceconnectionstatechange = this.handleConnectionStateChange;
            this.peerConnection.onconnectionstatechange = this.handleConnectionStateChange;
            
            console.log('Lidhja peer u krijua');
            return true;
        } catch (error) {
            console.error('Gabim në krijimin e lidhjes peer:', error);
            return false;
        }
    }
    
    // Krijo ofertë
    async createOffer() {
        if (!this.peerConnection) {
            console.error('Nuk mund të krijoj ofertë pa lidhje peer');
            return false;
        }
        
        try {
            const offer = await this.peerConnection.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true
            });
            
            await this.peerConnection.setLocalDescription(offer);
            
            // Dërgo ofertën në server
            await this.sendToSignalingServer({
                action: 'offer',
                roomId: this.roomId,
                userId: this.userId,
                offer: offer
            });
            
            console.log('Oferta u krijua dhe u dërgua');
            return true;
        } catch (error) {
            console.error('Gabim në krijimin e ofertës:', error);
            return false;
        }
    }
    
    // Krijo përgjigje
    async createAnswer() {
        if (!this.peerConnection) {
            console.error('Nuk mund të krijoj përgjigje pa lidhje peer');
            return false;
        }
        
        try {
            const answer = await this.peerConnection.createAnswer();
            
            await this.peerConnection.setLocalDescription(answer);
            
            // Dërgo përgjigjen në server
            await this.sendToSignalingServer({
                action: 'answer',
                roomId: this.roomId,
                userId: this.userId,
                answer: answer
            });
            
            console.log('Përgjigjja u krijua dhe u dërgua');
            return true;
        } catch (error) {
            console.error('Gabim në krijimin e përgjigjes:', error);
            return false;
        }
    }
    
    // Trajtimi i ICE kandidatëve
    handleIceCandidate(event) {
        if (event.candidate) {
            // Dërgo kandidatin në server
            this.sendToSignalingServer({
                action: 'candidate',
                roomId: this.roomId,
                userId: this.userId,
                candidate: event.candidate
            }).catch(error => {
                console.error('Gabim në dërgimin e ICE kandidatit:', error);
            });
        } else {
            console.log('ICE candidate gathering completed');
        }
    }
    
    // Shto ICE kandidatin
    async addIceCandidate(candidate) {
        if (!this.peerConnection) {
            console.error('Nuk mund të shtoj ICE kandidat pa lidhje peer');
            return false;
        }
        
        try {
            await this.peerConnection.addIceCandidate(new RTCIceCandidate(candidate));
            console.log('ICE kandidati u shtua me sukses');
            return true;
        } catch (error) {
            console.error('Gabim në shtimin e ICE kandidatit:', error);
            return false;
        }
    }
    
    // Trajtimi i tracks të largët
    handleRemoteTrack(event) {
        if (event.streams && event.streams[0]) {
            this.remoteStream = event.streams[0];
            
            // Shfaq video të largët
            if (this.remoteVideoElement) {
                this.remoteVideoElement.srcObject = this.remoteStream;
            }
            
            // Lajmëro callback
            this.onRemoteStreamAdded(this.remoteStream);
        }
    }
    
    // Trajtimi i ndryshimeve të gjendjes së lidhjes
    handleConnectionStateChange() {
        const iceState = this.peerConnection?.iceConnectionState;
        const connState = this.peerConnection?.connectionState;
        
        console.log(`Gjendje e re lidhje: ICE=${iceState}, Conn=${connState}`);
        
        // Kontrollo nëse lidhja është aktive
        if (iceState === 'connected' || iceState === 'completed' || connState === 'connected') {
            this.isConnected = true;
            this.reconnectAttempts = 0; // Rivendos tentativat e rilidhjes
        } else if (iceState === 'disconnected' || iceState === 'failed' || iceState === 'closed' ||
                  connState === 'disconnected' || connState === 'failed' || connState === 'closed') {
            this.isConnected = false;
            
            // Provo të rilidhet nëse lidhja dështoi
            if ((iceState === 'failed' || connState === 'failed') && 
                this.reconnectAttempts < this.maxReconnectAttempts) {
                console.log('Lidhja dështoi, duke provuar rilidhjen...');
                this.reconnect();
            }
        }
        
        // Lajmëro callback
        this.onConnectionStateChange({
            iceState,
            connState,
            isConnected: this.isConnected
        });
    }
    
    // Funksion për t'u rilidhur
    async reconnect() {
        console.log('Duke u rilidhur...');
        
        // Mbyll lidhjen ekzistuese
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }
        
        // Rikrijo lidhjen
        this.reconnectAttempts++;
        this.createPeerConnection();
        
        if (this.isInitiator) {
            await this.createOffer();
        }
        
        return true;
    }
    
    // Ndryshimi i gjendjes së audios
    toggleAudio() {
        if (!this.localStream) return false;
        
        const audioTracks = this.localStream.getAudioTracks();
        if (audioTracks.length === 0) return false;
        
        this.isAudioMuted = !this.isAudioMuted;
        
        audioTracks.forEach(track => {
            track.enabled = !this.isAudioMuted;
        });
        
        return true;
    }
    
    // Ndryshimi i gjendjes së videos
    toggleVideo() {
        if (!this.localStream) return false;
        
        const videoTracks = this.localStream.getVideoTracks();
        if (videoTracks.length === 0) return false;
        
        this.isVideoOff = !this.isVideoOff;
        
        videoTracks.forEach(track => {
            track.enabled = !this.isVideoOff;
        });
        
        return true;
    }
    
    // Fillimi i ndarjes së ekranit
    async startScreenShare() {
        if (this.isScreenSharing) return false;
        
        try {
            const screenStream = await navigator.mediaDevices.getDisplayMedia({
                video: true
            });
            
            // Ruaj track-un origjinal të videos
            this.originalVideoTrack = this.localStream.getVideoTracks()[0];
            
            // Zëvendëso track-un e videos në lokalStream
            const screenTrack = screenStream.getVideoTracks()[0];
            
            // Zëvendëso track-un në peerConnection
            if (this.peerConnection) {
                const senders = this.peerConnection.getSenders();
                const sender = senders.find(s => 
                    s.track && s.track.kind === 'video'
                );
                
                if (sender) {
                    sender.replaceTrack(screenTrack);
                }
            }
            
            // Zëvendëso track-un në lokalStream
            this.localStream.removeTrack(this.originalVideoTrack);
            this.localStream.addTrack(screenTrack);
            
            // Përditëso video lokale
            if (this.localVideoElement) {
                this.localVideoElement.srcObject = this.localStream;
            }
            
            // Vendos gjendjen
            this.isScreenSharing = true;
            
            // Kur përdoruesi ndalon ndarjen e ekranit
            screenTrack.onended = () => {
                this.stopScreenShare();
            };
            
            return true;
        } catch (error) {
            console.error('Gabim në ndarjen e ekranit:', error);
            return false;
        }
    }
    
    // Ndalimi i ndarjes së ekranit
    stopScreenShare() {
        if (!this.isScreenSharing || !this.originalVideoTrack) return false;
        
        // Zëvendëso track-un në peerConnection
        if (this.peerConnection) {
            const senders = this.peerConnection.getSenders();
            const sender = senders.find(s => 
                s.track && s.track.kind === 'video'
            );
            
            if (sender) {
                sender.replaceTrack(this.originalVideoTrack);
            }
        }
        
        // Zëvendëso track-un në lokalStream
        const screenTrack = this.localStream.getVideoTracks()[0];
        if (screenTrack) {
            screenTrack.stop();
            this.localStream.removeTrack(screenTrack);
            this.localStream.addTrack(this.originalVideoTrack);
        }
        
        // Përditëso video lokale
        if (this.localVideoElement) {
            this.localVideoElement.srcObject = this.localStream;
        }
        
        // Rivendos gjendjen
        this.isScreenSharing = false;
        this.originalVideoTrack = null;
        
        return true;
    }
    
    // Dërgo mesazh chati
    async sendChatMessage(message) {
        try {
            // Dërgo mesazhin përmes një kanali të dhënash ose përmes signalingServer
            await this.sendToSignalingServer({
                action: 'message',
                roomId: this.roomId,
                userId: this.userId,
                username: this.username,
                message: message
            });
            
            return true;
        } catch (error) {
            console.error('Gabim në dërgimin e mesazhit të chat:', error);
            return false;
        }
    }
    
    // Mbyll lidhjen
    async close() {
        // Njofto serverin
        try {
            await this.sendToSignalingServer({
                action: 'leave',
                roomId: this.roomId,
                userId: this.userId
            });
        } catch (error) {
            console.error('Gabim në njoftimin e largimit:', error);
        }
        
        // Ndalo polling
        this.stopPolling();
        
        // Ndalo mediat lokale
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => track.stop());
        }
        
        // Mbyll lidhjen peer
        if (this.peerConnection) {
            this.peerConnection.close();
            this.peerConnection = null;
        }
        
        // Rivendos gjendjen
        this.isConnected = false;
        
        console.log('Lidhja u mbyll');
        return true;
    }
}

// Eksporto klasën
if (typeof module !== 'undefined' && module.exports) {
    module.exports = NoteriaWebRTC;
}