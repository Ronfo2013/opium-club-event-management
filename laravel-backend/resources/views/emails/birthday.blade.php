<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buon Compleanno {{ $user->first_name }}!</title>
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
            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
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
        .birthday-message {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid #ff6b6b;
            text-align: center;
        }
        .special-offer {
            background: #fff3cd;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 2px solid #ffeaa7;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #666;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            background: #ff6b6b;
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
        <h1>🎂 Buon Compleanno {{ $user->first_name }}! 🎉</h1>
        <p>Opium Club Pordenone</p>
    </div>

    <div class="content">
        <div class="birthday-message">
            <h2>🎈 Tanti Auguri! 🎈</h2>
            <p>Il team di Opium Club Pordenone ti augura un fantastico compleanno!</p>
            <p>Speriamo che questa giornata speciale sia piena di gioia, sorrisi e momenti indimenticabili.</p>
        </div>

        <h3>🎁 Regalo Speciale per Te!</h3>
        <p>Per celebrare il tuo compleanno, abbiamo preparato qualcosa di speciale per te!</p>

        <div class="special-offer">
            <h3>🍾 Offerta Compleanno Esclusiva</h3>
            <p><strong>Ingresso omaggio per te e un amico/a!</strong></p>
            <p>Presenta questo biglietto al nostro prossimo evento e porta con te un amico/a senza costi aggiuntivi.</p>
            <p>L'offerta è valida per 30 giorni dalla data del tuo compleanno.</p>
        </div>

        <h3>🎊 Vieni a Festeggiare con Noi!</h3>
        <p>Non c'è modo migliore di celebrare il tuo compleanno che con la famiglia Opium Club!</p>
        <p>Iscriviti al nostro prossimo evento e festeggiamo insieme questa giornata speciale.</p>

        <h3>📱 Come Utilizzare l'Offerta</h3>
        <ul>
            <li>Iscriviti al prossimo evento tramite il nostro sito</li>
            <li>Presenta il biglietto allegato all'ingresso</li>
            <li>Porta con te un amico/a per l'ingresso omaggio</li>
            <li>Divertiti e festeggia con noi! 🎉</li>
        </ul>

        <p>Grazie per essere parte della nostra famiglia e per aver scelto Opium Club per i tuoi momenti di divertimento.</p>
        
        <p>Tanti auguri ancora!<br>
        Il team di Opium Club Pordenone 🎂</p>
    </div>

    <div class="footer">
        <p>Opium Club Pordenone - Auguri di Compleanno</p>
        <p>Questa email è stata inviata automaticamente. Non rispondere a questo messaggio.</p>
    </div>
</body>
</html>





