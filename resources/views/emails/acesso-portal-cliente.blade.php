{{-- Email HTML simples e auto-contido: os clientes de email ignoram folhas de
     estilo externas e boa parte do CSS moderno, por isso vai tudo em linha. --}}
<div style="font-family:Arial,Helvetica,sans-serif;background:#f4f5f7;padding:24px 0;margin:0">
    <div style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb">

        <div style="background:#4f46e5;padding:22px 28px">
            <h1 style="margin:0;color:#ffffff;font-size:19px;font-weight:bold">{{ $empresa }}</h1>
            <p style="margin:5px 0 0;color:#c7d2fe;font-size:13px">Portal do Cliente</p>
        </div>

        <div style="padding:28px">
            <p style="margin:0 0 14px;color:#111827;font-size:15px">
                Olá{{ $cliente->name ? ', ' . e($cliente->name) : '' }},
            </p>

            <p style="margin:0 0 20px;color:#374151;font-size:14px;line-height:1.6">
                @if($senha !== null)
                    A <strong>{{ $empresa }}</strong> criou-lhe acesso ao portal do cliente. Aí pode consultar
                    as suas facturas, proformas, o extracto de conta e os seus dados, a qualquer hora.
                @else
                    A <strong>{{ $empresa }}</strong> actualizou os seus dados de entrada no portal do cliente.
                    A senha continua a mesma.
                @endif
            </p>

            {{-- Entra-se com QUALQUER UM destes, sempre com a mesma senha. --}}
            <p style="margin:0 0 8px;color:#6b7280;font-size:12px">Pode entrar com qualquer um destes dados:</p>
            <table style="width:100%;border-collapse:collapse;background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:20px">
                <tr>
                    <td style="padding:12px 16px;color:#6b7280;font-size:12px;width:110px">Email</td>
                    <td style="padding:12px 16px;color:#111827;font-size:14px;font-weight:bold">{{ $cliente->email }}</td>
                </tr>
                @if($telefone)
                <tr>
                    <td style="padding:12px 16px;color:#6b7280;font-size:12px;border-top:1px solid #e5e7eb">Telefone</td>
                    <td style="padding:12px 16px;color:#111827;font-size:14px;font-weight:bold;border-top:1px solid #e5e7eb">{{ $telefone }}</td>
                </tr>
                @endif
                @if($utilizador)
                <tr>
                    <td style="padding:12px 16px;color:#6b7280;font-size:12px;border-top:1px solid #e5e7eb">Utilizador</td>
                    <td style="padding:12px 16px;color:#111827;font-size:14px;font-weight:bold;border-top:1px solid #e5e7eb;font-family:'Courier New',monospace">{{ $utilizador }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding:12px 16px;color:#6b7280;font-size:12px;border-top:1px solid #e5e7eb">Senha</td>
                    <td style="padding:12px 16px;border-top:1px solid #e5e7eb">
                        @if($senha !== null)
                            <span style="font-family:'Courier New',monospace;font-size:16px;font-weight:bold;color:#111827;letter-spacing:1px">{{ $senha }}</span>
                        @else
                            <span style="color:#374151;font-size:13px">A que já usava</span>
                        @endif
                    </td>
                </tr>
            </table>

            <div style="text-align:center;margin-bottom:22px">
                <a href="{{ $url }}"
                   style="display:inline-block;background:#4f46e5;color:#ffffff;text-decoration:none;padding:13px 30px;border-radius:8px;font-size:15px;font-weight:bold">
                    Entrar no portal
                </a>
            </div>

            {{-- A senha viajou por email e passou por quem a criou: dizer isto
                 é o mínimo, e o portal tem ecrã próprio para a trocar. --}}
            @if($senha !== null)
            <div style="background:#fffbeb;border-left:3px solid #f59e0b;padding:12px 16px;border-radius:0 6px 6px 0">
                <p style="margin:0;color:#92400e;font-size:13px;line-height:1.5">
                    <strong>Mude a senha assim que entrar.</strong> Esta chegou-lhe por email, por isso
                    não é secreta. No portal, em <em>Perfil</em>, pode escolher outra.
                </p>
            </div>
            @endif

            <p style="margin:20px 0 0;color:#6b7280;font-size:12px;line-height:1.5">
                Se este endereço não lhe é familiar: {{ $url }}<br>
                Não pediu este acesso? Avise a {{ $empresa }} e ignore esta mensagem.
            </p>
        </div>

        <div style="background:#f9fafb;padding:14px 28px;border-top:1px solid #e5e7eb">
            <p style="margin:0;color:#9ca3af;font-size:11px;text-align:center">
                Mensagem automática enviada por {{ $empresa }}.
            </p>
        </div>
    </div>
</div>
