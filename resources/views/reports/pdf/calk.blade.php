@extends('reports.pdf.layout', ['title' => 'Catatan Atas Laporan Keuangan (CALK)', 'identity' => $identity, 'period' => $period])

@section('content')
    <p class="muted" style="margin-bottom:10px">
        {{ $identity['legal_name'] ?? '' }}
        @if(!empty($identity['address'])) · {{ $identity['address'] }}@endif
    </p>

    <table style="margin-bottom:14px">
        <thead>
            <tr>
                <th>Ringkasan posisi</th>
                <th class="num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($highlights as $h)
                <tr>
                    <td>{{ $h['label'] }}</td>
                    <td class="num {{ ($h['amount'] ?? 0) < 0 ? 'neg' : '' }}">
                        {{ number_format($h['amount'], 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="bold" style="margin:10px 0 4px">Kebijakan akuntansi</p>
    <ol style="margin:0 0 12px 18px;padding:0">
        @foreach($policies as $p)
            <li style="margin-bottom:4px">{{ $p }}</li>
        @endforeach
    </ol>

    <p class="bold" style="margin:10px 0 4px">Catatan tambahan</p>
    <div style="white-space:pre-wrap;border:1px solid #ccc;padding:8px;min-height:60px">
        {{ $notes !== '' ? $notes : 'Tidak ada catatan tambahan.' }}
    </div>
@endsection
