{{--
    O «COMEÇAR GRÁTIS» NO TELEMÓVEL (26/09/2026).

    No topo, num telemóvel estreito, o logótipo, o botão e o menu não cabiam na
    mesma linha e o botão ficava por cima do logótipo. Abaixo de 640px o botão
    sai do topo (classe `sos-cta-topo`) e passa para uma barra fixa em baixo —
    onde o polegar chega, e sempre à vista de quem vem do anúncio — com o
    WhatsApp ao lado.

    CSS escrito aqui e não em classes do Tailwind: o publico.css compilado não
    acompanha cada mudança dos Blades, e uma classe que lá não esteja não faz
    nada em produção (foi o que aconteceu ao `sm:h-16` do logótipo).

    Os cliques medem-se como os outros, pelo sos-tracker.js: o link para
    `/register` conta como click_register, o de `wa.me` como click_whatsapp.

    O aviso de cookies fica por cima desta barra enquanto está aberto — a
    escolha vem primeiro.
--}}
<style>
    .sos-cta-fundo { display: none; }
    @media (max-width: 639px) {
        .sos-cta-topo { display: none !important; }
        .sos-cta-fundo {
            display: flex; gap: 8px; position: fixed; left: 0; right: 0; bottom: 0; z-index: 45;
            padding: 10px 16px calc(10px + env(safe-area-inset-bottom));
            background: rgba(255, 255, 255, .97); border-top: 1px solid #e5e7eb;
            box-shadow: 0 -8px 24px -12px rgba(15, 23, 42, .25);
        }
        .sos-cta-fundo a {
            display: inline-flex; align-items: center; justify-content: center; min-height: 48px;
            border-radius: 12px; font-weight: 700; font-size: 15px; text-decoration: none;
        }
        .sos-cta-fundo a:focus-visible { outline: 3px solid #1d4ed8; outline-offset: 2px; }
        .sos-cta-principal { flex: 1; color: #fff; background: linear-gradient(90deg, #2563eb, #7c3aed); }
        .sos-cta-wa { width: 52px; flex: none; color: #fff; background: #16a34a; font-size: 22px; }
        /* O fim da página não fica escondido debaixo da barra. */
        body { padding-bottom: calc(69px + env(safe-area-inset-bottom)); }
    }
</style>
@php
    // Sem número dado (a página inicial), o contacto da plataforma — o mesmo
    // que o site mostra, das definições.
    $whatsapp ??= preg_replace('/\D/', '', \App\Support\Entrada::contacto()['telefone']);
@endphp
<div class="sos-cta-fundo">
    <a href="{{ route('register') }}" class="sos-cta-principal">
        <i class="fas fa-rocket" aria-hidden="true" style="margin-right: 8px;"></i>{{ __('Começar Grátis') }}
    </a>
    @if(! empty($whatsapp))
        <a href="https://wa.me/{{ $whatsapp }}" target="_blank" rel="noopener" class="sos-cta-wa" aria-label="WhatsApp">
            <i class="fab fa-whatsapp" aria-hidden="true"></i>
        </a>
    @endif
</div>
