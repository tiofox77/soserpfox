{{--
    Banner de estado da licença. Só entra no ecrã quando o middleware
    VerificarLicenca partilha $licencaEstado (logo, só na build offline com
    enforce ligado e num estado brando). Na cloud a variável nunca existe.
--}}
@php
    $e = $licencaEstado ?? null;
@endphp
@if($e && $e->mostraAviso())
    @php
        $cor = match($e->estado) {
            \App\Services\Licensing\LicenseState::AVISO => 'background:#fef9c3;color:#854d0e;',
            \App\Services\Licensing\LicenseState::BANNER => 'background:#fed7aa;color:#9a3412;',
            \App\Services\Licensing\LicenseState::SO_LEITURA => 'background:#fecaca;color:#991b1b;',
            default => 'background:#fee2e2;color:#991b1b;',
        };
    @endphp
    <div style="{{ $cor }} padding:10px 16px; text-align:center; font-size:14px; font-weight:600;">
        {{ $e->motivo }}
        <a href="{{ route('licenca.index') }}" style="text-decoration:underline; margin-left:8px;">Gerir licença</a>
    </div>
@endif
