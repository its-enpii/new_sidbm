@extends('reports.pdf.layout', ['title' => 'Laporan Arus Kas', 'identity' => $identity, 'period' => $period])

@section('content')
    <p class="muted" style="margin-bottom:8px">
        Metode langsung · akun kas 1.1.01*
        @if(!empty($reconciled))
            · rekonsiliasi OK
        @else
            · selisih implied {{ number_format($implied_closing ?? 0, 0, ',', '.') }}
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Uraian</th>
                <th class="num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <tr class="sub">
                <td>Saldo kas awal periode</td>
                <td class="num">{{ number_format($opening_cash, 0, ',', '.') }}</td>
            </tr>

            @foreach($sections as $section)
                <tr class="section">
                    <td colspan="2">{{ $section['label'] }}</td>
                </tr>
                @forelse($section['lines'] as $line)
                    <tr>
                        <td>
                            {{ $line['label'] }}
                            @if(($line['count'] ?? 1) > 1)
                                <span class="muted">({{ $line['count'] }} jurnal)</span>
                            @endif
                        </td>
                        <td class="num {{ ($line['amount'] ?? 0) < 0 ? 'neg' : '' }}">
                            {{ number_format($line['amount'], 0, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="muted">Tidak ada mutasi</td>
                    </tr>
                @endforelse
                <tr class="sub">
                    <td>Jumlah {{ strtolower($section['label']) }}</td>
                    <td class="num {{ ($section['total'] ?? 0) < 0 ? 'neg' : '' }}">
                        {{ number_format($section['total'], 0, ',', '.') }}
                    </td>
                </tr>
            @endforeach

            <tr class="total">
                <td>Kenaikan (penurunan) bersih kas</td>
                <td class="num {{ ($net_change ?? 0) < 0 ? 'neg' : '' }}">
                    {{ number_format($net_change, 0, ',', '.') }}
                </td>
            </tr>
            <tr class="total">
                <td>Saldo kas akhir periode</td>
                <td class="num">{{ number_format($closing_cash, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endsection
