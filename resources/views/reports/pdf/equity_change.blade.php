@extends('reports.pdf.layout', ['title' => 'Laporan Perubahan Ekuitas', 'identity' => $identity, 'period' => $period])

@section('content')
    <table style="margin-bottom:12px">
        <thead>
            <tr>
                <th>Ringkasan</th>
                <th class="num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bridge as $item)
                <tr class="{{ $item['key'] === 'closing' ? 'total' : '' }}">
                    <td>{{ $item['label'] }}</td>
                    <td class="num {{ ($item['amount'] ?? 0) < 0 ? 'neg' : '' }}">
                        {{ number_format($item['amount'], 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table>
        <thead>
            <tr>
                <th>Rekening</th>
                <th class="num">Awal</th>
                <th class="num">Mutasi</th>
                <th class="num">Akhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['code'] }}. {{ $row['name'] }}@if(!empty($row['is_earnings'])) (laba)@endif</td>
                    <td class="num">{{ number_format($row['opening'], 0, ',', '.') }}</td>
                    <td class="num {{ ($row['movement'] ?? 0) < 0 ? 'neg' : '' }}">{{ number_format($row['movement'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['closing'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="ctr muted">Tidak ada data.</td></tr>
            @endforelse
            @if(count($rows) > 0)
                <tr class="total">
                    <td>Total ekuitas</td>
                    <td class="num">{{ number_format($summary['opening_total'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($summary['movement_total'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($summary['closing_total'], 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
