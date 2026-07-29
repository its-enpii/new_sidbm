@extends('reports.pdf.layout', ['title' => 'Laporan Laba Rugi', 'identity' => $identity, 'period' => $period])

@section('content')
<table>
    <thead>
        <tr>
            <th>Rekening</th>
            <th class="num">s.d. {{ $header_lalu }}</th>
            <th class="num">{{ $header_sekarang }}</th>
            <th class="num">s.d. {{ $header_sekarang }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach($groups as $group)
            <tr class="section">
                <td colspan="4">{{ $group['code'] }}. {{ $group['name'] }}</td>
            </tr>
            @foreach($group['children'] as $row)
                <tr>
                    <td>{{ $row['code'] }}. {{ $row['name'] }}</td>
                    <td class="num">{{ number_format($row['prior'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['current'], 2, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['ytd'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="sub">
                <td>Jumlah {{ $group['name'] }}</td>
                <td class="num">{{ number_format($group['prior'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($group['current'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format($group['ytd'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
        <tr class="total">
            <td>Laba (Rugi) Operasional (A)</td>
            <td class="num">{{ number_format($summary['operating']['prior'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['operating']['current'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['operating']['ytd'], 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Laba (Rugi) Non Operasional (B)</td>
            <td class="num">{{ number_format($summary['non_operating']['prior'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['non_operating']['current'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['non_operating']['ytd'], 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Laba (Rugi) Sebelum Pajak (A+B)</td>
            <td class="num">{{ number_format($summary['before_tax']['prior'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['before_tax']['current'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['before_tax']['ytd'], 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Beban Pajak</td>
            <td class="num">{{ number_format($summary['tax']['prior'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['tax']['current'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['tax']['ytd'], 2, ',', '.') }}</td>
        </tr>
        <tr class="total">
            <td>Laba (Rugi) Bersih</td>
            <td class="num">{{ number_format($summary['after_tax']['prior'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['after_tax']['current'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format($summary['after_tax']['ytd'], 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
@endsection
