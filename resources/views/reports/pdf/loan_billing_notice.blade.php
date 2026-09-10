@extends('reports.pdf.layout', ['title' => 'Surat Tagihan Pinjaman', 'identity' => $identity, 'period' => $period])

@section('content')
<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 11px;">
    <tr>
        <td colspan="11" align="center">
            <div style="font-size: 16px;"><b>DAFTAR TAGIHAN ANGSURAN PINJAMAN</b></div>
            <div style="font-size: 14px;"><b>PERIODE {{ strtoupper($period['period_label'] ?? '') }}</b></div>
        </td>
    </tr>
    <tr><td colspan="11" height="10"></td></tr>
</table>

<table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-bottom: 12px;">
    <tr style="background: rgb(235, 235, 235); font-weight: bold;">
        <td style="padding: 4px;">Kelompok: {{ $totals['groups_count'] }}</td>
        <td style="padding: 4px;">Pinjaman: {{ $totals['loans_count'] }}</td>
        <td style="padding: 4px;">Pemanfaat: {{ $totals['members_count'] }}</td>
        <td align="right" style="padding: 4px;">Pokok: Rp {{ number_format($totals['principal'], 2, ',', '.') }}</td>
        <td align="right" style="padding: 4px;">Jasa: Rp {{ number_format($totals['interest'], 2, ',', '.') }}</td>
        <td align="right" style="padding: 4px;">Denda: Rp {{ number_format($totals['penalty'], 2, ',', '.') }}</td>
        <td align="right" style="padding: 4px;">Total Tagihan: Rp {{ number_format($totals['total'], 2, ',', '.') }}</td>
    </tr>
</table>

@forelse($groups as $group)
    <div style="margin-top: 10px; margin-bottom: 4px; font-size: 11px; font-weight: bold;">
        Kelompok: {{ $group['group_name'] }} (Desa: {{ $group['village_name'] }})
    </div>
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 9.5px; margin-bottom: 10px; border-collapse: collapse;">
        <thead>
            <tr style="background: rgb(74, 74, 74); font-weight: bold; color: #fff;">
                <th style="border: 1px solid #000; padding: 3px;" width="20">No</th>
                <th style="border: 1px solid #000; padding: 3px;" width="110">No. SPK</th>
                <th style="border: 1px solid #000; padding: 3px;">Nama Anggota</th>
                <th style="border: 1px solid #000; padding: 3px;" width="25">Ke</th>
                <th style="border: 1px solid #000; padding: 3px;" width="65">Jatuh Tempo</th>
                <th style="border: 1px solid #000; padding: 3px;" align="right" width="65">Pokok</th>
                <th style="border: 1px solid #000; padding: 3px;" align="right" width="55">Jasa</th>
                <th style="border: 1px solid #000; padding: 3px;" align="right" width="45">Denda</th>
                <th style="border: 1px solid #000; padding: 3px;" align="right" width="70">Total</th>
                <th style="border: 1px solid #000; padding: 3px;" width="80">No. HP</th>
                <th style="border: 1px solid #000; padding: 3px;" width="45">Status</th>
            </tr>
        </thead>
        <tbody>
            @php $rowNo = 1; @endphp
            @foreach($group['loans'] as $loan)
                @foreach($loan['items'] as $item)
                    <tr style="background: {{ $rowNo % 2 == 1 ? 'rgb(248, 248, 248)' : 'rgba(255, 255, 255)' }};">
                        <td style="border: 1px solid #ccc; padding: 2px;" align="center">{{ $rowNo++ }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;">{{ $item['loan_number'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;">{{ $item['beneficiary_name'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;" align="center">{{ $item['installment_number'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;" align="center">{{ $item['due_date'] }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;" align="right">{{ number_format($item['principal_due'], 2, ',', '.') }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;" align="right">{{ number_format($item['interest_due'], 2, ',', '.') }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;" align="right">{{ number_format($item['penalty_due'], 2, ',', '.') }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px; font-weight: bold;" align="right">{{ number_format($item['total_due'], 2, ',', '.') }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;">{{ $item['phone'] ?? '—' }}</td>
                        <td style="border: 1px solid #ccc; padding: 2px;" align="center">{{ ucfirst($item['status']) }}</td>
                    </tr>
                @endforeach
            @endforeach
            <tr style="background: rgb(220, 220, 220); font-weight: bold;">
                <td colspan="5" style="border: 1px solid #999; padding: 3px;">Subtotal Kelompok {{ $group['group_name'] }} ({{ $group['totals']['members_count'] }} orang)</td>
                <td style="border: 1px solid #999; padding: 3px;" align="right">{{ number_format($group['totals']['principal'], 2, ',', '.') }}</td>
                <td style="border: 1px solid #999; padding: 3px;" align="right">{{ number_format($group['totals']['interest'], 2, ',', '.') }}</td>
                <td style="border: 1px solid #999; padding: 3px;" align="right">{{ number_format($group['totals']['penalty'], 2, ',', '.') }}</td>
                <td style="border: 1px solid #999; padding: 3px;" align="right">{{ number_format($group['totals']['total'], 2, ',', '.') }}</td>
                <td colspan="2" style="border: 1px solid #999;"></td>
            </tr>
        </tbody>
    </table>
@empty
    <div style="text-align: center; padding: 20px; color: #666; font-size: 11px;">
        Tidak ada tagihan angsuran pinjaman untuk filter dan periode terpilih.
    </div>
@endforelse

@if(count($groups) > 0)
    <table border="0" width="100%" cellspacing="0" cellpadding="0" style="font-size: 10px; margin-top: 10px; border-collapse: collapse;">
        <tr style="background: rgb(180, 180, 180); font-weight: bold;">
            <td style="border: 1px solid #666; padding: 4px;" colspan="5">GRAND TOTAL TAGIHAN ({{ $totals['members_count'] }} pemanfaat di {{ $totals['groups_count'] }} kelompok)</td>
            <td style="border: 1px solid #666; padding: 4px;" align="right">{{ number_format($totals['principal'], 2, ',', '.') }}</td>
            <td style="border: 1px solid #666; padding: 4px;" align="right">{{ number_format($totals['interest'], 2, ',', '.') }}</td>
            <td style="border: 1px solid #666; padding: 4px;" align="right">{{ number_format($totals['penalty'], 2, ',', '.') }}</td>
            <td style="border: 1px solid #666; padding: 4px;" align="right">{{ number_format($totals['total'], 2, ',', '.') }}</td>
            <td colspan="2" style="border: 1px solid #666;"></td>
        </tr>
    </table>
@endif

@if(!empty($signature))
    <div style="margin-top: 20px; page-break-inside: avoid;">
        {!! $signature !!}
    </div>
@endif
@endsection
