<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <style>
        body {
            margin: 0;
            font-family: "Inter", sans-serif;
            font-weight: normal;
            background-color: #fff;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        div {
            /* display: flex;
            flex-direction: column; */
            box-sizing: border-box;
        }

        table {
            border-spacing: 5mm 0mm;
            margin-left: -5mm;
            margin-right: -5mm;
            width: 186mm;
            /* height: calc(100% + 5mm); */
        }

        td {
            vertical-align: top;
        }

        .MainPaper {
            /* display: flex;
            flex-direction: column; */
            /* gap: 18.897637795px; */
            padding: 5mm;
            /* margin: 10mm; */
            width: 176mm;
            height: 263mm;
            background-color: #ddd;
        }

        .MainPaper .GroupInner {
            gap: 18.897637795px;
            /* display: flex; */

            /* flex-direction: column; */
        }

        .PaperSplit {
            /* display: flex; */

            /* flex-direction: row !important; */
        }

        .BoxInner {
            background-color: #fff;
            padding: 5mm;
            /* gap: 15px; */
            font-size: 12px;
            margin-bottom: 5mm
        }

        .BoxInner div {
            overflow: hidden;
            overflow-wrap: anywhere;
        }

        .LeftPanel {
            width: 60.22%;
        }

        .RightPanel {
            width: calc(39.78% - 5mm);
        }

        .ImgBanner {
            width: 100%;
        }

        .BoxInnerTitle {
            font-weight: bold;
            font-size: 16px;
        }

        .InfoGroup {
            gap: 10px;
            align-content: center;
            margin-top: auto;
            margin-bottom: auto;
        }

        .InfoGroup img {
            margin-top: auto;
            margin-bottom: auto;
            width: 23px;
            height: 23px;
        }

        .InfoGroup div {
            margin-top: auto;
            margin-bottom: auto;
            width: calc(100% - 40px);
        }

        .Desc {
            vertical-align: middle;
        }

        .Desc p {
            margin: 0;
        }

        .DescIcon {
            width: 23px;
            height: 23px;
            vertical-align: middle;
        }

        .DescIcon img {
            width: 23px;
            height: 23px;
        }

        .GroupSocmed img {
            width: 30px;
            height: 30px;
        }

        .TextPrimaryBasic {
            color: #ca0c64;
            font-weight: bold;
        }

        .Location p {
            margin: 0
        }
    </style>
</head>

