<html>
    <body style="">
        <img src="{{ asset('img/qrcodes/qrcode_' . $qrcode->id . '.svg') }}" alt="">
        
        <p style="text-align:center; width: 512px; font-size: 28px; margin: 10px 0">
            <strong>Метка №{{ $qrcode->point->num }}</strong>
            <br>
            (Поле №{{ $qrcode->point->polygon->field->num }}, {{ $qrcode->point->polygon->field->client->khname }})
            <br><br>
            Дата отбора: ____________________________
        </p>
    </body>
</html>