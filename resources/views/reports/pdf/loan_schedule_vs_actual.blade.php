@extends('reports.pdf.layout', ['title' => 'Rencana vs Realisasi Angsuran', 'identity' => $identity, 'period' => $period])

@section('content')
    <p class="muted" style="margin-bottom:8px">
        {{ $totals['count'] ?? 0 }} pinjaman · gap pokok
        {{ number_format($totals['gap_principal'] ?? 0, 0, ',', '.') }} · gap jasa
        {{ number_format($totals['gap_interest'] ?? 0, 0, ',', '.') }}
    </p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kelompok</th>
                <th>Produk</th>
                <th class="num">Rencana Pokok</th>
                <th class="num">Realisasi Pokok</th>
                <th class="num">Gap Pokok</th>
                <th class="num">Rencana Jasa</th>
                <th class="num">Realisasi Jasa</th>
                <th class="num">Gap Jasa</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>{{ $row['group_name'] }}</td>
                    <td>{{ $row['product_code'] }}</td>
                    <td class="num">{{ number_format($row['plan_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['actual_principal'], 0, ',', '.') }}</td>
                    <td class="num {{ $row['gap_principal'] > 0 ? 'neg' : '' }}">{{ number_format($row['gap_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['plan_interest'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($row['actual_interest'], 0, ',', '.') }}</td>
                    <td class="num {{ $row['gap_interest'] > 0 ? 'neg' : '' }}">{{ number_format($row['gap_interest'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="ctr muted">Tidak ada data.</td>
                </tr>
            @endforelse
            @if(count($rows) > 0)
                <tr class="total">
                    <td colspan="3">Jumlah</td>
                    <td class="num">{{ number_format($totals['plan_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['actual_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['gap_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['plan_interest'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['actual_interest'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['gap_interest'], 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
