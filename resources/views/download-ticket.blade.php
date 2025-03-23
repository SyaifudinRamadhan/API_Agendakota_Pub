<?php
use Carbon\Carbon;
setlocale(LC_ALL, 'id_ID');
Carbon::setLocale('id');
?>

<!doctype html>
{{-- <html class={{ session('show_data') ? '' : 'html-1' }} lang="en"> --}}
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Agendakota | Download Ticket</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="{{ url('stylesheets/download-ticket.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

{{-- <body class={{ session('show_data') ? '' : 'body-1' }}> --}}

<body>
    <nav class="navbar sticky-top bg-body-tertiary">
        <div class="container-fluid">
            <div>
                <img src="{{ url('/images/pdf/logo.png') }}" alt="Agendakota" width="43%350px" height="auto">
            </div>
        </div>
    </nav>
    {{-- =========================================================================================================== --}}

    {{--  
          1. This code bellow is used for validate the OTP code before accessing invitation ticket
          2. You can un-comment if you want activate / give OTP again before accessing invitation ticket 
    --}}


    {{-- @if (session('has_download'))
        <script>
            window.location.reload();
        </script>
    @endif --}}
    {{-- @if (session('show_data')) --}}

    {{-- =========================================================================================================== --}}
    {{-- PAGE FOR VIEW TICKET AFTER SUBMIT UNIQUE TICKET CODE --}}
    <div class="container">
        @if (session('error'))
            <div class="alert alert-danger mb-3 ms-auto me-auto" role="alert">
                {{ session('error') }}
            </div>
        @endif
        <div class="row p-4 out-box mt-4">
            <div class="col-12">
                <h3 class="text-center mb-4"><b>{{ $purchase->ticket->event->name }}</b></h3>
            </div>
            <div class="col-md-6 d-flex left-column flex-column p-4">
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
            <div class="col-md-6 right-column p-4 pe-0 d-flex">
                <img class="w-100 banner rounded-4 m-auto" src="{{ url($purchase->ticket->event->logo) }}"
                    alt="">
            </div>
        </div>
        <h5 class="mt-4 mb-4">
            <b>Detail Pemesanan <span class="text-secondary">| Order Detail</span></b>
        </h5>
        <div class="row row-gap mb-5">
            <div class="col-md col-md-6 border border-black rounded-3 p-3 d-flex flex-column">
                <h5 class="text-center mb-4 mt-auto fw-bold">Nama Tiket - Invitation</h5>
                <img style="aspect-ratio: 1/1; margin: auto; height: 128px"
                    src="data:image/png;base64,{{ BarCode2::getBarcodePNG($purchase->id . '*~^|-|^~*' . $purchase->user_id, 'QRCODE', 8, 8) }}"
                    class="ms-auto me-auto mb-3" alt="qrcode" />
                <p class="fw-bold text-center mb-auto">
                    {{ $purchase->id . '*~^|-|^~*' . $purchase->user_id }}
                </p>
                @if ($purchase->ticket->secretInfo)
                    <a href="{{ $purchase->ticket->secretInfo->meet_link }}" class="btn btn-primary w-100 m-auto">Join
                        Meet</a>
                @endif
            </div>

            <div class="col-md col-md-6 border border-black rounded-3 d-flex flex-column p-4">
                <button id="export-pdf" type="submit" class="btn btn-danger main-btn w-100 mt-auto mb-3 d-flex">
                    <div class="ms-auto me-auto">
                        Download Tiket
                    </div>
                </button>
                <div id="alert-download" class="alert alert-danger mb-3 ms-auto me-auto d-none" role="alert">
                    Mohon maaf. Terjadi masalah koneksi. Halaman akan dimuat ulang dalam 5 detik.
                </div>
                <p class="fw-bold text-secondary mb-2">
                    Nama Pemesan
                </p>
                <p class="fw-bold mb-4">
                    {{ $purchase->orgInv->name }}
                </p>
                {{-- <p class="fw-bold text-secondary mb-2">
                    Kode Akses
                </p>
                <p class="fw-bold mb-4">
                    {{ $decrypted->otpCode }}
                </p> --}}
                <p class="fw-bold text-secondary mb-2">
                    Nomor Telepon / WhatsApp
                </p>
                <p class="fw-bold mb-4">
                    {{ $purchase->orgInv->wa_num }}
                </p>
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
                    {{ Carbon::parse($purchase->payment->created_at)->isoFormat('D MMMM Y') }}
                </p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        const handleSuccess = (res) => {
            return {
                data: res.data,
                status: res.status,
            };
        };

        const handleError = (error) => {
            // console.log(error);
            if (error.response === undefined) {
                return {
                    data: {
                        data: [error.message]
                    },
                    status: 500,
                };
            } else {
                return {
                    data: error.response,
                    status: error.response.status,
                };
            }
        };

        const downloadTicket = async (url, otpCode) => {
            try {
                let res = await axios.post(
                    url, {
                        unique_ticket: otpCode
                    }, {
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}', // Laravel CSRF Token
                        },
                        responseType: "blob",
                    }
                );
                return handleSuccess(res);
            } catch (error) {
                return handleError(error);
            }
        };

        document.getElementById('export-pdf').addEventListener('click', function(el) {
            document.getElementById('export-pdf').innerHTML = `<div class=" ms-auto spinner-border" role="status">
                        </div>
                        <div class="mt-auto mb-auto ms-3 me-auto">
                            Loading ...
                        </div>`;
            document.getElementById('export-pdf').disabled = true;
            // Kirim request POST ke endpoint PDF
            downloadTicket("{{ route('download-ticket', $urlKey) }}", "-").then(res => {
                if (res.status === 200) {
                    let url = window.URL.createObjectURL(
                        new Blob([res.data], {
                            type: "application/pdf"
                        })
                    );
                    let link = document.createElement("a");
                    link.href = url;
                    link.setAttribute("download", "agendakota_ticket.pdf");
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.location.reload();
                } else {
                    document.getElementById('alert-download').classList.remove('d-none');
                    setTimeout(() => {
                        window.location.reload();
                    }, 5000);
                }
            });
        });
    </script>
    {{-- =========================================================================================================== --}}

    {{--  
          1. This code bellow is used for validate the OTP code before accessing invitation ticket
          2. You can un-comment if you want activate / give OTP again before accessing invitation ticket 
    --}}

    {{-- @else --}}
    {{-- FOR BASIC PAGE / FORM TO PREVIEW TICKET --}}
    {{-- <div class="container">
            <form method="POST" action="{{ route('preview-ticket', $urlKey) }}"
                class="mt-3 ms-auto mt-5 me-auto content-box form-box">
                <h5 class="ms-auto me-auto mb-4 mt-3 fw-bold text-center">
                    Buka Tiket Undangan
                </h5>
                <div class="alert alert-info mb-3 ms-auto me-auto" role="alert">
                    A simple info alert—check it out!
                </div>
                @if (session('error'))
                    <div class="alert alert-danger mb-3 ms-auto me-auto" role="alert">
                        {{ session('error') }}
                    </div>
                @endif
                @csrf
                <div class="mb-3">
                    <label for="exampleInputEmail1" class="form-label">Kode Tiket</label>
                    <input type="text" name="unique_ticket" class="form-control" id="exampleInputEmail1"
                        aria-describedby="emailHelp" placeholder="Tuliskan kode tiket / otp anda disini !!!">
                </div>
                <div class="form-group ms-auto me-auto mb-3 d-flex">
                    <!-- Google Recaptcha Widget-->
                    <div class="g-recaptcha mt-4 ms-auto me-auto" data-sitekey={{ config('services.recaptcha.key') }}>
                    </div>

                </div>
                <button type="submit" class="btn w-100 mt-3 btn-danger main-btn">Buka Tiket</button>
            </form>
        </div> --}}
    {{-- @endif --}}

    {{-- =========================================================================================================== --}}

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <script async src="https://www.google.com/recaptcha/api.js"></script>
</body>

</html>
