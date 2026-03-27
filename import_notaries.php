<?php
/**
 * Import Notaries Data
 * Importon të dhënat e noterëve në bazën e të dhënave
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'confidb.php';

// Krijo tabelën notaret nëse nuk ekziston
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS notaret (
            id INT AUTO_INCREMENT PRIMARY KEY,
            emri_mbiemri VARCHAR(255) NOT NULL,
            kontakti VARCHAR(255),
            email VARCHAR(255),
            adresa TEXT,
            qyteti VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_qyteti (qyteti),
            INDEX idx_emri (emri_mbiemri)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "<p style='color:green;'>✓ Tabela 'notaret' u krijua ose ekziston tashmë.</p>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>✗ Gabim në krijimin e tabelës: " . $e->getMessage() . "</p>";
    exit;
}

// Të dhënat e noterëve
$notaries_data = [
    ['Adelina Ajeti-Qerimi', '045-489-444', 'notereadelinaajeti@hotmail.com', 'Rr. "Dëshmorët e Kombit", nr.65, Ferizaj', 'Ferizaj'],
    ['Adnan Imeri', '044 322-933', 'adnanimeri@live.com', 'Rr. "Kongresi i Manastirit" p.n. (Objekti i Postës), Viti', 'Viti'],
    ['Afrore Zatriqi', '049-324-329', 'afrore.ukaj.zatriqi@gmail.com', 'Rr. "Mbretëresha Teutë", p.n, Pejë.', 'Pejë'],
    ['Alban Janova', '044-444-221', 'albanjanova@gmail.com', 'Rr. "Fehmi dhe Xhevë Lladrovci" Nr. 15', 'Gllogoc'],
    ['Alban Musliu', '044-285-545', 'alban.musliu@gmail.com', 'Rr. "28 Nëntori", obj. Veranda F2, nr. 6, Skenderaj', 'Skënderaj'],
    ['Amir Rudaku', '049-203-024', 'noteramirrudaku@gmail.com', 'Rruga "Nuhi Gashi", Nr. 138', 'Podujevë'],
    ['Anduena Gashi-Shehu', '044-711-111', 'notere.anduenagashishehu@gmail.com', 'Rr."Jusuf Gërvalla", nr. 78, (afër Qendrës Tregtare Dardha)', 'Prishtinë'],
    ['Arben Mustafa', '044-133-300', 'noterarbenmustafa@gmail.com', 'Rr. "Mulla Idrizi", Nr.7', 'Gjilan'],
    ['Arbena Shehu', '044-115-644', 'arbena.shehu@gmail.com', 'Rr. "Garibaldi", kati I, banesa nr.5', 'Prishtinë'],
    ['Arbër Krasniqi', '045-828-828', 'noterarberkrasniqi@gmail.com', 'Rr. "Luan Haradinaj", nr.110', 'Prishtinë'],
    ['Arbër Sadiku', '044-122-699', 'arbersadiku.noter2012@gmail.com', 'Rr. "Nëna Terezë"', 'Gjakovë'],
    ['Ardi Behrami', '046-124-040', 'noter.ardibehrami@gmail.com', 'Rr. "Nënë Treza", Nr.223', 'Fushë Kosovë'],
    ['Ardian Sermaxhaj', '044-305-008', 'ardiani84@hotmail.com', 'Rr. "Brigada 161", Nr. 62, Ferizaj', 'Ferizaj'],
    ['Ardita Hoti Sertolli', '049-285-373', 'hotiardita03@gmail.com', 'Rr. "William Walker" p.nr, pranë Gjykates Themelore në Malisheve', 'Malishevë'],
    ['Ardonë Bahtiri', '044-425-308', 'ardone.bahtiri@gmail.com', 'Rr."Zahir Pajaziti", Obj. 2, Malësia Invest, Nr.15, Podujevë', 'Podujevë'],
    ['Arësim Avdijaj', '044-265-087', 'arsim.avdijaj@gmail.com', 'Rr. "Mbretëresha Teutë", Nr. 110', 'Pejë'],
    ['Ariana Demolli', '044-664-288', 'demolliariana@gmail.com', 'Rr."Shqipëria", p.n., Lipjan', 'Lipjan'],
    ['Arianë Gjoci', '048-123-232', 'arianegjoci@gmail.com', 'Rr."Xhelal Hajda-Toni", p.n., Rahovec', 'Rahovec'],
    ['Arianit Shala', '049-731-001', 'noterarianitshala@gmail.com', 'Në objektin e komunës, kati -2', 'Zubin Potok'],
    ['Arijan Rizvanolli', '045 559-362', 'ari_rizvanolli@yahoo.com', 'Rr."Luan Haradinaj",Nr.235/kati/I-rë Gjakovë', 'Gjakovë'],
    ['Arijeta Ukaj Dervishaj', '044-310-254', 'arijeta_ukaj@yahoo.com', 'Rr. "Nëna Terezë", lokali Nr.7', 'Fushë Kosovë'],
    ['Arlind Sharani', '044-514-540', 'arlindsharani@gmail.com', 'Rr. "Bajo Topulli" nr. 12, Prizren', 'Prizren'],
    ['Arlinda Mulaj-Tahiri', '049-710-177', 'arlindamulaj1@gmail.com', 'Rr. "Sadri Kelmendi", nr.26, Pejë.', 'Pejë'],
    ['Armend Baxhaku', '049-616-836', 'armendbaxhaku1@gmail.com', 'Rr. "Tahir Sinani" nr. 48', 'Prizren'],
    ['Arta Morina-Mustafa', '044-139-727', 'arta.mustafa@msn.com', 'Rr. "Remzi Ademaj" Nr. 113', 'Prizren'],
    ['Artan Bunjaku', '049-861-116', 'artan.bunjaku@noter-ks.com', 'Rr. "Agim Ramadani", Hy. 192, Kati 1, Zyra nr. 4', 'Prishtinë'],
    ['Artan Osmani', '049-589-742', 'artanosmani018@gmail.com', 'Lagjja e Boshnjakëve p.nr. (përballë Komunës)', 'Mitrovicë e Veriut'],
    ['Arteida Asllani', '044-154-700', 'arteida.asllani@gmail.com', 'Rr. "Luan Haradinaj", H.1, Nr. 9', 'Prishtinë'],
    ['Astrit Bibaj', '044-184-412', 'bibaj.astrit@gmail.com', 'Rr. "Garibaldi", Hyrja 15, Nr.5', 'Prishtinë'],
    ['Bardhyl Dinaj', '044-355-412', 'bardhyldinaj@hotmail.com', 'Sheshi: "Hasan Prishtina", Hyrja 2, kati përdhese', 'Vushtrri'],
    ['Behar Idrizi', '044-321-185', 'behar.noteri@gmail.com', 'Rr. "Xhemajl Mustafa", Nr.280', 'Gjilan'],
    ['Behar Mehani', '049-600-026', 'noter.beharmehani@gmail.com', 'Rr. "Ruzhdi Hyseni" nr. 11, Vushtrri', 'Vushtrri'],
    ['Besmir Juniku', '044-247-296', 'besmirjunikunoter@gmail.com', 'Rr. "Nëna Terezë" nr.298, kati I, Gjakovë', 'Gjakovë'],
    ['Betim Behluli', '049 390-590', 'betim_behluli@hotmail.com', 'Rr. "Sheh Mihedini", nr. 109, Rahovec', 'Rahovec'],
    ['Bilgaip Maznikar', '044-216-398', 'noter_bilgaip@hotmail.com', 'Rr. "Bujar Godemi", Nr.1', 'Prizren'],
    ['Blerta Dobra-Geci', '044 361-111', 'noterblertageci@gmail.com', 'Rr. "28 Nëntori ", p.n Skenderaj', 'Skenderaj'],
    ['Bujar Berdynaj', '044-265-093', 'bujar_b@hotmail.com', 'Rr. "Mbretëresha Teutë", nr. 144, Pejë', 'Pejë'],
    ['Burim Xhemajli', '045-737-475', 'burim.xhemajli@hotmail.com', 'Rr. "Rexhep Luci", nr.9/6, kati i I-rë', 'Prishtinë'],
    ['Dardan Kuçi', '049-862-024', 'dardankuci17@gmail.com', 'Rr. "Brigada 123", nr. 224, Suharekë', 'Suharekë'],
    ['Donika Rashica-Demaj', '045-438-905', 'donika.rashicaa@gmail.com', 'Rr. "Abdyl Krasniqi", nr. 56, Malishevë', 'Malishevë'],
    ['Dorentina Kelmendi', '049-677-870', 'dorentinabkelmendi@gmail.com', 'Rr. "Eliot Engel", Hyrja Nr.91/1, Kati 1, Nr.1, Pejë', 'Pejë'],
    ['Doruntina Berisha', '045-234-350', 'doruntinapodrimajberisha@gmail.com', 'Rr. "Bahri Fazliu", nr. 70', 'Podujevë'],
    ['Doruntina Peçani', '044-750-444', 'doruntina.p@notere-ks.com', 'Rr. "Sali Çeku", Euro Invest, Obj. 9, Hyrja C1, Kati 1, Nr.2, Ferizaj', 'Ferizaj'],
    ['Dukagjin Dinaj', '044-706-026', 'dinajdukagjin@gmail.com', 'Rr. "Mbretëresha Teutë", p.n.', 'Mitrovicë'],
    ['Elona Resyli', '048-471-444', 'elonaresylinoter@gmail.com', 'Rr."Dëshmoret e Pashtrikut", nr. 6, Rahovec', 'Rahovec'],
    ['Endrit Ajeti', '045 339 482', 'ajetiendrit@hotmail.com', 'Rr. "Bulevardi i Pavarësisë", nr.11', 'Gjilan'],
    ['Erblina Krasniqi Prishtina', '044-444-485', 'erblina.prishtina@hotmail.com', 'Rr. "Garibaldi", Hy. 3, Kati 1, Nr. 6, Prishtinë', 'Prishtinë'],
    ['Erdon Gjinolli', '045-299-989', 'erdongjinolli@yahoo.com', 'Rr. "Skenderbeu" nr. 144.', 'Kamenicë'],
    ['Ermira Uka - Gashi', '044-162-397', 'ermira_uka@hotmail.com', 'Rr. "Nëna Terezë", Nr.121', 'Fushë Kosovë'],
    ['Fadil Sinanaj', '044-625-674', 'fadilsinanaj3@gmail.com', 'Rr. "17 Shkurti", nr. 17, Gjilan.', 'Gjilan'],
    ['Faik Çollaku', '044-192-154', 'fqollaku@gmail.com', 'Rr. "Nëna Terezë", Nr.141/1', 'Fushë Kosovë'],
    ['Fatmir Halimi', '044-132-913', 'fatmirhalimi@hotmail.com', 'Rr. "Medlin Olbrajt", Nr.9', 'Gjilan'],
    ['Fatmir Tasholli', '044-259-108', 'noterfatmirtasholli@gmail.com', 'Rr. "Gjergj Fishta"', 'Lipjan'],
    ['Fatmire Dobruna', '045-724-688', 'noteredobruna@gmail.com', 'Rr. "Shaban Shala", nr. 1 P2', 'Gllogoc'],
    ['Fatmire Jerliu-Kuçi', '044-110-807', 'fatmire.j.kuci@gmail.com', 'Rr. "Fehmi Agani", nr.11, Lipjan', 'Lipjan'],
    ['Faton Muslija', '044-637-065', 'faton.muslija@gmail.com', 'Rr. "UÇK"-ës, Hyr. 54, kati I-rë, Nr.2 -Prishtinë', 'Prishtinë'],
    ['Faton Thaçi', '045-950-000', 'faton.thaqi@hotmail.com', 'Rr. "Kolashini", p.n. Mitrovicë Veriore', 'Mitrovicë e Veriut'],
    ['Fellanza Cacaj-Kerolli', '043-840-841', 'notere.fellanzakerolli@gmail.com', 'Sheshi "Adem Jashari" , nr. 11, Skenderaj', 'Skenderaj'],
    ['Ferat Krasniqi', '045-525-746', 'noterferatkrasniqi@gmail.com', 'Rr. "Tahir Sinani", p.n.', 'Shtime'],
    ['Ferdinand Radi', '049-500-181', 'ferdinandradi@radilaw.com', 'Rr. "Xhelal Hajda Toni", nr. 85, Rahovec', 'Rahovec'],
    ['Festim Zeqiri', '049 655-940', 'zeqirifestim1@gmail.com', 'Rr. "Brigada 123", nr.166', 'Suharekë'],
    ['Fisnik Shahini', '044-260-287', 'fisnikshahini@hotmail.com', 'Rr. "Brigada 161", nr. 56, Ferizaj', 'Ferizaj'],
    ['Floransa Sahiti', '048-745-545', 'noterefloransa.sahiti@gmail.com', 'Rr. "Dëshmorët e Kombit" nr. 2.', 'Hani i Elezit'],
    ['Florida Kostanica', '048-332-252', 'floridakostanica@gmail.com', 'Rr. "Hasan Prishtina", nr.185', 'Vushtrri'],
    ['Florim Salihu', '049-203-110', 'florim79salihu@gmail.com', 'Rr. "De Rada", p.n. Prizren', 'Prizren'],
    ['Florina Ramajli Aliu', '044-256-291', 'florinaramajli@gmail.com', 'Rr." Idriz Seferi " nr. 21, Kaçanik', 'Kaçanik'],
    ['Flutur Zajmi', '049-188-315', 'notere.zajmi@yahoo.com', 'Rr. "Nëna Terezë", Nr.414', 'Gjakovë'],
    ['Gazmend Heta', '044-243-453', 'gazmend-heta@hotmail.com', 'Rr. "Dëshmorët e Kombit", Nr.182', 'Ferizaj'],
    ['Gentiana Bajraktari Idrizi', '044-749-355', 'gentianabajraktariidrizi@outlook.com', 'Rr. "Sali Çeku",Objekti "Invest Brand", Blloku D, Lokali numër .11', 'Ferizaj'],
    ['Gentiana Morina', '049-866-043', 'gentianam24@gmail.com', 'Kamenicë, rr. Skenderbeu, nr.81.', 'Kamenicë'],
    ['Granit Gërvalla', '049-223-343', 'granitgervalla93@gmail.com', 'Rr. " Abedin Rexha " p.n', 'Klinë'],
    ['Hajrije Krasniqi', '044-387-794', 'noterehajrijekrasniqi@gmail.com', 'Rr. "Skenderbeu", nr.31', 'Malishevë'],
    ['Haki Haziri', '044-154-228', 'haziri.haki70@gmail.com', 'Rr. "Lufta e Gjilanit", nr.95, Gjilan.', 'Gjilan'],
    ['Halil Rrukiqi', '044-310-981', 'halilrrukiqi@yahoo.com', 'Rr. "Fehmi e Xhevë Lladrovci"', 'Gllogoc'],
    ['Haxhe Qorraj', '044-136-486', 'haxhqorraj@gmail.com', 'Rr. "Sali Çeku"', 'Deçan'],
    ['Hazir Ahmeti', '044-281-195', 'hazirahmeti@hotmail.com', 'Shtërrpcë', 'Shtërrpcë'],
    ['Hysni Pukaj', '044-895-111', 'hysni.pukaj@gmail.com', 'Rr. "Brigada 161", nr. 290, Ferizaj', 'Ferizaj'],
    ['Hysni Veseli', '044-158-576', 'hysniveseli2020@gmail.com', 'Rr "Driton Islami"-Hyrja A kati i Parë Nr. 4, Ferizaj', 'Ferizaj'],
    ['Islam Azemi', '044-194-596', 'iazemi@hotmail.com', 'Rr. "Xhemajl Mustafa", nr. 1', 'Gjilan'],
    ['Kabil Merovci', '044-431-685', 'merovci.24@gmail.com', 'Rr. \'\'Bedri Gjina\'\' nr. 5', 'Mitrovicë'],
    ['Kosovare Sopi', '045-399-993', 'kosovaresopi@gmail.com', 'Rr. "Nëna Terezë", Nr.72', 'Fushë Kosovë'],
    ['Kreshnik Këndusi', '044-207-249', 'noterkreshnikkendusi@gmail.com', 'Rr."Avdullah Babalija ", nr.23/1, (përballë Prokurorisë Themelore), Gjakovë', 'Gjakovë'],
    ['Leartë Cana', '044-430-657', 'learta.cana@gmail.com', 'Rr. "Luan Haradinaj", obj.136, Kati 1, nr. 3', 'Prishtinë'],
    ['Liridona Sadiku', '044-316-037', 'notereliridonasadiku@gmail.com', 'Rr. "Hasan Kabashi" (përballë Komunës së Re)', 'Viti'],
    ['Lirie Bunjaku-Zhitia', '045-636-565', 'notereliriebunjaku@gmail.com', 'Rr. Gjergj Kastrioti Skënderbeu, Kati I-rë,', 'Malishevë'],
    ['Ljiljana Kenic', '049-779-065', 'ljiljak46@gmail.com', 'Fshati Stanishor', 'Novo Bërdë'],
    ['Marigona Paloj Shabani', '045-275-286', 'noteremarigona@gmail.com', 'Rr. "Adem Jashari", nr 30/1, Obiliq', 'Obiliq'],
    ['Marigonë Shkoza', '049-109-104', 'marigona.deda@gmail.com', 'Rr. "Nënë Tereza" pa nr., (afër Kuvendit Komunal), kati I-rë, Gjakovë', 'Gjakovë'],
    ['Mehdi Kozhani', '044-393-745', 'mehdikozhani76@gmail.com', 'Rr. "Saat Kulesi", nr. 47, Mamushë', 'Mamushë'],
    ['Meral Tejeci Çela', '044-685-456', 'tejecimeral@gmail.com', 'Rr." Remzi Ademaj " nr.228, Prizren', 'Prizren'],
    ['Merita Bogaj', '045-448-214', 'notere.meritabogaj@gmail.com', 'Suharekë', 'Suharekë'],
    ['Merita Kostanica', '049-790-790', 'meritakostanica@gmail.com', 'Sheshi Zahir Pajaziti, Hyrja nr.20, Kati 1, nr.1', 'Prishtinë'],
    ['Merita Selimi', '043-799-582', 'notere.meritaselimi@gmail.com', 'Rr."Brigada 123", p.n', 'Suharekë'],
    ['Migjen Mekaj', '049-585-999', 'noter.migjenmekaj@gmail.com', 'Istog', 'Istog'],
    ['Milot Podvorica', '045-653-666', 'noter.milotpodvorica@gmail.com', 'Rr. "Garibaldi" Nr. 7/3, Kati i parë.', 'Prishtinë'],
    ['Mimoza Zabeli Sinani', '049-858-885', 'notere.mimoza@gmail.com', 'Rr. Bardhyl Çaushi, Objekti C, Lokali 34, (Rruga B)', 'Prishtinë'],
    ['Naser Pajaziti', '045-677-295', 'naser.pajaziti@gmail.com', 'Rr. "Gjemajl Ademi" nr. 54', 'Viti'],
    ['Orhan Gashi', '044-191-608', 'orhangashi.noter@gmail.com', 'Rr. "Remzi Ademaj", nr. 92.', 'Prizren'],
    ['Pajtim Kaçka', '049-665-517', 'pajtimkacka@hotmail.com', 'Rr. "William Walker", nr.6, tek Gjykata Themelore', 'Prizren'],
    ['Qefsere Sejdiu', '044-383-613', 'qefserehasanisejdiu@hotmail.com', 'Rr. "Afrim Zhitia", p.n.', 'Mitrovicë e Jugut'],
    ['Qendresa Muriqi - Mekaj', '049-246-332', 'qendresamekaj@gmail.com', 'Sheshi "Haxhi Zeka" rr. "Toni Bler", nr. 25, Pejë', 'Pejë'],
    ['Ridvan Kajtazi', '044-487-000', 'ridvan.kajtazi@gmail.com', 'Rr. "Krajl Milutina", Nr. 10, Graçanicë,', 'Graçanicë'],
    ['Sadeta Fazlji', '044-946-434', 'sadeta1.f@gmail.com', 'Rr. "Hafiz Ismail Haki", p.n', 'Prizren'],
    ['Sahara Duriqi Avdiu', '044-650-732', 'duriqisahara@gmail.com', 'Rr. "Skenderbeu" nr. 10, (afer Xhamisë)', 'Kaçanik'],
    ['Sahit Bajraktari', '049-649-094', 'notersbajraktari@gmail.com', 'Rr. "Ukshin Hoti", Nr. 45, Pejton, Prishtinë.', 'Prishtinë'],
    ['Saranda Bogaj Sheremeti', '048-825-222', 'noterebogajsheremeti@gmail.com', 'Rr. "UÇK" , Nr. 126 I 10000, Prishtinë,', 'Prishtinë'],
    ['Saranda Rexhepi', '049-720-600', 'sarandarexhep1@hotmail.com', 'Sheshi "Zahir Pajaziti", nr. 43', 'Podujevë'],
    ['Sefadin Blakaj', '044-988-944', 'sefadinblakaj@gmail.com', 'Rr. Fehmi Agani, 21/3-1.', 'Prishtinë'],
    ['Sefer Krasniqi', '044-206-924', 'noterseferkrasniqi@hotmail.com', 'Rr. " Shqipëria ", p.n.', 'Lipjan'],
    ['Selatin Shahini', '044-161-364', 'selatin_sh@hotmail.com', 'Rruga e Reçakut, objekti i ETC-së', 'Ferizaj'],
    ['Shemsije Istogu-Lladrovci', '044-739-794', 'sh_istogulladrovci@hotmail.com', 'Rr. "Sali Çekaj", Kompleksi "Gështenja", Kati i I-rë p.n. Deçan.', 'Deçan'],
    ['Sherife Seferi Tahiri', '044-375-538', 'sherife_seferi@hotmail.com', 'Rr. "Rruga e Tiranës", Shtime', 'Shtime'],
    ['Shkëlqesa Veseli', '049-353-385', 'shkelqesa.veseli@gmail.com', 'Rr. "Bedri Gjinaj", Hyrja 1, kati 1', 'Mitrovicë'],
    ['Shkelzen Ademi', '046-999-555', 'notershkelzenademi@gmail.com', 'Rr." UÇK", nr. 58/1,', 'Prishtinë'],
    ['Shpend Haskaj', '044-310-925', 'noter.haskaj@hotmail.com', 'Rr. "Mbretëresha Teutë" nr.122.', 'Pejë'],
    ['Shpresa Hasani', '045-587-282', 'notershpresahasani@gmail.com', 'Rr."Brigada 161", objekti i Arch Point, Kati P, Lokali B1', 'Ferizaj'],
    ['Shqipe Ahmeti', '049-606-015', 'notereshqipeahmeti@gmail.com', 'Bulevardi Isa Boletini Nr.9, Mitrovicë', 'Mitrovicë'],
    ['Shqipe Zogu', '049-414-136', 'shqipe-zogu@hotmail.com', 'Rr. "Fehmi dhe Xhevë Lladrovci", nr.13', 'Gllogoc'],
    ['Shqipo Sejdiu', '044-599-052', 'shqipo.sejdiu@gmail.com', 'Rr. "Skenderbeu", Objekti BeniDona Center, Kati I-rë, Nr. 3', 'Podujevë'],
    ['Simeana Beshi', '049-637-876', 'beshisimeana@gmail.com', 'Rr. "Fehmi Agani", nr. 68, Prizren', 'Prizren'],
    ['Sinan Visoka', '044-128-012', 's_visoka@hotmail.com', 'Rr. "Lordi Bajron".', 'Podujevë'],
    ['Suna Sallahu', '044-604-993', 'suna_salla@hotmail.com', 'Rr. William Walker nr.11', 'Prizren'],
    ['Suzana Kuçi Shala', '044-248-346', 'zana.kuci@gmail.com', 'Junik', 'Junik'],
    ['Urtina Preniqi', '045-402-162', 'urtinapreniqi@gmail.com', 'Sheshi Zahir Pajaziti, hyrja 2, kati II, numër 6, Prishtinë', 'Prishtinë'],
    ['Usref Maznikar', '045-303-172', 'usref_noter@hotmail.com', 'Rr. "Sheshi i Dëshmorëve".', 'Dragash'],
    ['Valbona Bytyqi', '049-168-007', 'noterevalbonabytyqi@hotmail.com', 'Rr. "Ukshin Hoti" nr. 120, Kati 1, C3/2a', 'Prishtinë'],
    ['Valdete Ademi', '044-879-200', 'valdeteademi@hotmail.com', 'Blv. Dëshmorët e Kombit, Hyrja 5, Nr. 4.', 'Prishtinë'],
    ['Valmire Muzaqi', '044-285-589', 'valmirepollomi33@gmail.com', 'Rr. "UÇK", Nr.1', 'Prishtinë'],
    ['Vanesa Ahmetaj', '043-834-133', 'vanesa.ahmetaj.1@gmail.com', 'Rr. "Bedri Pejani", objekti Përparimi Bresje, Kati I, Nr. 1/4', 'Fushë Kosovë'],
    ['Veton Dragidella', '049-999-733', 'vetondragidella@gmail.com', 'Rr."Abedin Rexha" p.n, Klinë', 'Klinë'],
    ['Veton Mustafa', '044-772-662', 'vetonmustafa@msn.com', 'Rr. "William Walker", nr. 7.', 'Prizren'],
    ['Vjosa Gashi-Desku', '044-346-582', 'notervjosadesku@gmail.com', 'Rr."Abedin Rexha", nr.13, Klinë', 'Klinë'],
    ['Vjosa Gradinaj Mexhuani', '046-100-589', 'noterevjosa@gmail.com', 'Rr."Reçakut" obj. Lumi Construction, Lokali nr.1, Ferizaj.', 'Ferizaj'],
    ['Vlora Haziri', '044-847-867', 'vloramhaziri.notere@gmail.com', 'Rr."UÇK", nr.145', 'Gjakovë'],
    ['Vlora Osmani', '049-909-674', 'notervloraosmani@gmail.com', 'Rr."Ukshin Hoti", Kompleksi "Prime Rezidence II", kati përdhesë, Lakrishtë', 'Prishtinë'],
    ['Xhyljetë Izmaku - Rama', '044-600-221', 'xhyljeteizmaku@gmail.com', 'Vushtrri', 'Vushtrri'],
    ['Ylli Ahmeti', '044-773-315', 'ylliahmeti0@gmail.com', 'Rr."Bedri Gjinaj", nr. 33, Mitrovicë', 'Mitrovicë'],
    ['Ylli Mekaj', '049-147-800', 'yllimekaj@hotmail.com', 'Rr. "Wesley Clark", nr.12.', 'Pejë'],
    ['Yllka Malaj Kallaba', '045-673-318', 'malajyllka@gmail.com', 'Rr. "Basri Canolli" Pn. Kamenicë.', 'Kamenicë'],
    ['Zekri Bytyçi', '049-921-316', 'zekri.bytyçi@live.com', 'Rr. "Idriz Seferi" nr. 19', 'Kaçanik'],
    ['Zymer Metaj', '044-507-265', 'noterzymermetaj@gmail.com', 'rr."Fadil Ferati", nr.63/1, Istog', 'Istog']
];

// Fshij të dhënat e vjetra nëse ekzistojnë
try {
    $pdo->exec("DELETE FROM notaret");
    echo "<p style='color:orange;'>⚠ Të dhënat e vjetra u fshirën.</p>";
} catch (PDOException $e) {
    error_log("Error deleting old data: " . $e->getMessage());
}

// Importo të dhënat e reja
$stmt = $pdo->prepare("
    INSERT INTO notaret (emri_mbiemri, kontakti, email, adresa, qyteti)
    VALUES (?, ?, ?, ?, ?)
");

$imported = 0;
$failed = 0;

foreach ($notaries_data as $notary) {
    try {
        $stmt->execute($notary);
        $imported++;
    } catch (PDOException $e) {
        error_log("Error inserting notary: " . json_encode($notary) . " - " . $e->getMessage());
        $failed++;
    }
}

echo "<p style='color:green;'>✓ Noterët u importuan me sukses!</p>";
echo "<p><strong>Importuar:</strong> $imported</p>";
echo "<p><strong>Dështuar:</strong> $failed</p>";
echo "<p><a href='notaries.php' style='color:blue;text-decoration:none;'>Shiko listën e noterëve →</a></p>";
?>
<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Importim i Noterëve</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        p {
            font-size: 1.1rem;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 Importim të Dhënash - Noterët</h1>
    </div>
</body>
</html>
