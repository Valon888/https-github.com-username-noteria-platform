# Noteria - Udhëzues për Video Thirrjet në Kohë Reale

## Përshkrim i Përgjithshëm

Sistemi i video thirrjeve në Noteria është implementuar duke përdorur teknologjinë WebRTC (Web Real-Time Communication), e cila mundëson komunikimin në kohë reale mes përdoruesve pa nevojën e plugin-ave shtesë. Kjo teknologji është e integruar direkt në shfletuesit modernë dhe ofron komunikim të sigurt me enkriptim end-to-end.

## Struktura e Implementimit

Sistemi përbëhet nga këto komponente kryesore:

1. **Klienti WebRTC (`noteria-webrtc.js`)**:
   - Menaxhon lidhjet peer-to-peer
   - Merret me aksesin në kamerë dhe mikrofon
   - Ndërton dhe proceson ofertat dhe përgjigjet WebRTC
   - Trajton ICE kandidatët dhe lidhjet rrjetore

2. **Serveri i Sinjalizimit (`video_call_signaling.php`)**:
   - Koordinon shkëmbimin e mesazheve mes përdoruesve
   - Menaxhon dhomat e komunikimit
   - Trajton hyrjet dhe daljet e përdoruesve

3. **Ndërfaqja e Video Thirrjes (`video_call_template.php`)**:
   - Shfaq video-streams lokale dhe të largët
   - Ofron kontrollet e thirrjes (mikrofon, kamera, ndarja e ekranit)
   - Tregon statusin e lidhjes dhe kohëzgjatjen e thirrjes

4. **Shërbimet Mbështetëse**:
   - `update_call_duration.php` për ruajtjen e statistikave të thirrjeve
   - `video_call.php` për menaxhimin e thirrjeve dhe integrimin me notifikimet

## Databaza

Sistemi përdor disa tabela në databazë:

- `video_calls`: Ruan të dhënat e thirrjeve
- `call_history`: Mban historikun e thirrjeve
- `webrtc_rooms`: Menaxhon dhomat e komunikimit
- `webrtc_participants`: Ruan pjesëmarrësit në dhoma
- `webrtc_messages`: Përdoret për shkëmbimin e mesazheve të sinjalizimit

## Si Funksionon

### Krijimi i një Thirrjeje

1. Përdoruesi klikon butonin "Thirr" në një notifikim ose në profilin e një përdoruesi tjetër
2. Sistemi krijon një hyrje të re në tabelën `video_calls` me status "pending"
3. Dërgohet një notifikim në kohë reale tek marrësi
4. Përdoruesi që inicion thirrjen kalon në faqen e pritjes

### Pranimi i një Thirrjeje

1. Marrësi shikon një notifikim për thirrjen hyrëse
2. Nëse e pranon, statusi i thirrjes bëhet "active"
3. Të dy përdoruesit kalojnë në ndërfaqen e video thirrjes
4. Fillon shkëmbimi i sinjaleve WebRTC përmes serverit të sinjalizimit

### Procesi WebRTC

1. **Përgatitja e Medias**: Secili përdorues akseson kamerën dhe mikrofonin lokal
2. **Krijimi i Lidhjes**: Krijohet një objekt `RTCPeerConnection` në secilën anë
3. **Krijimi i Ofertës**: Përdoruesi iniciues krijon një "ofertë" SDP
4. **Sinjalizimi**: Oferta dërgohet te marrësi përmes serverit të sinjalizimit
5. **Përgjigjja**: Marrësi krijon një "përgjigje" SDP dhe e dërgon te iniciuesi
6. **ICE Kandidatët**: Shkëmbehen kandidatët ICE për të mundësuar lidhje direkte
7. **Lidhje Direkte**: Pasi lidhja vendoset, video dhe audio transmetohen direkt pa kaluar nga serveri

### Përfundimi i Thirrjes

