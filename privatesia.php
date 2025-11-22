<?php
// Përfshini lidhjet me bazën e të dhënave ose file-at e nevojshëm
// include 'includes/config.php';
?>

<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politika e Privatësisë | Noteria</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --text-color: #333;
            --light-bg: #f9f9f9;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: var(--light-bg);
            color: var(--text-color);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .privacy-header {
            background-color: var(--primary-color);
            color: var(--white);
            padding: 40px 0;
            text-align: center;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }

        .privacy-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .privacy-header p {
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0.9;
        }

        .privacy-content {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }

        .privacy-section {
            margin-bottom: 40px;
        }

        .privacy-section:last-child {
            margin-bottom: 0;
        }

        .privacy-section h2 {
            color: var(--primary-color);
            font-size: 1.8rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary-color);
        }

        .privacy-section h3 {
            color: var(--secondary-color);
            font-size: 1.4rem;
            margin: 25px 0 15px;
        }

        .privacy-section p, .privacy-section ul {
            margin-bottom: 15px;
        }

        .privacy-section ul {
            padding-left: 20px;
        }

        .privacy-section ul li {
            margin-bottom: 10px;
        }

        .highlight-box {
            background-color: rgba(52, 152, 219, 0.1);
            border-left: 4px solid var(--secondary-color);
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
        }

        .info-box {
            display: flex;
            align-items: center;
            background-color: rgba(46, 204, 113, 0.1);
            border: 1px solid rgba(46, 204, 113, 0.3);
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 20px 0;
        }

        .info-box i {
            font-size: 2rem;
            color: #2ecc71;
            margin-right: 15px;
        }

        .warning-box {
            display: flex;
            align-items: center;
            background-color: rgba(231, 76, 60, 0.1);
            border: 1px solid rgba(231, 76, 60, 0.3);
            padding: 15px;
            border-radius: var(--border-radius);
            margin: 20px 0;
        }

        .warning-box i {
            font-size: 2rem;
            color: var(--accent-color);
            margin-right: 15px;
        }

        .footer {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .footer p {
            margin-bottom: 10px;
        }

        .updated-date {
            font-style: italic;
            margin-top: 40px;
            text-align: right;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .privacy-header {
                padding: 30px 15px;
            }
            
            .privacy-header h1 {
                font-size: 2rem;
            }
            
            .privacy-content {
                padding: 25px;
            }
            
            .privacy-section h2 {
                font-size: 1.6rem;
            }
            
            .privacy-section h3 {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="privacy-header">
        <h1>Politika e Privatësisë</h1>
        <p>Ky dokument përshkruan se si ne mbledhim, përdorim dhe mbrojmë të dhënat tuaja personale gjatë përdorimit të shërbimeve tona.</p>
    </div>

    <div class="privacy-content">
        <div class="privacy-section">
            <h2>Hyrje</h2>
            <p>Mirë se vini në politikën e privatësisë të Noteria-s. Ne e vlerësojmë besimin tuaj dhe jemi të përkushtuar për të mbrojtur privatësinë tuaj. Kjo politikë shpjegon se si ne mbledhim, përdorim dhe mbrojmë të dhënat tuaja personale.</p>
            
            <p>Duke përdorur shërbimet tona, ju pranoni praktikat e përshkruara në këtë politikë të privatësisë. Ne ju inkurajojmë ta lexoni me kujdes për të kuptuar më mirë se si ne ruajmë dhe përpunojmë informacionin tuaj personal.</p>
        </div>

        <div class="privacy-section">
            <h2>Të dhënat që ne mbledhim</h2>
            
            <h3>Të dhënat personale</h3>
            <p>Ne mund të mbledhim të dhënat e mëposhtme personale:</p>
            <ul>
                <li>Informacioni i kontaktit (emri, adresa e email-it, numri i telefonit)</li>
                <li>Informacioni i llogarisë (emri i përdoruesit, fjalëkalimi i koduar)</li>
                <li>Të dhënat e faturimit dhe pagesës</li>
                <li>Adresa fizike (për dërgesa ose fatura)</li>
            </ul>

            <h3>Të dhënat e përdorimit</h3>
            <p>Ne mund të mbledhim gjithashtu informacion rreth mënyrës se si ju përdorni shërbimet tona:</p>
            <ul>
                <li>Të dhënat e regjistrimit (log data)</li>
                <li>Të dhënat e pajisjes (lloji i pajisjes, sistemi operativ)</li>
                <li>Të dhënat e vendndodhjes (bazuar në IP adresën tuaj)</li>
                <li>Informacion mbi përdorimin e shërbimit (faqet e vizituara, kohëzgjatja e vizitave)</li>
                <li>Cookie-t dhe teknologji të ngjashme të përcjelljes</li>
            </ul>

            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <p>Ne nuk mbledhim kurrë më shumë informacion sesa është absolutisht e nevojshme për të ofruar shërbimet tona për ju.</p>
                </div>
            </div>
        </div>

        <div class="privacy-section">
            <h2>Si i përdorim të dhënat tuaja</h2>
            
            <p>Ne përdorim të dhënat që mbledhim për qëllimet e mëposhtme:</p>
            
            <h3>Ofrimi i shërbimeve</h3>
            <ul>
                <li>Për të krijuar dhe menaxhuar llogarinë tuaj</li>
                <li>Për të përpunuar transaksionet dhe pagesat</li>
                <li>Për të personalizuar përvojën tuaj të përdoruesit</li>
                <li>Për të komunikuar me ju rreth shërbimeve tona</li>
            </ul>

            <h3>Përmirësimi dhe analiza</h3>
            <ul>
                <li>Për të analizuar përdorimin e platformës sonë</li>
                <li>Për të përmirësuar shërbimet tona</li>
                <li>Për të zhvilluar produkte dhe veçori të reja</li>
            </ul>

            <h3>Siguria dhe mbrojtja</h3>
            <ul>
                <li>Për të mbrojtur shërbimet tona dhe të dhënat e përdoruesve</li>
                <li>Për të parandaluar, zbuluar dhe hetuar aktivitete mashtruese ose të paligjshme</li>
                <li>Për të verifikuar identitetin tuaj kur është e nevojshme</li>
            </ul>

            <div class="highlight-box">
                <p>Ne nuk do të shesim kurrë të dhënat tuaja personale tek palët e treta për qëllime marketingu pa pëlqimin tuaj të qartë.</p>
            </div>
        </div>

        <div class="privacy-section">
            <h2>Ndarja e të dhënave me palët e treta</h2>
            
            <p>Ne mund të ndajmë të dhënat tuaja personale me kategoritë e mëposhtme të palëve të treta:</p>

            <h3>Kërkesa ligjore</h3>
            <p>Ne mund të ndajmë të dhënat tuaja personale në përgjigje të një procesi ligjor ose kur është e nevojshme për të:</p>
            <ul>
                <li>Zbatuar kushtet tona të shërbimit</li>
                <li>Mbrojtur të drejtat, pronën ose sigurinë tonë</li>
                <li>Përmbushur kërkesat ligjore dhe rregullatore</li>
            </ul>

            <div class="warning-box">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <p>Të gjitha palët e treta që kanë qasje në të dhënat tuaja janë të detyruara të ruajnë konfidencialitetin dhe sigurinë e tyre, dhe t'i përdorin vetëm për qëllimet specifike për të cilat u janë dhënë.</p>
                </div>
            </div>
        </div>

        <div class="privacy-section">
            <h2>Mbajtja dhe siguria e të dhënave</h2>
            
            <h3>Periudha e ruajtjes</h3>
            <p>Ne ruajmë të dhënat tuaja personale për aq kohë sa është e nevojshme për të përmbushur qëllimet për të cilat u mblodhën, përveç rasteve kur ligji kërkon ose lejon një periudhë më të gjatë të ruajtjes.</p>

            <h3>Masat e sigurisë</h3>
            <p>Ne implementojmë masa të përshtatshme teknike dhe organizative për të mbrojtur të dhënat tuaja personale nga humbja aksidentale, përdorimi ose qasja e paautorizuar, ndryshimi ose zbulimi. Këto masa përfshijnë:</p>
            <ul>
                <li>Enkriptimi i të dhënave të ndjeshme</li>
                <li>Kontrollet e aksesit dhe autentikimi i përdoruesit</li>
                <li>Rregulla të rrepta të aksesit për stafin tonë</li>
                <li>Kopje rezervë të rregullta të të dhënave</li>
                <li>Monitorim i vazhdueshëm i sistemeve tona për dobësi të mundshme</li>
            </ul>

            <div class="info-box">
                <i class="fas fa-shield-alt"></i>
                <div>
                    <p>Ndërsa ne bëjmë çdo përpjekje për të mbrojtur të dhënat tuaja, asnjë metodë transmetimi nëpërmjet internetit ose metodë e ruajtjes elektronike nuk është 100% e sigurt. Ne nuk mund të garantojmë sigurinë absolute të të dhënave tuaja.</p>
                </div>
            </div>
        </div>

        <div class="privacy-section">
            <h2>Të drejtat tuaja të privatësisë</h2>
            
            <p>Në varësi të vendndodhjes suaj, ju mund të keni të drejtat e mëposhtme në lidhje me të dhënat tuaja personale:</p>
            
            <ul>
                <li><strong>E drejta për qasje</strong> - Ju keni të drejtë të kërkoni një kopje të të dhënave tuaja personale që ne mbajmë.</li>
                <li><strong>E drejta për korrigjim</strong> - Ju mund të kërkoni që të dhënat e pasakta ose jo të plota të korrigjohen.</li>
                <li><strong>E drejta për fshirje</strong> - Në rrethana të caktuara, ju mund të kërkoni fshirjen e të dhënave tuaja personale.</li>
                <li><strong>E drejta për kufizimin e përpunimit</strong> - Ju mund të kërkoni kufizimin e përpunimit të të dhënave tuaja personale.</li>
                <li><strong>E drejta për kundërshtim</strong> - Ju keni të drejtë të kundërshtoni përpunimin e të dhënave tuaja personale në disa rrethana.</li>
                <li><strong>E drejta për portabilitet të të dhënave</strong> - Ju mund të kërkoni një kopje elektronike të të dhënave tuaja për t'i transferuar te një shërbim tjetër.</li>
            </ul>

            <p>Për të ushtruar ndonjë nga këto të drejta, ju lutemi na kontaktoni në adresën e email-it të dhënë më poshtë. Ne do të përpiqemi t'i përgjigjemi kërkesës suaj brenda 30 ditëve.</p>

            <div class="highlight-box">
                <p>Ne nuk do të diskriminojmë kundër jush për ushtrimin e ndonjë prej të drejtave tuaja të privatësisë.</p>
            </div>
        </div>

        <div class="privacy-section">
            <h2>Cookie-t dhe teknologjitë e përcjelljes</h2>
            
            <p>Ne përdorim cookie-t dhe teknologji të ngjashme të përcjelljes për të mbledhur dhe ruajtur informacion kur ju vizitoni faqen tonë të internetit ose përdorni shërbimet tona. Cookie-t janë file të vegjël që ruhen në shfletuesin ose pajisjen tuaj.</p>

            <h3>Llojet e cookie-ve që përdorim</h3>
            <ul>
                <li><strong>Cookie-t thelbësore</strong> - Të nevojshme për funksionimin e faqes së internetit</li>
                <li><strong>Cookie-t e performancës</strong> - Për të analizuar se si përdoruesit ndërveprojnë me faqen tonë</li>
                <li><strong>Cookie-t funksionale</strong> - Për të mbajtur mend preferencat tuaja</li>
                <li><strong>Cookie-t e targetimit</strong> - Për të ofruar përmbajtje më relevante dhe të personalizuar</li>
            </ul>

            <h3>Kontrolli i cookie-ve</h3>
            <p>Shumica e shfletuesve ju lejojnë të kontrolloni cookie-t nëpërmjet preferencave të tyre. Ju mund të zgjidhni të refuzoni cookie-t, megjithatë, kjo mund të ndikojë në funksionalitetin e shërbimeve tona.</p>
        </div>

        <div class="privacy-section">
            <h2>Ndryshimet në këtë politikë</h2>
            
            <p>Ne mund të përditësojmë këtë politikë privatësie herë pas here për të reflektuar ndryshimet në praktikat tona të privatësisë ose për arsye të tjera operacionale, ligjore ose rregullatore.</p>
            
            <p>Kur bëjmë ndryshime materiale në këtë politikë, ne do t'ju njoftojmë duke postuar njoftimin e dukshëm në faqen tonë të internetit ose duke ju dërguar një email përpara se ndryshimi të hyjë në fuqi.</p>
            
            <p>Ne ju inkurajojmë të rishikoni periodikisht këtë faqe për informacionin më të fundit mbi praktikat tona të privatësisë.</p>
        </div>

        <div class="privacy-section">
            <h2>Na kontaktoni</h2>
            
            <p>Nëse keni pyetje, shqetësime ose kërkesa në lidhje me këtë politikë privatësie ose trajtimin e të dhënave tuaja personale, ju lutemi na kontaktoni në:</p>
            
            <ul>
                <li><strong>Email:</strong> privacy@noteria.com</li>
                <li><strong>Adresa:</strong> Rruga "Ismail Qemali", Nr. 123, Prishtinë, Kosovë</li>
                <li><strong>Telefon:</strong> +383 45 XXX XXXX</li>
            </ul>
            
            <p>Ne do të përpiqemi t'i përgjigjemi të gjitha pyetjeve dhe shqetësimeve tuaja brenda një kohe të arsyeshme.</p>
        </div>

        <p class="updated-date">Përditësuar më: 4 Tetor, 2025</p>
    </div>

    <div class="footer">
        <p>&copy; <?php echo date("Y"); ?> Noteria. Të gjitha të drejtat e rezervuara.</p>
        <p><a href="index.php">Kthehu në faqen kryesore</a></p>
    </div>
</div>

</body>
</html>