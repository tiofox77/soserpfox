{{-- O MOLDE DOS EMAILS DO PROGRAMA DE REVENDEDORES. Tudo em linha: os
     clientes de email ignoram folhas de estilo externas. --}}
<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f5f7;padding:24px 0;margin:0">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">

        <div style="background:{{ $cor }};padding:22px 28px">
            <h1 style="margin:0;color:#ffffff;font-size:19px;font-weight:bold">{{ $plataforma }}</h1>
            <p style="margin:5px 0 0;color:#ede9fe;font-size:13px">{{ __('Programa de Revendedores') }}</p>
        </div>

        <div style="padding:28px">
            <p style="margin:0 0 14px;color:#111827;font-size:15px">{{ $saudacao }}</p>

            @foreach($linhas as $linha)
                <p style="margin:0 0 14px;color:#374151;font-size:14px;line-height:1.6">{{ $linha }}</p>
            @endforeach

            @if($dados)
                <table style="width:100%;border-collapse:collapse;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin:6px 0 20px">
                    @foreach($dados as $rotulo => $valor)
                        <tr>
                            <td style="padding:11px 16px;color:#6b7280;font-size:12px;width:150px;{{ $loop->first ? '' : 'border-top:1px solid #e5e7eb;' }}">{{ $rotulo }}</td>
                            <td style="padding:11px 16px;color:#111827;font-size:14px;font-weight:bold;word-break:break-all;{{ $loop->first ? '' : 'border-top:1px solid #e5e7eb;' }}">{{ $valor }}</td>
                        </tr>
                    @endforeach
                </table>
            @endif

            @if($botao)
                <div style="text-align:center;margin:8px 0 22px">
                    <a href="{{ $botao['url'] }}"
                       style="display:inline-block;background:{{ $cor }};color:#ffffff;text-decoration:none;padding:13px 30px;border-radius:8px;font-size:15px;font-weight:bold">
                        {{ $botao['texto'] }}
                    </a>
                </div>
            @endif

            @if($alerta)
                <div style="background:#fffbeb;border-left:3px solid #f59e0b;padding:12px 16px;border-radius:0 6px 6px 0">
                    <p style="margin:0;color:#92400e;font-size:13px;line-height:1.5">{{ $alerta }}</p>
                </div>
            @endif

            @if($botao)
                <p style="margin:20px 0 0;color:#6b7280;font-size:12px;line-height:1.5">
                    {{ __('Se o botão não abrir:') }} {{ $botao['url'] }}
                </p>
            @endif
        </div>

        <div style="background:#f9fafb;padding:14px 28px;border-top:1px solid #e5e7eb">
            <p style="margin:0;color:#9ca3af;font-size:11px;text-align:center">
                {{ __('Mensagem automática enviada por :plataforma.', ['plataforma' => $plataforma]) }}
            </p>
        </div>
    </div>
</div>
