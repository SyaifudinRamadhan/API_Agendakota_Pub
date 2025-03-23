<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print QR Event</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css"
        integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <style>
        .event-name {
            text-align: center;
            font-weight: bold;
        }

        .icon-ak {
            width: 300px;
            text-align: center;
        }

        h4 {
            font-family: "Inter", sans-serif !important;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div style="z-index: 1;">
        <h2 class="event-name">QR Event {{ $event->name }}</h2>
        <div class="text-center">
            <p>by</p>
            <img class="icon-ak" src="{{ public_path('images/pdf/logo.png') }}" alt="">
        </div>
    </div>
    <img style=" z-index: -1;
    position: fixed;
    bottom: 0;
    width: 100%; height: 50%"
        src="{{ public_path('images/pdf/bg-print-bawah.png') }}" alt="">
    <div class="container" style="z-index: 2">
        <div class="mt-5 row">
            <div class="col-12">

            </div>
            <div class="col-12 text-center" style="margin-top: 10px;">
                <img width="45%" src="data:image/png;base64,{{ BarCode2::getBarcodePNG($urlEvent, 'QRCODE', 8, 8) }}"
                    class="img-qrcode" alt="barcode" />
            </div>
            <div class="col-12">
                <div
                    style="margin-top: 50px;
                background-color: #f9e7f1a1;
                border-radius: 15px;
                padding: 25px 45px;">
                    <h4>Petunjuk Checkin</h4>
                    <p>1. Buka halaman home user Agendakota</p>
                    <p>2. Klik Menu Option (Baris horizontal susun 3) di kanan atas</p>
                    <p>3. Pilih dan klik menu <i><b>QR Checkin</b></i></p>
                    <p>4. Pilih kamera untuk scanning jika diminta</p>
                    <p>5. Scan kode QR Event untuk Checkin</p>
                    <p>6. Tunjukkan hasil checkin kepada satpam / penjaga</p>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-A3rJD856KowSb7dwlZdYEkO39Gagi7vIsF0jrRAoQmDKKtQBHUuLZ9AsSv4jD4Xa" crossorigin="anonymous">
    </script>
    <script>
        // document.addEventListener('DOMContentLoaded', function () {
        //     window.print();
        // });
    </script>
</body>

</html>