1. Kur një përdorues klikon "Përfundo Thirrjen", lidhja mbyllet
2. Kohëzgjatja e thirrjes ruhet në databazë
3. Statusi i thirrjes ndryshon në "ended"
4. Përdoruesi kthehet në ndërfaqen kryesore

## Karakteristikat

- **Komunikim në Kohë Reale**: Video dhe audio pa vonesa të ndjeshme
- **Ndarja e Ekranit**: Mundësia për të ndarë ekranin me përdoruesin tjetër
- **Chat i Integruar**: Mundësia për të shkëmbyer mesazhe teksti gjatë thirrjes
- **Kontrollet e Thirrjes**: Aktivizim/çaktivizim i mikrofonit dhe kamerës
- **Statistikat e Thirrjes**: Ruajtja e kohëzgjatjes dhe detajeve të tjera
- **Ndërfaqe Responsive**: Funksionale në pajisje të ndryshme
- **Sigurim i Enkriptimit**: Komunikim i sigurt end-to-end

## Kërkesat Teknike

- **Shfletues i Përditësuar**: Chrome, Firefox, Safari, Edge (versione të reja)
- **Akses në Kamerë/Mikrofon**: Përdoruesi duhet të lejojë aksesin
- **Lidhje Interneti Stabile**: Rekomandohet minimum 500 kbps në të dy drejtimet

## Zgjidhja e Problemeve

### Nuk shihet video e përdoruesit tjetër
- Kontrollo nëse përdoruesi tjetër ka aktivizuar kamerën
- Kontrollo statusin e lidhjes në kënd të ekranit
- Provo të rifreskosh faqen dhe të rilidhesh

### Probleme me audion
- Kontrollo nëse mikrofoni është aktivizuar
- Kontrollo nëse volumi është i ndezur
- Kontrollo nëse është zgjedhur pajisja e saktë audio

### Thirrja shkëputet vazhdimisht
- Kontrollo cilësinë e internetit
- Zvogëlo rezolucionin e videos
- Çaktivizo videon nëse lidhja është e dobët

### Lidhja nuk vendoset
- Kontrollo nëse të dy jeni në shfletues të mbështetur
- Kontrollo nëse ka firewall që bllokon lidhjet WebRTC
- Provo të lidhesh nga një rrjet tjetër

## Integrimi me Sistemin e Notifikimeve

Sistemi i video thirrjeve është i integruar ngushtësisht me sistemin ekzistues të notifikimeve në Noteria:

1. **Notifikim për Thirrje Hyrëse**: Dërgon notifikim në kohë reale kur një thirrje është iniciuar
2. **Thirrje nga Notifikimi**: Mund të iniciohet një thirrje direkt nga një notifikim ekzistues
3. **Statusi i Notifikimit**: Notifikimet përditësohen bazuar në statusin e thirrjes (pranuar, refuzuar, etj.)

## Siguria dhe Privatësia

- Të gjitha video thirrjet janë të enkriptuara end-to-end
- Nuk ruhen video apo audio të thirrjeve
- Ruhen vetëm metadata si kohëzgjatja dhe pjesëmarrësit
- Serveri i sinjalizimit vetëm ndihmon në vendosjen e lidhjes, por nuk ka akses në përmbajtjen e komunikimit

---

## Instalimi

Për të aktivizuar sistemin e video thirrjeve në platformën Noteria, ndiqni këto hapa:

1. Importoni skemën SQL nga `sql/webrtc_tables.sql`
2. Kopjoni fajllat e implementimit në direktorinë e projektit
3. Integroni thirrjet në ndërfaqen tuaj ekzistuese

## Zhvillimi i Mëtejshëm

- Shtimi i funksionalitetit të dhomave me shumë pjesëmarrës
- Regjistrimi i thirrjeve (me pëlqimin e përdoruesve)
- Integrim me kalendarin për thirrje të planifikuara
- Implementimi i filtrave të videos
- Optimizimi i përdorimit të bandwidth