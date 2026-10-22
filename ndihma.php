<?php
// Start output buffering immediately - prevents "headers already sent" errors
ob_start();

// Include performance monitoring
require_once 'performance.php';

// Include advanced security system
require_once 'security.php';

// Initialize security system
initSecurity();

// Session is already started by security system
$is_logged_in = isset($_SESSION['user_id']);

// Multilingual system for Help Center
$langs = ['sq' => 'Shqip', 'sr' => 'Српски', 'en' => 'English'];
$lang = $_GET['lang'] ?? 'sq';
if (!in_array($lang, array_keys($langs))) $lang = 'sq';

$text = [
    'title' => [
        'sq' => 'Ndihma | e-Noteria | Republika e Kosovës',
        'sr' => 'Помоћ | e-Noteria | Република Косово',
        'en' => 'Help | e-Noteria | Republic of Kosovo',
    ],
    'nav' => [
        'services'  => ['sq' => 'Shërbimet',      'sr' => 'Услуге',           'en' => 'Services'],
        'news'      => ['sq' => 'Lajme',           'sr' => 'Вести',            'en' => 'News'],
        'help'      => ['sq' => 'Ndihma',          'sr' => 'Помоћ',            'en' => 'Help'],
        'dashboard' => ['sq' => 'Paneli',          'sr' => 'Контролна табла',  'en' => 'Dashboard'],
        'login'     => ['sq' => 'Kyçuni',          'sr' => 'Пријава',          'en' => 'Login'],
    ],
    'hero' => [
        'title' => [
            'sq' => 'Qendra e Ndihmës',
            'sr' => 'Центар за помоћ',
            'en' => 'Help Center',
        ],
        'desc' => [
            'sq' => 'Gjeni përgjigjet për pyetjet tuaja dhe merrni ndihmë për të gjitha shërbimet tona',
            'sr' => 'Пронађите одговоре на ваша питања и добијте помоћ за све наше услуге',
            'en' => 'Find answers to your questions and get help for all our services',
        ],
    ],
    'faq_heading' => [
        'sq' => 'Pyetjet e Shpeshta',
        'sr' => 'Често постављана питања',
        'en' => 'Frequently Asked Questions',
    ],
    'faq_users_heading' => [
        'sq' => 'Pyetjet për Përdoruesit e Thjeshtë',
        'sr' => 'Питања за обичне кориснике',
        'en' => 'Questions for Regular Users',
    ],
    'contact_heading' => [
        'sq' => 'Na Kontaktoni',
        'sr' => 'Контактирајте нас',
        'en' => 'Contact Us',
    ],
    'quicklinks_heading' => [
        'sq' => 'Linqe të Shpejta',
        'sr' => 'Брзи линкови',
        'en' => 'Quick Links',
    ],
    'faq' => [
        [
            'q' => [
                'sq' => 'Si mund të regjistrohem si zyrë noteriale?',
                'sr' => 'Kako da se registrujem kao notarska kancelarija?',
                'en' => 'How can I register as a notary office?',
            ],
            'a' => [
                'sq' => '<p>Për të regjistruar zyrën tuaj noteriale në platformën e-Noteria, ndiqni këto hapa:</p><ol><li>Klikoni butonin "Kyçuni" në krye të faqes</li><li>Zgjedhni "Regjistrohu si Noter"</li><li>Plotësoni të dhënat e zyrës suaj dhe informacionet personale</li><li>Ngarkoni dokumentet e nevojshme për verifikim</li><li>Paguani abonimin mujor prej 150€</li></ol><p>Pasi të verifikohet llogaria juaj, do të keni akses të plotë në të gjitha shërbimet.</p>',
                'sr' => '<p>Da biste registrovali svoju notarsku kancelariju na platformi e-Noteria, sledite ove korake:</p><ol><li>Kliknite na dugme "Prijava" na vrhu stranice</li><li>Izaberite "Registruj se kao notar"</li><li>Popunite podatke o kancelariji i lične informacije</li><li>Postavite potrebna dokumenta za verifikaciju</li><li>Platite mesečnu pretplatu od 150€</li></ol><p>Nakon verifikacije naloga, imaćete pun pristup svim uslugama.</p>',
                'en' => '<p>To register your notary office on the e-Noteria platform, follow these steps:</p><ol><li>Click the "Login" button at the top of the page</li><li>Select "Register as Notary"</li><li>Fill in your office and personal information</li><li>Upload the required documents for verification</li><li>Pay the monthly subscription of €150</li></ol><p>Once your account is verified, you will have full access to all services.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të regjistrohem si përdorues i thjeshtë?',
                'sr' => 'Kako da se registrujem kao običan korisnik?',
                'en' => 'How can I register as a regular user?',
            ],
            'a' => [
                'sq' => '<p>Për të regjistruar si përdorues i thjeshtë, ndiqni këto hapa:</p><ol><li>Klikoni butonin "Kyçuni" në krye të faqes</li><li>Zgjedhni "Regjistrohu si Përdorues"</li><li>Plotësoni të dhënat tuaja personale (emër, mbiemër, email, numër telefoni)</li><li>Verifikoni email-in tuaj duke klikuar në linkun që do të merrni</li><li>Krijoni fjalëkalimin dhe hyni në llogari</li></ol><p>Si përdorues i regjistruar, mund të rezervoni termine, kontrolloni statusin e aplikimeve dhe verifikoni dokumente. Regjistrimi është falas.</p>',
                'sr' => '<p>Da biste se registrovali kao običan korisnik, sledite ove korake:</p><ol><li>Kliknite na dugme "Prijava" na vrhu stranice</li><li>Izaberite "Registruj se kao korisnik"</li><li>Unesite svoje lične podatke (ime, prezime, email, broj telefona)</li><li>Potvrdite svoj email klikom na link koji ćete dobiti</li><li>Kreirajte lozinku i prijavite se</li></ol><p>Kao registrovani korisnik, možete zakazivati termine, proveravati status aplikacija i verifikovati dokumente. Registracija je besplatna.</p>',
                'en' => '<p>To register as a regular user, follow these steps:</p><ol><li>Click the "Login" button at the top of the page</li><li>Select "Register as User"</li><li>Fill in your personal details (name, surname, email, phone number)</li><li>Verify your email by clicking the link you receive</li><li>Create a password and log in</li></ol><p>As a registered user, you can book appointments, check your application status, and verify documents. Registration is free.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Çfarë përfshin çmimi i abonimit 150€?',
                'sr' => 'Šta je uključeno u mesečnoj pretplati od 150€?',
                'en' => 'What does the monthly subscription of €150 include?',
            ],
            'a' => [
                'sq' => '<p>Abonimi mujor prej 150€ përfshin:</p><ul><li>Akses të plotë në platformën cloud-based</li><li>Sistemin e rezervimeve online</li><li>Verifikimin elektronik të dokumenteve</li><li>Ndjekjen e statusit të aplikimeve</li><li>Mbështetje teknike 24/7</li><li>Backup dhe siguri të të dhënave</li><li>Raportime dhe statistika të detajuara</li></ul>',
                'sr' => '<p>Mesečna pretplata od 150€ uključuje:</p><ul><li>Pun pristup cloud platformi</li><li>Sistem za online rezervacije</li><li>Elektronsku verifikaciju dokumenata</li><li>Praćenje statusa aplikacija</li><li>Tehničku podršku 24/7</li><li>Backup i bezbednost podataka</li><li>Detaljne izveštaje i statistike</li></ul>',
                'en' => '<p>The monthly subscription of €150 includes:</p><ul><li>Full access to the cloud-based platform</li><li>Online reservation system</li><li>Electronic verification of documents</li><li>Tracking of application status</li><li>Technical support 24/7</li><li>Data backup and security</li><li>Detailed reports and statistics</li></ul>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të verifikoj një dokument?',
                'sr' => 'Kako da verifikujem dokument?',
                'en' => 'How can I verify a document?',
            ],
            'a' => [
                'sq' => '<p>Për të verifikuar një dokument:</p><ol><li>Shkoni në faqen "Verifikimi" nga menuja</li><li>Shkruani kodin e verifikimit të dokumentit</li><li>Klikoni butonin "Verifiko Dokumentin"</li><li>Do të merrni rezultatin menjëherë</li></ol><p>Sistemi do të tregojë nëse dokumenti është i vlefshëm, i skaduar, ose i falsifikuar.</p>',
                'sr' => '<p>Da biste verifikovali dokument:</p><ol><li>Idite na stranicu "Verifikacija" iz menija</li><li>Unesite verifikacioni kod dokumenta</li><li>Kliknite dugme "Verifikuj dokument"</li><li>Rezultat ćete dobiti odmah</li></ol><p>Sistem će pokazati da li je dokument važeći, istekao ili falsifikovan.</p>',
                'en' => '<p>To verify a document:</p><ol><li>Go to the "Verification" page from the menu</li><li>Enter the document verification code</li><li>Click the "Verify Document" button</li><li>You will receive the result immediately</li></ol><p>The system will show whether the document is valid, expired, or falsified.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të kontrolloj statusin e një aplikimi?',
                'sr' => 'Kako da proverim status prijave?',
                'en' => 'How can I check the status of an application?',
            ],
            'a' => [
                'sq' => '<p>Për të kontrolluar statusin e aplikimit tuaj:</p><ol><li>Shkoni në faqen "Statusi" nga menuja</li><li>Shkruani numrin e referencës që keni marrë në email</li><li>Klikoni butonin "Kontrollo Statusin"</li></ol><p>Statusi mund të jetë: Në Proces, Aprovuar dhe Përfunduar, ose Refuzuar.</p>',
                'sr' => '<p>Da biste proverili status svoje prijave:</p><ol><li>Idite na stranicu "Status" iz menija</li><li>Unesite referentni broj koji ste dobili emailom</li><li>Kliknite dugme "Proveri status"</li></ol><p>Status može biti: U obradi, Odobreno i završeno, ili Odbijeno.</p>',
                'en' => '<p>To check the status of your application:</p><ol><li>Go to the "Status" page from the menu</li><li>Enter the reference number you received by email</li><li>Click the "Check Status" button</li></ol><p>The status can be: In Progress, Approved and Completed, or Rejected.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Çfarë dokumente duhet për regjistrimin?',
                'sr' => 'Koja dokumenta su potrebna za registraciju?',
                'en' => 'What documents are required for registration?',
            ],
            'a' => [
                'sq' => '<p>Për regjistrimin si zyrë noteriale nevojiten:</p><ul><li>Licencë e noterit (kopje e noterizuar)</li><li>Certifikatë e regjistrimit të biznesit</li><li>ID personale e noterit</li><li>Prova e adresës së zyrës</li><li>Certifikatë tatimore</li></ul><p>Të gjitha dokumentet duhet të jenë në format PDF dhe jo më të mëdha se 5MB.</p>',
                'sr' => '<p>Za registraciju notarske kancelarije potrebna su sledeća dokumenta:</p><ul><li>Notarska licenca (overena kopija)</li><li>Potvrda o registraciji preduzeća</li><li>Lična karta notara</li><li>Dokaz adrese kancelarije</li><li>Poreski sertifikat</li></ul><p>Sva dokumenta moraju biti u PDF formatu i ne veća od 5MB.</p>',
                'en' => '<p>To register as a notary office, the following documents are required:</p><ul><li>Notary license (notarized copy)</li><li>Business registration certificate</li><li>Notary personal ID</li><li>Proof of office address</li><li>Tax certificate</li></ul><p>All documents must be in PDF format and no larger than 5MB.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të kontaktoj mbështetjen teknike?',
                'sr' => 'Kako da kontaktiram tehničku podršku?',
                'en' => 'How can I contact technical support?',
            ],
            'a' => [
                'sq' => '<p>Mbështetja teknike është në dispozicion 24/7 përmes:</p><ul><li><strong>Telefon:</strong> 038 200 100</li><li><strong>Email:</strong> support@e-noteria.rks-gov.net</li><li><strong>Chat Live:</strong> Në këndin e poshtëm të djathtë të faqes</li></ul><p>Koha mesatare e përgjigjes është 15 minuta gjatë orëve të punës dhe 2 orë jashtë orëve të punës.</p>',
                'sr' => '<p>Tehnička podrška je dostupna 24/7 putem:</p><ul><li><strong>Telefon:</strong> 038 200 100</li><li><strong>Email:</strong> support@e-noteria.rks-gov.net</li><li><strong>Live chat:</strong> U donjem desnom uglu stranice</li></ul><p>Prosečno vreme odgovora je 15 minuta tokom radnog vremena i 2 sata van radnog vremena.</p>',
                'en' => '<p>Technical support is available 24/7 via:</p><ul><li><strong>Phone:</strong> 038 200 100</li><li><strong>Email:</strong> support@e-noteria.rks-gov.net</li><li><strong>Live Chat:</strong> In the bottom right corner of the page</li></ul><p>Average response time is 15 minutes during business hours and 2 hours outside business hours.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si funksionon chat-i live?',
                'sr' => 'Kako funkcioniše live chat?',
                'en' => 'How does the live chat work?',
            ],
            'a' => [
                'sq' => '<p>Chat-i live është i disponueshëm 24/7 në këndin e poshtëm të djathtë:</p><ul><li>Klikoni ikonën e chat-it (💬) për të hapur dritaren</li><li>Shkruani pyetjen tuaj dhe shtypni Enter ose klikoni butonin dërgo</li><li>Merrni përgjigje të menjëhershme nga mbështetja jonë</li><li>Chat-i mbështet pyetje për regjistrimin, shërbimet, çmimet dhe ndihmë teknike</li></ul><p>Nëse nuk jemi online, mund të na lini mesazhin tuaj dhe do t\'ju kthehemi sa më shpejt.</p>',
                'sr' => '<p>Live chat je dostupan 24/7 u donjem desnom uglu:</p><ul><li>Kliknite ikonu chata (💬) da otvorite prozor</li><li>Napišite pitanje i pritisnite Enter ili kliknite dugme pošalji</li><li>Dobijte trenutni odgovor od naše podrške</li><li>Chat podržava pitanja o registraciji, uslugama, cenama i tehničkoj pomoći</li></ul><p>Ako nismo online, možete nam ostaviti poruku i odgovorićemo što pre.</p>',
                'en' => '<p>Live chat is available 24/7 in the bottom right corner:</p><ul><li>Click the chat icon (💬) to open the window</li><li>Type your question and press Enter or click the send button</li><li>Get instant replies from our support team</li><li>Chat supports questions about registration, services, pricing, and technical help</li></ul><p>If we are offline, you can leave us a message and we will get back to you as soon as possible.</p>',
            ],
        ],
    ],
    'faq_users' => [
        [
            'q' => [
                'sq' => 'Si mund të bëj një rezervim termesh?',
                'sr' => 'Kako da zakazem termin?',
                'en' => 'How can I book an appointment?',
            ],
            'a' => [
                'sq' => '<p>Për të bërë një rezervim termesh online:</p><ol><li>Hyni në llogarinë tuaj</li><li>Shkoni në seksionin "Rezervimet"</li><li>Zgjedhni zyrën noteriale</li><li>Zgjedhni datën dhe orën e dëshiruar</li><li>Plotësoni arsyetimin e vizitës</li><li>Konfirmoni rezervimin</li></ol><p>Do të merrni konfirmim përmes email dhe SMS me të gjitha detalet.</p>',
                'sr' => '<p>Da biste zakazali termin online:</p><ol><li>Prijavite se na nalog</li><li>Idite u odeljak "Rezervacije"</li><li>Izaberite notarsku kancelariju</li><li>Izaberite željeni datum i vreme</li><li>Popunite razlog posete</li><li>Potvrdite rezervaciju</li></ol><p>Dobićete potvrdu putem emaila i SMS-a sa svim detaljima.</p>',
                'en' => '<p>To book an appointment online:</p><ol><li>Log in to your account</li><li>Go to the "Reservations" section</li><li>Select the notary office</li><li>Choose your desired date and time</li><li>Fill in the reason for the visit</li><li>Confirm the reservation</li></ol><p>You will receive a confirmation via email and SMS with all the details.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të ndryshoj të dhënat e mia personale?',
                'sr' => 'Kako da promenim lične podatke?',
                'en' => 'How can I update my personal information?',
            ],
            'a' => [
                'sq' => '<p>Për të ndryshuar të dhënat tuaja personale:</p><ol><li>Hyni në llogarinë tuaj</li><li>Shkoni në "Profili" ose "Cilësimet"</li><li>Klikoni "Ndrysho të dhënat e mia"</li><li>Përditësoni emrin, numrin e telefonit, adresën, etj.</li><li>Klikoni "Ruaj ndryshimet"</li></ol><p>Ndryshimet zbatohen menjëherë në sistem.</p>',
                'sr' => '<p>Da biste promenili lične podatke:</p><ol><li>Prijavite se na nalog</li><li>Idite na "Profil" ili "Podešavanja"</li><li>Kliknite "Izmeni moje podatke"</li><li>Ažurirajte ime, broj telefona, adresu itd.</li><li>Kliknite "Sačuvaj promene"</li></ol><p>Promene se odmah primenjuju u sistemu.</p>',
                'en' => '<p>To update your personal information:</p><ol><li>Log in to your account</li><li>Go to "Profile" or "Settings"</li><li>Click "Edit my information"</li><li>Update your name, phone number, address, etc.</li><li>Click "Save changes"</li></ol><p>Changes take effect immediately in the system.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të shoh historikun e shërbimeve?',
                'sr' => 'Kako da vidim istoriju usluga?',
                'en' => 'How can I view my service history?',
            ],
            'a' => [
                'sq' => '<p>Për të parë historikun e shërbimeve tuaja:</p><ol><li>Hyni në llogarinë tuaj</li><li>Shkoni në seksionin "Historik"</li><li>Shihni të gjitha shërbimet e marra</li><li>Filtrojini sipas datës ose zyrës</li><li>Shkarkoni dokumentet ose faturimet</li></ol>',
                'sr' => '<p>Da biste videli istoriju usluga:</p><ol><li>Prijavite se na nalog</li><li>Idite u odeljak "Istorija"</li><li>Vidite sve primljene usluge</li><li>Filtrirajte po datumu ili kancelariji</li><li>Preuzmite dokumenta ili račune</li></ol>',
                'en' => '<p>To view your service history:</p><ol><li>Log in to your account</li><li>Go to the "History" section</li><li>View all received services</li><li>Filter by date or office</li><li>Download documents or invoices</li></ol>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të rivendos fjalëkalimin nëse e kam harruar?',
                'sr' => 'Kako da resetujem zaboravljenu lozinku?',
                'en' => 'How can I reset my forgotten password?',
            ],
            'a' => [
                'sq' => '<p>Nëse keni harruar fjalëkalimin:</p><ol><li>Shkoni në faqen e login-it</li><li>Klikoni "Harrova fjalëkalimin"</li><li>Shkruani email-in tuaj</li><li>Kontrolloni email-in tuaj për linkun e rivënies</li><li>Klikoni linkun dhe krijoni fjalëkalim të ri</li></ol><p>Linku i rivënies është i vlefshëm për 24 orë.</p>',
                'sr' => '<p>Ako ste zaboravili lozinku:</p><ol><li>Idite na stranicu za prijavu</li><li>Kliknite "Zaboravio sam lozinku"</li><li>Unesite svoju email adresu</li><li>Proverite email za link za resetovanje</li><li>Kliknite link i kreirajte novu lozinku</li></ol><p>Link za resetovanje važi 24 sata.</p>',
                'en' => '<p>If you have forgotten your password:</p><ol><li>Go to the login page</li><li>Click "Forgot my password"</li><li>Enter your email address</li><li>Check your email for the reset link</li><li>Click the link and create a new password</li></ol><p>The reset link is valid for 24 hours.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'A mund të përdor platformën në celular?',
                'sr' => 'Mogu li koristiti platformu na mobilnom telefonu?',
                'en' => 'Can I use the platform on mobile?',
            ],
            'a' => [
                'sq' => '<p>Po! Platforma e-Noteria është plotësisht responsive dhe funksionon në:</p><ul><li>✓ Telefona inteligjente (iOS dhe Android)</li><li>✓ Tabela</li><li>✓ Kompjuterë të stolit dhe laptopot</li></ul><p>Mund të bëni rezervime, kontrolloni statusin dhe verifikoni dokumente direkt nga telefoni juaj.</p>',
                'sr' => '<p>Da! Platforma e-Noteria je potpuno responzivna i radi na:</p><ul><li>✓ Pametnim telefonima (iOS i Android)</li><li>✓ Tabletima</li><li>✓ Desktop računarima i laptopovima</li></ul><p>Možete zakazivati termine, proveravati status i verifikovati dokumenta direktno s telefona.</p>',
                'en' => '<p>Yes! The e-Noteria platform is fully responsive and works on:</p><ul><li>✓ Smartphones (iOS and Android)</li><li>✓ Tablets</li><li>✓ Desktop computers and laptops</li></ul><p>You can book appointments, check status, and verify documents directly from your phone.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'A janë të sigurt të dhënat e mia personale?',
                'sr' => 'Da li su moji lični podaci bezbedni?',
                'en' => 'Are my personal data secure?',
            ],
            'a' => [
                'sq' => '<p>Po! Siguria e të dhënave tuaja është prioriteti ynë kryesor. Përdorim:</p><ul><li>🔐 Enkriptimin SSL/TLS (256-bit)</li><li>🔒 Serverë të sigurë në Kosovë</li><li>📋 Respektim të plotë të GDPR</li><li>🛡️ Firewall dhe sisteme zbulimi të kërcënimeve</li><li>📊 Backup ditor i të dhënave</li><li>👥 Asnjë të dhënë nuk ndahet me palë të treta</li></ul>',
                'sr' => '<p>Da! Bezbednost vaših podataka je naš prioritet. Koristimo:</p><ul><li>🔐 SSL/TLS enkripciju (256-bit)</li><li>🔒 Sigurne servere u Kosovu</li><li>📋 Potpunu usklađenost sa GDPR</li><li>🛡️ Firewall i sisteme za detekciju pretnji</li><li>📊 Dnevni backup podataka</li><li>👥 Podaci se ne dele sa trećim stranama</li></ul>',
                'en' => '<p>Yes! The security of your data is our top priority. We use:</p><ul><li>🔐 SSL/TLS encryption (256-bit)</li><li>🔒 Secure servers in Kosovo</li><li>📋 Full GDPR compliance</li><li>🛡️ Firewall and threat detection systems</li><li>📊 Daily data backup</li><li>👥 No data is shared with third parties</li></ul>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të anuloj një rezervim?',
                'sr' => 'Kako da otkažem rezervaciju?',
                'en' => 'How can I cancel a reservation?',
            ],
            'a' => [
                'sq' => '<p>Për të anuluar një rezervim:</p><ol><li>Hyni në llogarinë tuaj</li><li>Shkoni në "Rezervimet"</li><li>Gjeni rezervimin që dëshironi ta anuloni</li><li>Klikoni "Anulo" dhe konfirmoni</li></ol><p>Mund të anuloni rezervimet deri 24 orë para termesit.</p>',
                'sr' => '<p>Da biste otkazali rezervaciju:</p><ol><li>Prijavite se na nalog</li><li>Idite na "Rezervacije"</li><li>Pronađite rezervaciju koju želite da otkažete</li><li>Kliknite "Otkaži" i potvrdite</li></ol><p>Možete otkazati rezervacije do 24 sata pre termina.</p>',
                'en' => '<p>To cancel a reservation:</p><ol><li>Log in to your account</li><li>Go to "Reservations"</li><li>Find the reservation you want to cancel</li><li>Click "Cancel" and confirm</li></ol><p>You can cancel reservations up to 24 hours before the appointment.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Cila është tarifa për shërbimet e notarit?',
                'sr' => 'Kolika je naknada za notarske usluge?',
                'en' => 'What is the fee for notary services?',
            ],
            'a' => [
                'sq' => '<p>Tarifat vendosen nga zyrat noteriale sipas ligjit të Kosovës. Në platformën e-Noteria:</p><ul><li>Regjistrimi si përdorues → <strong>FALAS</strong></li><li>Rezervimi i termesit → <strong>FALAS</strong></li><li>Verifikimi i dokumenteve → <strong>FALAS</strong></li><li>Shërbimet e notarit → Sipas tarifës të zyrës</li></ul><p>Çdo zyrë noteriale publikon tarifat e saj në profil para rezervimit.</p>',
                'sr' => '<p>Naknade određuju notarske kancelarije prema zakonu Kosova. Na platformi e-Noteria:</p><ul><li>Registracija korisnika → <strong>BESPLATNO</strong></li><li>Zakazivanje termina → <strong>BESPLATNO</strong></li><li>Verifikacija dokumenata → <strong>BESPLATNO</strong></li><li>Notarske usluge → Prema tarifi kancelarije</li></ul><p>Svaka notarska kancelarija objavljuje svoje tarife pre rezervacije.</p>',
                'en' => '<p>Fees are set by notary offices according to Kosovo law. On the e-Noteria platform:</p><ul><li>User registration → <strong>FREE</strong></li><li>Appointment booking → <strong>FREE</strong></li><li>Document verification → <strong>FREE</strong></li><li>Notary services → According to the office tariff</li></ul><p>Each notary office publishes its fees in their profile before booking.</p>',
            ],
        ],
        [
            'q' => [
                'sq' => 'Si mund të kontaktoj zyrën noteriale direkt?',
                'sr' => 'Kako da direktno kontaktiram notarsku kancelariju?',
                'en' => 'How can I contact the notary office directly?',
            ],
            'a' => [
                'sq' => '<p>Për kontaktin e drejtpërdrejt me zyrën noteriale:</p><ol><li>Shkoni në faqen "Shërbimet"</li><li>Zgjedhni zyrën noteriale</li><li>Shihni numrin e telefonit dhe adresën email</li><li>Kontaktojini direkt përmes telefonit ose email</li></ol>',
                'sr' => '<p>Za direktan kontakt sa notarskom kancelariom:</p><ol><li>Idite na stranicu "Usluge"</li><li>Izaberite notarsku kancelariju</li><li>Pogledajte broj telefona i email adresu</li><li>Kontaktirajte ih direktno telefonom ili emailom</li></ol>',
                'en' => '<p>To contact the notary office directly:</p><ol><li>Go to the "Services" page</li><li>Select the notary office</li><li>View their phone number and email address</li><li>Contact them directly by phone or email</li></ol>',
            ],
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $text['title'][$lang]; ?></title>
    <link rel="stylesheet" href="/assets/bootstrap/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/all.min.css">
    <style>
        :root {
            --rks-blue: #003366;
            --rks-blue-light: #1a5276;
            --rks-blue-dark: #001f40;
            --rks-gold: #cfa856;
            --bg-gray: #f4f6f9;
            --shadow-md: 0 4px 20px rgba(0,51,102,0.10);
            --shadow-lg: 0 10px 40px rgba(0,51,102,0.15);
            --transition: all 0.3s ease;
        }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg-gray); }

        /* Top bar */
        .gov-bar {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-bottom: 2px solid var(--rks-gold);
            padding: 8px 0;
            font-size: 0.85rem;
        }

        /* Nav */
        .main-nav {
            background: rgba(255,255,255,0.95);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,51,102,0.08);
            position: sticky;
            top: 0;
            z-index: 1000;
            backdrop-filter: blur(10px);
            transition: var(--transition);
        }
        .main-nav.scrolled { padding: 12px 0; box-shadow: 0 4px 30px rgba(0,51,102,0.12); }
        .brand { 
            font-weight: 800; 
            font-size: 26px; 
            color: var(--rks-blue); 
            text-decoration: none; 
            display: flex; 
            align-items: center; 
            gap: 10px;
        }
        .brand .logo-img {
            height: 45px;
            width: 45px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
            transition: all 0.3s ease;
        }
        .brand:hover .logo-img {
            filter: drop-shadow(0 4px 8px rgba(207, 168, 86, 0.4));
            transform: scale(1.05);
        }
        .brand .text-primary { color: var(--rks-gold) !important; }

        /* Hero */
        .help-hero {
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 50%, var(--rks-blue-dark) 100%);
            color: white;
            padding: 100px 0 80px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .help-hero::before {
            content: '';
            position: absolute; top: -50%; right: -10%;
            width: 600px; height: 600px;
            background: radial-gradient(circle, rgba(207,168,86,0.15) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }
        .help-hero::after {
            content: '';
            position: absolute; bottom: -30%; left: -5%;
            width: 400px; height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
            animation: float 8s ease-in-out infinite reverse;
        }
        @keyframes float {
            0%, 100% { transform: translateY(0px) translateX(0px); }
            50%       { transform: translateY(-20px) translateX(10px); }
        }

        /* FAQ */
        .faq-section { padding: 80px 0; background: linear-gradient(135deg, var(--bg-gray) 0%, #ffffff 100%); }
        .faq-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            border: 2px solid rgba(0,51,102,0.05);
            margin-bottom: 20px;
            overflow: hidden;
            transition: var(--transition);
        }
        .faq-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .faq-question {
            padding: 24px 32px;
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
            color: white;
            margin: 0;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-weight: 600;
            font-size: 1.05rem;
            transition: var(--transition);
        }
        .faq-question:hover { background: linear-gradient(135deg, var(--rks-blue-light) 0%, var(--rks-blue) 100%); }
        .faq-question i { transition: var(--transition); flex-shrink: 0; margin-left: 16px; }
        .faq-card.active .faq-question i { transform: rotate(180deg); }
        .faq-answer { max-height: 0; overflow: hidden; transition: max-height 0.4s ease; }
        .faq-answer .content { padding: 24px 32px; color: #555; line-height: 1.7; }

        /* Contact */
        .contact-section { padding: 80px 0; background: linear-gradient(135deg, #ffffff 0%, var(--bg-gray) 100%); }
        .contact-card {
            background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            border: 2px solid rgba(0,51,102,0.05);
            text-align: center;
            transition: var(--transition);
            height: 100%;
        }
        .contact-card:hover { transform: translateY(-8px); box-shadow: 0 20px 60px rgba(0,51,102,0.2); }
        .contact-icon {
            width: 80px; height: 80px;
            background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 24px;
            box-shadow: 0 8px 24px rgba(0,51,102,0.25);
            color: white;
            font-size: 1.5rem;
        }

        /* Quick links */
        .quick-links { padding: 60px 0; background: linear-gradient(135deg, var(--rks-blue) 0%, var(--rks-blue-light) 100%); color: white; }
        .link-card {
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
            transition: var(--transition);
            text-decoration: none;
            color: white;
            display: block;
        }
        .link-card:hover { transform: translateY(-4px); background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); color: white; }
        .link-card i { font-size: 2rem; margin-bottom: 12px; display: block; }

        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            color: #8b949e;
            padding: 60px 0 30px;
            position: relative;
        }
        footer::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px;
            background: linear-gradient(90deg, transparent, var(--rks-gold), transparent);
        }
        footer a { color: #8b949e; text-decoration: none; display: block; margin-bottom: 12px; transition: var(--transition); padding-left: 0; }
        footer a:hover { color: white; padding-left: 15px; }
        .footer-logo {
            font-weight: 800; color: white; font-size: 1.5rem; margin-bottom: 20px; display: block;
            background: linear-gradient(135deg, #ffffff, var(--rks-gold));
            -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .help-hero { padding: 80px 0 60px; }
            .help-hero h1 { font-size: 2.2rem; }
            .faq-question { padding: 18px 20px; font-size: 0.95rem; }
            .faq-answer .content { padding: 20px; }
            .contact-card { padding: 30px 20px; }
        }
    </style>
</head>
<body>

<!-- Top Strip -->
<div class="gov-bar">
    <div class="container d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center">
            <svg width="24" height="24" viewBox="0 0 100 100" class="me-2">
                <path d="M50 0 L90 20 L90 50 Q90 80 50 100 Q10 80 10 50 L10 20 Z" fill="#244d96"/>
                <path d="M30 40 L50 60 L70 40" stroke="#cfa856" stroke-width="5" fill="none"/>
            </svg>
            <span>e-Noteria</span>
        </div>
        <div>
            <a href="?lang=sq" class="text-decoration-none <?php echo $lang==='sq' ? 'fw-bold text-primary' : 'text-muted'; ?> me-3">Shqip</a>
            <a href="?lang=sr" class="text-decoration-none <?php echo $lang==='sr' ? 'fw-bold text-primary' : 'text-muted'; ?> me-3">Srpski</a>
            <a href="?lang=en" class="text-decoration-none <?php echo $lang==='en' ? 'fw-bold text-primary' : 'text-muted'; ?>">English</a>
        </div>
    </div>
</div>

<!-- Main Nav -->
<nav class="main-nav">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="index.php" class="brand">
            <img src="images/pngwing.com (1).png" alt="e-Noteria Logo" class="logo-img">
            e<span class="text-primary">-Noteria</span>
        </a>
        <div class="d-flex align-items-center gap-4 d-none d-md-flex">
            <a href="services.php" class="text-dark text-decoration-none fw-medium"><?php echo $text['nav']['services'][$lang]; ?></a>
            <a href="#" class="text-dark text-decoration-none fw-medium"><?php echo $text['nav']['news'][$lang]; ?></a>
            <a href="ndihma.php" class="text-dark text-decoration-none fw-medium"><?php echo $text['nav']['help'][$lang]; ?></a>
            <?php if ($is_logged_in): ?>
                <a href="dashboard.php" class="btn btn-primary px-4 rounded-pill fw-bold"><?php echo $text['nav']['dashboard'][$lang]; ?></a>
            <?php else: ?>
                <a href="login.php" class="btn btn-outline-primary px-4 rounded-pill fw-bold"><?php echo $text['nav']['login'][$lang]; ?></a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<!-- Hero -->
<div class="help-hero">
    <div class="container position-relative" style="z-index:1;">
        <h1 class="display-4 fw-bold mb-4"><?php echo htmlspecialchars($text['hero']['title'][$lang]); ?></h1>
        <p class="lead mb-0"><?php echo htmlspecialchars($text['hero']['desc'][$lang]); ?></p>
    </div>
</div>

<!-- FAQ Section - rendered from PHP arrays, NO hard-coded duplicates -->
<div class="faq-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <h2 class="text-center mb-5 fw-bold" style="color: var(--rks-blue);">
                    <?php echo htmlspecialchars($text['faq_heading'][$lang]); ?>
                </h2>

                <?php foreach ($text['faq'] as $item): ?>
                <div class="faq-card">
                    <h5 class="faq-question">
                        <?php echo htmlspecialchars($item['q'][$lang]); ?>
                        <i class="fas fa-chevron-down"></i>
                    </h5>
                    <div class="faq-answer">
                        <div class="content">
                            <?php echo $item['a'][$lang]; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

                <h3 class="text-center mt-5 mb-4 fw-bold" style="color: var(--rks-blue);">
                    <?php echo htmlspecialchars($text['faq_users_heading'][$lang]); ?>
                </h3>

                <?php foreach ($text['faq_users'] as $item): ?>
                <div class="faq-card">
                    <h5 class="faq-question">
                        <?php echo htmlspecialchars($item['q'][$lang]); ?>
                        <i class="fas fa-chevron-down"></i>
                    </h5>
                    <div class="faq-answer">
                        <div class="content">
                            <?php echo $item['a'][$lang]; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>
    </div>
</div>

<!-- Contact Section -->
<div class="contact-section">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <h2 class="text-center mb-5 fw-bold" style="color: var(--rks-blue);">
                    <?php echo htmlspecialchars($text['contact_heading'][$lang]); ?>
                </h2>
                <div class="row g-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="contact-card">
                            <div class="contact-icon"><i class="fas fa-phone"></i></div>
                            <h5 class="fw-bold mb-3">Telefon</h5>
                            <p class="text-muted mb-2">24/7</p>
                            <h6 class="fw-bold" style="color: var(--rks-blue);">038 200 100</h6>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="contact-card">
                            <div class="contact-icon"><i class="fas fa-envelope"></i></div>
                            <h5 class="fw-bold mb-3">Email</h5>
                            <p class="text-muted mb-2">Brenda 24 orëve</p>
                            <h6 class="fw-bold" style="color: var(--rks-blue); font-size: 0.8rem;">support@e-noteria.rks-gov.net</h6>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="contact-card">
                            <div class="contact-icon"><i class="fas fa-comments"></i></div>
                            <h5 class="fw-bold mb-3">Chat Live</h5>
                            <p class="text-muted mb-2">Përgjigje të menjëhershme</p>
                            <h6 class="fw-bold" style="color: var(--rks-blue);">Online 24/7</h6>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="contact-card">
                            <div class="contact-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <h5 class="fw-bold mb-3">Adresa</h5>
                            <p class="text-muted mb-2">Zyra qendrore</p>
                            <h6 class="fw-bold" style="color: var(--rks-blue);">Rr. "Agim Ramadani" nr. 1<br>10000 Prishtinë</h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Links -->
<div class="quick-links">
    <div class="container">
        <h2 class="text-center mb-5 fw-bold">
            <?php echo htmlspecialchars($text['quicklinks_heading'][$lang]); ?>
        </h2>
        <div class="row g-4 justify-content-center">
            <div class="col-md-3 col-6">
                <a href="services.php" class="link-card">
                    <i class="fas fa-list"></i>
                    <h6 class="mb-0"><?php echo $text['nav']['services'][$lang]; ?></h6>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="verifikimi.php" class="link-card">
                    <i class="fas fa-check-circle"></i>
                    <h6 class="mb-0">Verifikimi</h6>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="status.php" class="link-card">
                    <i class="fas fa-clock"></i>
                    <h6 class="mb-0">Statusi</h6>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="rrethnesh.php" class="link-card">
                    <i class="fas fa-info-circle"></i>
                    <h6 class="mb-0">Rreth Nesh</h6>
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row">
            <div class="col-md-4 mb-4">
                <span class="footer-logo">e-Noteria</span>
                <p>Platforma SaaS për zyrat noteriale në Kosovë. Abonim mujor 150€ për akses të plotë.</p>
            </div>
            <div class="col-md-2 col-6 mb-4">
                <h6 class="text-white mb-3">Linqe</h6>
                <a href="rrethnesh.php">Rreth Nesh</a>
                <a href="Privatesia.php">Privatësia</a>
                <a href="terms.php">Kushtet</a>
            </div>
            <div class="col-md-2 col-6 mb-4">
                <h6 class="text-white mb-3">Shërbime</h6>
                <a href="services.php">Shërbimet</a>
                <a href="verifikimi.php">Verifikimi</a>
                <a href="status.php">Statusi</a>
            </div>
            <div class="col-md-2 col-6 mb-4">
                <h6 class="text-white mb-3">Ndihma</h6>
                <a href="ndihma.php">FAQ</a>
                <a href="mailto:support@e-noteria.rks-gov.net">Kontakti</a>
                <a href="#">Dokumentacioni</a>
            </div>
            <div class="col-md-2 col-6 mb-4">
                <h6 class="text-white mb-3">Platforma</h6>
                <a href="#">API</a>
                <a href="statusi_sistemit.php">Statusi i Sistemit</a>
                <a href="#">Versioni</a>
            </div>
        </div>
        <div class="border-top border-secondary pt-3 text-center mt-3">
            <small>&copy; <?php echo date('Y'); ?> Republika e Kosovës - Ministria e Drejtësisë</small>
        </div>
    </div>
</footer>

<script src="/assets/bootstrap/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // FAQ Accordion
    const faqCards = document.querySelectorAll('.faq-card');
    faqCards.forEach(function (card) {
        card.querySelector('.faq-question').addEventListener('click', function () {
            const isActive = card.classList.contains('active');
            // Close all
            faqCards.forEach(function (c) {
                c.classList.remove('active');
                c.querySelector('.faq-answer').style.maxHeight = '0';
            });
            // Open clicked if it was closed
            if (!isActive) {
                card.classList.add('active');
                const answer = card.querySelector('.faq-answer');
                answer.style.maxHeight = answer.scrollHeight + 'px';
            }
        });
    });

    // Navbar scroll effect
    const navbar = document.querySelector('.main-nav');
    window.addEventListener('scroll', function () {
        navbar.classList.toggle('scrolled', window.scrollY > 50);
    });
});
</script>

<!-- Chat widget commented out due to undefined reference -->
<!-- <?php include 'chat-widget.php'; ?> -->

<script>
    // Guard against undefined chatWidget reference and its methods
    if (typeof chatWidget === 'undefined') {
        window.chatWidget = {
            contains: function(element) { return false; }
        };
    }
</script>
</body>
</html>

