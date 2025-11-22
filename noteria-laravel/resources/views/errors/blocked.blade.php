<!DOCTYPE html>
<html lang="sq">
<head>
    <meta charset="UTF-8">
    <title>Qasja u bllokua</title>
    <style>
        body { font-family: Arial, sans-serif; background: #fff3f3; color: #222; padding: 40px; }
        .container { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px #eee; padding: 32px; }
        h2 { color: #c00; }
        .ip { font-weight: bold; }
        .contact { margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h2>Qasja u bllokua</h2>
    @if($reason === 'vpn')
        <p>U detektua përdorimi i VPN nga një vend i bllokuar.</p>
    @elseif($reason === 'country')
        <p>Vendi juaj nuk lejohet në këtë platformë.</p>
    @else
        <p>Qasja juaj është bllokuar për arsye sigurie.</p>
    @endif
    <div class="contact">
        <p>Nëse mendoni se kjo është gabim, ju lutemi kontaktoni <a href="mailto:support@noteria.com">support@noteria.com</a> dhe dërgoni këtë IP: <span class="ip">{{ $ip }}</span>.</p>
    </div>
</div>
</body>
</html>
