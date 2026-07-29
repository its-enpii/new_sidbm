@extends('reports.pdf.layout', ['title' => 'Neraca', 'identity' => $identity, 'period' => $period])

@section('content')
<table>
    <thead>
        <tr>
            <th width="15%">Kode</th>
            <th>Nama Akun</th>
            <th class="num" width="20%">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sections as $l1)
            <tr class="section">
                <td colspan="3">{{ $l1['code'] }}. {{ $l1['name'] }}</td>
            </tr>
            @foreach($l1['children'] as $l2)
                <tr class="sub">
                    <td>{{ $l2['code'] }}</td>
                    <td colspan="2">{{ $l2['name'] }}</td>
                </tr>
                @foreach($l2['children'] as $l3)
                    <tr>
                        <td>{{ $l3['code'] }}</td>
                        <td>{{ $l3['name'] }}</td>
                        <td class="num @if($l3['balance'] < 0) neg @endif">
                            @if($l3['balance'] < 0)
                                ({{ number_format(abs($l3['balance']), 2, ',', '.') }})
                            @else
                                {{ number_format($l3['balance'], 2, ',', '.') }}
                            @endif
                        </td>
                    </tr>
                @endforeach
            @endforeach
            @php
                $sectionLabel = match ($l1['account_type'] ?? '') {
                    'asset' => 'Jumlah Aset',
                    'liability' => 'Jumlah Utang',
                    'equity' => 'Jumlah Modal',
                    default => 'Jumlah '.$l1['name'],
                };
            @endphp
            <tr class="total">
                <td colspan="2">{{ $sectionLabel }}</td>
                <td class="num @if(($l1['balance'] ?? 0) < 0) neg @endif">
                    @if(($l1['balance'] ?? 0) < 0)
                        ({{ number_format(abs($l1['balance']), 2, ',', '.') }})
                    @else
                        {{ number_format($l1['balance'] ?? 0, 2, ',', '.') }}
                    @endif
                </td>
            </tr>
        @endforeach
        <tr class="total">
            <td colspan="2">Jumlah Liabilitas + Ekuitas</td>
            <td class="num">{{ number_format($totals['liabilities_equity'], 2, ',', '.') }}</td>
        </tr>
    </tbody>
</table>
<p class="muted">Laba berjalan: {{ number_format($totals['net_income'], 2, ',', '.') }} · Seimbang: {{ $balanced ? 'Ya' : 'Tidak' }}</p>
@endsection
