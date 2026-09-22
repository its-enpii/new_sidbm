<style>
    footer.disbursement-footer {
        position: fixed;
        bottom: -40px;
        left: 0px;
        right: 0px;
    }
</style>
<footer class="disbursement-footer">
    <table width="100%" style="border-top: 1px solid #888; font-size: 8px; color: #555;">
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
