<?php
    // Footer i thjeshtë
    echo <<<HTML
<footer style="background:linear-gradient(90deg,#2b5876 0%,#4e4376 100%);color:#fff;padding:32px 0 18px 0;margin-top:40px;box-shadow:0 -2px 8px rgba(44,62,80,0.08);font-family:'Segoe UI',Arial,sans-serif;">
    <div style="max-width:1100px;margin:0 auto;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;padding:0 32px;">
        <div style="flex:1 1 220px;min-width:180px;">
            <div style="font-size:1.5rem;font-weight:bold;letter-spacing:1px;display:flex;align-items:center;">
                <img src="https://img.icons8.com/ios-filled/40/ffffff/notary.png" alt="Logo" style="width:32px;height:32px;margin-right:10px;">
                Noteria
            </div>
            <p style="margin:12px 0 0 0;font-size:1rem;opacity:.85;">
                Platforma digjitale për rezervime, dokumente dhe shërbime noteriale në Kosovë.
            </p>
        </div>
        <div style="flex:1 1 160px;min-width:140px;margin-top:18px;">
            <h4 style="margin-bottom:10px;font-size:1.08rem;color:#ffd700;">Linqe të shpejta</h4>
            <ul style="list-style:none;padding:0;margin:0;">
                <li><a href="dashboard.php" style="color:#fff;text-decoration:none;opacity:.92;">Ballina</a></li>
                <li><a href="reservation.php" style="color:#fff;text-decoration:none;opacity:.92;">Rezervimet</a></li>
                <li><a href="documents.php" style="color:#fff;text-decoration:none;opacity:.92;">Dokumentet</a></li>
                <li><a href="invoices.php" style="color:#fff;text-decoration:none;opacity:.92;">Faturat</a></li>
                <li><a href="profile.php" style="color:#fff;text-decoration:none;opacity:.92;">Profili</a></li>
            </ul>
        </div>
        <div style="flex:1 1 180px;min-width:150px;margin-top:18px;">
            <h4 style="margin-bottom:10px;font-size:1.08rem;color:#ffd700;">Kontakt</h4>
            <p style="margin:0;font-size:1rem;opacity:.85;">
                <span style="display:block;margin-bottom:4px;">Email: <a href="mailto:info@noteria.com" style="color:#ffd700;text-decoration:none;">info@noteria.com</a></span>
                <span style="display:block;">Tel: <a href="tel:+38344123456" style="color:#ffd700;text-decoration:none;">+383 44 123 456</a></span>
            </p>
            <div style="margin-top:10px;">
                <a href="https://facebook.com" target="_blank" style="margin-right:8px;"><img src="https://img.icons8.com/ios-filled/24/ffd700/facebook-new.png" alt="Facebook"></a>
                <a href="https://instagram.com" target="_blank"><img src="https://img.icons8.com/ios-filled/24/ffd700/instagram-new.png" alt="Instagram"></a>
            </div>
        </div>
    </div>
    <div style="border-top:1px solid rgba(255,255,255,0.2);padding:18px 0 0 0;margin-top:24px;text-align:center;font-size:0.9rem;opacity:.9;">
        <p style="margin:0;">&copy; " . date("Y") . " Noteria. Të gjitha të drejtat e rezervuara.</p>
    </div>
</footer>
HTML;