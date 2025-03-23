<?php
    use Carbon\Carbon;
    setlocale(LC_ALL, 'id_ID');
    Carbon::setLocale('id');
?>

<!doctype html>
<html class="" lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agendakota | Download Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="file://{{ public_path('stylesheets/download-ticket.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body class="">
    <nav class="navbar sticky-top bg-body-tertiary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="file://{{ public_path('/images/pdf/logo.png') }}" alt="Agendakota" width="43%350px"
                    height="auto">
            </a>
        </div>
    </nav>
    {{-- PAGE FOR VIEW TICKET AFTER SUBMIT UNIQUE TICKET CODE --}}
    {{-- @dump($purchase) --}}
    <div class="container">

        <div class="row p-4 out-box mt-4">
            <div class="col-12">
                <h3 class="text-center mb-4"><b>{{ $purchase->ticket->event->name }}</b></h3>
            </div>
            <div class="col-6 d-flex left-column-fixed flex-column p-4">
                <div class="row">
                    <div class="col-1 icon-col">
                        <i class="bi bi-calendar-check-fill"></i>
                    </div>
                    @php
                        $startEvent = new DateTime(
                            $purchase->ticket->event->start_date . ' ' . $purchase->ticket->event->start_time,
                            new DateTimeZone('Asia/Jakarta'),
                        );
                        $endEvent = new DateTime(
                            $purchase->ticket->event->end_date . ' ' . $purchase->ticket->event->end_time,
                            new DateTimeZone('Asia/Jakarta'),
                        );
                    @endphp
                    <div class="col-11 text-col">
                        @if ($purchase->visitDate)
                            {{ Carbon::parse($purchase->visitDate->visit_date)->isoFormat('dddd, D MMMM Y') }}
                            {{ $startEvent->format('H:i') }} WIB <br>
                            s/d <br>
                            {{ Carbon::parse($purchase->visitDate->visit_date)->isoFormat('dddd, D MMMM Y') }}
                            {{ $endEvent->format('H:i') }} WIB
                        @else
                            {{ Carbon::parse($startEvent->format('d-m-Y'))->isoFormat('dddd, D MMMM Y') }}
                            {{ $startEvent->format('H:i') }} WIB <br>
                            s/d <br>
                            {{ Carbon::parse($endEvent->format('d-m-Y'))->isoFormat('dddd, D MMMM Y') }}
                            {{ $endEvent->format('H:i') }} WIB
                        @endif
                    </div>
                </div>
                <div class="row">
                    <div class="col-1 icon-col">
                        <i class="bi bi-pin-map"></i>
                    </div>
                    <div class="col-11 text-col">
                        {!! $purchase->ticket->event->location !!}
                    </div>
                </div>
                @if ($purchase->seatNumber)
                    <div class="row">
                        <div class="col-1 icon-col">
                            <i class="bi bi-door-open"></i>
                        </div>
                        <div class="col-11 text-col">
                            Tempat duduk nomor {{ $purchase->seatNumber->seat_number }}
                        </div>
                    </div>
                @endif
            </div>
            <div class="col-6 right-column-fixed p-4 pe-0 d-flex">
                <img class="w-100 banner rounded-4 m-auto"
                    src="file://{{ public_path($purchase->ticket->event->logo) }}" alt="">
            </div>
        </div>
        <h5 class="mt-4 mb-4">
            <b>Detail Pemesanan <span class="text-secondary">| Order Detail</span></b>
        </h5>
        <div class="row row-gap mb-5">
            <div class="col-6 border border-black rounded-3 p-3 d-flex flex-column" style="margin-right: 15px;">
                <h5 class="text-center mb-4 mt-auto fw-bold">{{ $purchase->orgInv ? 'Ticket - Invitation' : $purchase->ticket->name }}</h5>
                <img style="aspect-ratio: 1/1; margin: auto; height: 128px"
                    src="data:image/png;base64,{{ BarCode2::getBarcodePNG($purchase->id . '*~^|-|^~*' . $purchase->user_id, 'QRCODE', 8, 8) }}"
                    class="ms-auto me-auto mb-3" alt="qrcode" />
                <p class="fw-bold text-center mb-auto">
                    {{ $purchase->id . '*~^|-|^~*' . $purchase->user_id }}
                </p>
                @if ($purchase->ticket->secretInfo)
                    <p href="{{ $purchase->ticket->secretInfo->meet_link }}" class="w-100 text-center fw-bold text-primary m-auto">Link Meet : {{ $purchase->ticket->secretInfo->meet_link }}</p>
                @endif
            </div>

            <div class="col-6 border border-black rounded-3 d-flex flex-column p-4" style="margin-left: 15px;">

                <p class="fw-bold text-secondary mb-2">
                    Nama Pemesan
                </p>
                <p class="fw-bold mb-4">
                    {{ $purchase->orgInv ? $purchase->orgInv->name : $purchase->user->name }}
                </p>
                @if($purchase->orgInv)
                    {{--

                        @if (isset($decrypted))
                            <p class="fw-bold text-secondary mb-2">
                                Kode Akses
                            </p>
                            <p class="fw-bold mb-4">
                                {{ $decrypted->otpCode }}
                            </p>
                        @endif

                    --}}
                    <p class="fw-bold text-secondary mb-2">
                        Nomor Telepon / WhatsApp
                    </p>
                    <p class="fw-bold mb-4">
                        {{ $purchase->orgInv->wa_num }}
                    </p>
                @else
                    <p class="fw-bold text-secondary mb-2">
                        Email Pembeli
                    </p>
                    <p class="fw-bold mb-4">
                        {{ $purchase->user->email }}
                    </p>
                @endif
                    <p class="fw-bold text-secondary mb-2">
                        Order ID
                    </p>
                    <p class="fw-bold mb-4">
                        {{ $purchase->payment->order_id }}
                    </p>
                    <p class="fw-bold text-secondary mb-2">
                        Tanggal Pembelian
                    </p>
                    <p class="fw-bold mb-auto">
                        {{Carbon::parse($purchase->payment->created_at)->isoFormat('D MMMM Y')}}
                    </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
</body>

</html>