<body>
    <?php
    use Carbon\Carbon;
    setlocale(LC_ALL, 'id_ID');
    Carbon::setLocale('id');
    ?>
    <div class="MainPaper">
        <div class="BoxInner">
            <span style="font-weight: normal; font-size: 14px">
                TICKET TYPE :&nbsp;
                <span class="TextPrimaryBasic">
                    {{ $ticket->name }}, {{ $event->city }}&nbsp;
                </span>&nbsp;
                @currencyEncode($purchase->amount)
            </span>
        </div>
        <table>
            <tr>
                {{-- LEFT PANEL --}}
                <td class="LeftPanel">
                    <div class="BoxInner">
                        <img src="{{ public_path($event->logo) }}" alt="" srcset="" class="ImgBanner" />
                        {{-- <img src="{{ $event->logo }}" alt="" srcset="" class="ImgBanner" /> --}}
                    </div>
                    <div class="BoxInner" style="height: 227px">
                        <div class="BoxInnerTitle">{{ $event->name }}</div>
                        <table style="border-spacing: 2mm 3mm; margin-left: -2mm; width: 100%;">
                            <tr>
                                <td class="DescIcon"><img src="{{ public_path('/images/pdf/calendar.png') }}"
                                        alt="" srcset=""></td>
                                <td class="Desc">
                                    <div>{{ Carbon::parse($startDate)->isoFormat('dddd, D MMMM Y') }} -
                                        {{ Carbon::parse($endDate)->isoFormat('dddd, D MMMM Y') }}
                                        {{ $time }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td class="DescIcon"><img src="{{ public_path('/images/pdf/filter.png') }}"
                                        alt="" srcset=""></td>
                                <td class="Desc">{{ $event->exe_type }} Event</td>
                            </tr>
                            <tr>
                                <td class="DescIcon"><img src="{{ public_path('/images/pdf/map.png') }}" alt=""
                                        srcset="">
                                </td>
                                <td class="Desc">
                                    {!! $event->location !!}
                                </td>
                            </tr>
                            @if ($seat_number !== null)
                                <tr>
                                    <td class="DescIcon">
                                        Nomor Kursi
                                    </td>
                                    <td class="Desc">
                                        {{ $seat_number }}
                                    </td>
                                </tr>
                            @endif
                        </table>
                    </div>
                </td>
                {{-- RIGHT PANEL --}}
                <td class="RightPanel">
                    <div class="BoxInner" style="text-align: center">
                        <img src="{{ public_path($org->photo) }}" alt="" srcset="" class="OrgAvatar"
                            style="aspect-ratio: 1/1; margin: auto; height: 100px" />
                        {{-- <img src="{{ $org->photo }}" alt="" srcset="" class="OrgAvatar"
                            style="aspect-ratio: 1/1; margin: auto; height: 100px" /> --}}
                    </div>
                    <div class="BoxInner" style="text-align: center">
                        {{-- <QRCode
                                    style="aspect-ratio: 1/1; margin: auto; height: 128px"
                                    value={pchData.qr_str}
                                /> --}}

                        @if ($type == 'barcode')
                            <img style="aspect-ratio: 1/1; margin: auto; height: 128px"
                                src="data:image/png;base64,{{ BarCode1::getBarcodePNG($qrStr, 'C39', 2, 100) }}"
                                class="img-barcode" alt="barcode" />
                        @else
                            <img style="aspect-ratio: 1/1; margin: auto; height: 128px"
                                src="data:image/png;base64,{{ BarCode2::getBarcodePNG($qrStr, 'QRCODE', 8, 8) }}"
                                class="img-code" alt="qrcode" />
                        @endif

                    </div>
                    <div class="BoxInner" style="gap: 7px; text-align: center; height:85px; ">
                        <div>{{ $payment->order_id }}</div>
                        <div>{{ $myData->name }}</div>
                        <div>
                            Dipesan pada&nbsp;
                            {{ Carbon::parse($payment->created_at)->isoFormat('D MMMM Y') }}
                        </div>
                        <div>Ref: Online</div>
                    </div>
                </td>
            </tr>
        </table>
        <table>
            <tr>
                <td style="background-color: #fff; width: 42mm; padding: 3mm; vertical-align: middle;">
                    <table style="border-spacing: 2mm 0mm; margin: 0;">
                        <tr>

                            <td class="DescIcon"> <img src="{{ public_path('/images/pdf/phone.png') }}" alt=""
                                    srcset=""></td>
                            <td class="Desc">{{ $org->phone }}</td>
                        </tr>
                    </table>
                </td>
                <td style="background-color: #fff; width: 42mm; padding: 3mm; vertical-align: middle;">
                    <table style="border-spacing: 2mm 0mm; margin: 0;">
                        <tr>
                            <td class="DescIcon"><img src="{{ public_path('/images/pdf/instagram.png') }}"
                                    alt="" srcset=""></td>
                            <td class="Desc">{{ $org->instagram }}</td>
                        </tr>
                    </table>
                </td>
                <td style="background-color: #fff; width: 42mm; padding: 3mm; vertical-align: middle;">
                    <table style="border-spacing: 2mm 0mm; margin: 0;">
                        <tr>
                            <td class="DescIcon"><img src="{{ public_path('/images/pdf/mail.png') }}" alt=""
                                    srcset="">
                            </td>
                            <td class="Desc">
                                <div style="width: 36mm; overflow: hidden; overflow-wrap: anywhere;">
                                    {{ $org->email }}</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <div style="margin-top: 5mm">
            <img src="{{ public_path('images/pdf/Group 2.png') }}" width="100%" alt="">
        </div>
    </div>
</body>

</html>
