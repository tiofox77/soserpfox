<div class="footer-line">
    <table class="footer-table" cellspacing="0" cellpadding="0">
        <tr>
            <td class="footer-meta">
                Gerado em {{ now()->format('d/m/Y H:i') }}
                | {{ $tenant?->name ?? 'Empresa' }} - Confidencial
                | INSS Patronal (8%): {{ number_format($payrollItem->inss_employer ?? 0, 2, ',', '.') }} Kz
            </td>
            <td class="footer-brand">
                <img src="{{ asset('brand/soserp-icone-192.png') }}" alt="SOS ERP" class="footer-brand-logo">
                <span>Processado pelo SOS ERP</span>
            </td>
        </tr>
    </table>
</div>
