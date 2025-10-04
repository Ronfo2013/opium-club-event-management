<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Benvenuto a {{ $event->title }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            border-radius: 10px 10px 0 0;
        }
        .content {
            background: #f9f9f9;
            padding: 30px;
            border-radius: 0 0 10px 10px;
        }
        .event-info {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }
        .qr-info {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            text-align: center;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎉 Benvenuto a {{ $event->title }}!</h1>
        <p>Opium Club Pordenone</p>
    </div>

    <div class="content">
        <h2>Ciao {{ $user->first_name }}!</h2>
        
        <p>Grazie per esserti registrato al nostro evento. Siamo entusiasti di averti con noi!</p>

        <div class="event-info">
            <h3>📅 Dettagli Evento</h3>
            <p><strong>Evento:</strong> {{ $event->title }}</p>
            <p><strong>Data:</strong> {{ $event->event_date->format('d/m/Y') }}</p>
            <p><strong>Nome:</strong> {{ $user->first_name }} {{ $user->last_name }}</p>
            <p><strong>Email:</strong> {{ $user->email }}</p>
        </div>

        <div class="qr-info">
            <h3>📱 Il tuo QR Code</h3>
            <p>Il tuo biglietto digitale è allegato a questa email in formato PDF.</p>
            <p>Presenta il QR code all'ingresso per accedere all'evento.</p>
            <p><strong>Token QR:</strong> {{ $user->qr_token }}</p>
        </div>

        <h3>📍 Come arrivare</h3>
        <p>Opium Club Pordenone<br>
        Indirizzo: [Inserisci l'indirizzo]<br>
        Orario apertura: [Inserisci l'orario]</p>

        <h3>📋 Cosa portare</h3>
        <ul>
            <li>Un documento d'identità valido</li>
            <li>Il QR code (stampato o sul telefono)</li>
            <li>Buon umore e voglia di divertirsi! 🎉</li>
        </ul>

        <p>Se hai domande, non esitare a contattarci.</p>
        
        <p>A presto!<br>
        Il team di Opium Club Pordenone</p>
    </div>

    <div class="footer">
        <p>Opium Club Pordenone - Sistema di gestione eventi</p>
        <p>Questa email è stata inviata automaticamente. Non rispondere a questo messaggio.</p>
    </div>
</body>
</html>





