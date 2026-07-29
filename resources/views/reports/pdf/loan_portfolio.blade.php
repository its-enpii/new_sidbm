@extends('reports.pdf.layout', ['title' => 'Portofolio Pinjaman', 'identity' => $identity, 'period' => $period])

@section('content')
    <p class="muted" style="margin-bottom:8px">
        Filter:
        @if(($filter ?? 'all') === 'overdue') tunggakan saja
        @elseif(($filter ?? 'all') === 'current') lancar saja
        @else semua aktif
        @endif
        · {{ $totals['count'] ?? 0 }} pinjaman
        · tunggakan {{ $totals['overdue_count'] ?? 0 }}
    </p>

    <table style="margin-bottom:14px">
        <thead>
            <tr>
                <th>Aging</th>
                <th class="ctr">Jumlah</th>
                <th class="num">Sisa Pokok</th>
                <th class="num">Nilai Tunggakan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($aging as $bucket)
                <tr>
                    <td>{{ $bucket['label'] }}</td>
                    <td class="ctr">{{ $bucket['count'] }}</td>
                    <td class="num">{{ number_format($bucket['principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($bucket['overdue'], 0, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td>Total</td>
                <td class="ctr">{{ $totals['count'] }}</td>
                <td class="num">{{ number_format($totals['principal_remaining'], 0, ',', '.') }}</td>
                <td class="num">{{ number_format($totals['overdue_amount'], 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($by_village))
        <table style="margin-bottom:14px">
            <thead>
                <tr>
                    <th>Desa</th>
                    <th class="ctr">Pinjaman</th>
                    <th class="num">Sisa Pokok</th>
                    <th class="num">Tunggakan</th>
                    <th class="ctr"># Nunggak</th>
                </tr>
            </thead>
            <tbody>
                @foreach($by_village as $v)
                    <tr>
                        <td>{{ $v['village'] }}</td>
                        <td class="ctr">{{ $v['count'] }}</td>
                        <td class="num">{{ number_format($v['principal'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($v['overdue'], 0, ',', '.') }}</td>
                        <td class="ctr">{{ $v['overdue_count'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @php
        $sections = [];
        foreach ($rows as $row) {
            $key = $row['village_name'] ?? '—';
            if (! isset($sections[$key])) {
                $sections[$key] = [
                    'village' => $key,
                    'rows' => [],
                    'count' => 0,
                    'principal_disbursed' => 0.0,
                    'principal_remaining' => 0.0,
                    'overdue_principal' => 0.0,
                    'overdue_interest' => 0.0,
                ];
            }
            $sections[$key]['rows'][] = $row;
            $sections[$key]['count']++;
            $sections[$key]['principal_disbursed'] += (float) ($row['principal_disbursed'] ?? 0);
            $sections[$key]['principal_remaining'] += (float) ($row['principal_remaining'] ?? 0);
            $sections[$key]['overdue_principal'] += (float) ($row['overdue_principal'] ?? 0);
            $sections[$key]['overdue_interest'] += (float) ($row['overdue_interest'] ?? 0);
        }
    @endphp
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Kelompok</th>
                <th>Produk</th>
                <th>Cair</th>
                <th class="num">Alokasi</th>
                <th class="num">Sisa Pokok</th>
                <th class="num">Tungg. Pokok</th>
                <th class="num">Tungg. Jasa</th>
                <th class="ctr">Hari</th>
                <th>Jatuh tempo</th>
            </tr>
        </thead>
        <tbody>
            @forelse($sections as $section)
                <tr class="total">
                    <td colspan="10"><strong>Desa {{ $section['village'] }}</strong> · {{ $section['count'] }} pinjaman</td>
                </tr>
                @foreach($section['rows'] as $row)
                    <tr>
                        <td>{{ $row['id'] }}</td>
                        <td>{{ $row['group_name'] }}</td>
                        <td>{{ $row['product_code'] }}</td>
                        <td>{{ $row['disbursed_at'] ? \Carbon\CarbonImmutable::parse($row['disbursed_at'])->format('d/m/Y') : '—' }}</td>
                        <td class="num">{{ number_format($row['principal_disbursed'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($row['principal_remaining'], 0, ',', '.') }}</td>
                        <td class="num {{ ($row['overdue_principal'] ?? 0) > 0 ? 'neg' : '' }}">{{ number_format($row['overdue_principal'] ?? 0, 0, ',', '.') }}</td>
                        <td class="num {{ ($row['overdue_interest'] ?? 0) > 0 ? 'neg' : '' }}">{{ number_format($row['overdue_interest'] ?? 0, 0, ',', '.') }}</td>
                        <td class="ctr {{ $row['days_overdue'] > 0 ? 'neg' : '' }}">{{ $row['days_overdue'] > 0 ? $row['days_overdue'] : '—' }}</td>
                        <td>{{ $row['next_due_date'] ? \Carbon\CarbonImmutable::parse($row['next_due_date'])->format('d/m/Y') : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td colspan="4">Total {{ $section['village'] }}</td>
                    <td class="num">{{ number_format($section['principal_disbursed'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($section['principal_remaining'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($section['overdue_principal'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($section['overdue_interest'], 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="ctr muted">Tidak ada pinjaman aktif.</td>
                </tr>
            @endforelse
            @if(count($rows) > 0)
                <tr class="total">
                    <td colspan="4">Jumlah seluruh desa</td>
                    <td class="num">{{ number_format($totals['principal_disbursed'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['principal_remaining'], 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['overdue_principal'] ?? 0, 0, ',', '.') }}</td>
                    <td class="num">{{ number_format($totals['overdue_interest'] ?? 0, 0, ',', '.') }}</td>
                    <td colspan="2"></td>
                </tr>
            @endif
        </tbody>
    </table>
@endsection
