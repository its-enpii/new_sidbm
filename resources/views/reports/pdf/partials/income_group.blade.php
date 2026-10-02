{{-- Group (L2) header + account rows (L4) + Jumlah, mengikuti legacy laba_rugi.blade.php --}}
<tr style="background: rgb({{ $bg }}); font-weight: bold;">
    <td colspan="4" height="14">{{ $group['code'] }}. {{ $group['name'] }}</td>
</tr>

@php
    $jumPrior = 0;
    $jumYtd = 0;
@endphp

@foreach ($group['children'] as $idx => $row)
    <tr style="background: {{ $idx % 2 == 0 ? 'rgb(230, 230, 230)' : 'rgb(255, 255, 255)' }};">
        <td align="left">{{ $row['code'] }}. {{ $row['name'] }}</td>
        <td align="right">{{ number_format($row['prior'], 2) }}</td>
        <td align="right">{{ number_format($row['current'], 2) }}</td>
        <td align="right">{{ number_format($row['ytd'], 2) }}</td>
    </tr>
    @php
        $jumPrior += $row['prior'];
        $jumYtd += $row['ytd'];
    @endphp
@endforeach

<tr style="background: rgb({{ $bg }}); font-weight: bold;">
    <td align="left" height="14">Jumlah {{ $group['code'] }}. {{ $group['name'] }}</td>
    <td align="right">{{ number_format($group['prior'], 2) }}</td>
    <td align="right">{{ number_format($group['current'], 2) }}</td>
    <td align="right">{{ number_format($group['ytd'], 2) }}</td>
</tr>
