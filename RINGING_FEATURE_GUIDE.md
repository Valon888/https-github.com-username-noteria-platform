# 🔔 Guida për Ringing Feature në Video Thirrjet

## Përshkrimi
Ky sistem lejon përdoruesit të dëgjojnë një zile (ringtone) kur merrin një thirrje video, përsëri si në Viber dhe WhatsApp.

---

## 📁 Fichë të Përdorura

### 1. **video_call.php** (Faqja kryesore)
- Përmban:
  - Element `<audio>` për luajtjen e ziles
  - Modal HTML për shfaqjen e thirrjes hyrëse
  - CSS animacione për modalin
  - JavaScript functions për menaxhimin e ringing-ut

### 2. **Ringtone Files** (në direktoriumi root)
```
ringtone-031-437514.mp3
ringtone-030-437513.mp3
phone-ringtone-telephone-324474.mp3
phone-calling-sfx-317333.mp3
```

### 3. **test_ringing.html** (Test page - opcional)
- Faqe për testimin e ringing-ut jashtë video thirrjes

---

## 🎵 JavaScript Functions

### 1. **playRingtone()**
```javascript
function playRingtone() {
    var audio = document.getElementById('ringtone');
    if (audio) {
        audio.volume = 0.7; // 70% volume
        audio.play();
    }
}
```
**Përdorim:** Fillon luajtjen e ziles

---

### 2. **stopRingtone()**
```javascript
function stopRingtone() {
    var audio = document.getElementById('ringtone');
    if (audio) {
        audio.pause();
        audio.currentTime = 0;
    }
}
```
**Përdorim:** Ndalon zilen

---

### 3. **showIncomingCall(callerName)**
```javascript
function showIncomingCall(callerName) {
    var modal = document.getElementById('incomingCallModal');
    var nameElem = document.getElementById('callerName');
    
    if (modal) {
        nameElem.textContent = callerName || 'Noter';
        modal.classList.add('show');
        playRingtone();
        
        // Auto-hide after 30 seconds
        setTimeout(function() {
            if (modal.classList.contains('show')) {
                rejectCall();
            }
        }, 30000);
    }
}
```
**Përdorim:** Shfaq modalin e thirrjes hyrëse dhe fillon zilen

---

### 4. **acceptCall()**
```javascript
function acceptCall() {
    stopRingtone();
    var modal = document.getElementById('incomingCallModal');
    if (modal) {
        modal.classList.remove('show');
    }
}
```
**Përdorim:** Pranohet thirrja dhe ndalon zila

---

### 5. **rejectCall()**
```javascript
function rejectCall() {
    stopRingtone();
    var modal = document.getElementById('incomingCallModal');
    if (modal) {
        modal.classList.remove('show');
    }
}
```
**Përdorim:** Refuzohet thirrja dhe ndalon zila

---

## 🎨 Modal Styling

Modali ka:
- **Avatar** me pulsing glow effect
- **Caller name** (emri i llamuesit)
- **Status** - "Po thërret..." (blinking effect)
- **Action buttons**:
  - ✅ Green button - Prano
  - ❌ Red button - Refuzo

---

## 🔧 Si të Integrojsh në Jitsi Events

Kur dikush bashkohet në video thirrje, mund të thërret:

```javascript
// Kur dikush bashkohet
api.addEventListener('participantJoined', function(participant) {
    showIncomingCall(participant.name);
});

// Kur dikush largohet
api.addEventListener('participantLeft', function(participant) {
    stopRingtone();
});
```

---

## 📱 Testim

### Metodë 1: Faqja e testit
1. Shko në `http://localhost/noteria/test_ringing.html`
2. Kliko "Luaj Zilen" për të testuar zilen
3. Kliko "Simuloj Thirrje Hyrëse" për teste të plotë

### Metodë 2: Video thirrje aktuale
Integrimi me Jitsi events do të aktivizojë ringing-un automatikisht kur merret thirrje

---

## 🔊 Konfigurimi i Ziles

Për të ndryshuar ringtone-in:
1. Zëvendëso `ringtone-031-437514.mp3` me ringtone tjetër
2. Ndrysho `src` në element `<audio>`:
```html
<audio id="ringtone" preload="auto" loop>
    <source src="ringtone_i_ri.mp3" type="audio/mpeg">
</audio>
```

---

## 🎵 Ringtone të Disponueshme

- `ringtone-031-437514.mp3` - Moderni (përpara përzgjedhur)
- `ringtone-030-437513.mp3` - Klasik
- `phone-ringtone-telephone-324474.mp3` - Telefon
- `phone-calling-sfx-317333.mp3` - SFX

---

## ⚙️ Auto-Timeout

Nëse thirrja nuk pranohet në 30 sekonda, ajo refuzohet automatikisht.

Për të ndryshuar:
```javascript
setTimeout(function() {
    if (modal.classList.contains('show')) {
        rejectCall();
    }
}, 30000); // 30000 ms = 30 sekonda
```

---

## 📝 Shënime

- Zila luan vetëm kur modali shfaqet
- Audio volume = 70% (mund të ndryshohet)
- Modali ka animacione smooth fade-in
- Buttons janë responsive me hover effects
- Compatible me të gjithë browserët modernë

---

## 🐛 Troubleshooting

**Zila nuk luan:**
- Kontrolloni nëse ringtone file ekziston në direktoriumi
- Kontrolloni browser console për errors
- Provoni browser i ndryshëm
- Sigurohuni se audio nuk është muted në sistem

**Modali nuk shfaqet:**
- Kontrolloni JavaScript console
- Verifikoni se element-i #incomingCallModal ekziston në HTML

---

## 📞 Kontakt & Suporta
Për çështje, kontaktoni administratorin!
