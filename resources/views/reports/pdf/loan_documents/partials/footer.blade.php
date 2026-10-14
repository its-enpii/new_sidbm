@php
    $footerStatus = strtolower(trim((string) ($loan['status'] ?? '')));
    $footerStatusAllowed = in_array(
        $footerStatus,
        \App\Domain\Lending\Services\Reports\LoanDocumentService::disbursementStageStatuses(),
        true,
    );
@endphp
@if ($footerStatusAllowed)
<style>
    footer.disbursement-footer {
        position: fixed;
        bottom: -40px;
        left: 0px;
        right: 0px;
    }
</style>
<footer class="disbursement-footer">
    <table width="100%" style="border-top: 1.5px solid #888; font-size: 8px; color: #555;">
        <tr>
            <td align="left">
                <i>No. SPK: {{ $loan['loan_number'] ?? '-' }}</i>
            </td>
            <td align="right">
                <i>Tgl. Cair: {{ $tokens['{tgl_cair}'] ?? ($loan['disbursed_at'] ?? '-') }}</i>
            </td>
        </tr>
    </table>
</footer>
@endif
