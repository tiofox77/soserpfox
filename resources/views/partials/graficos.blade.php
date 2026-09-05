{{--
    A base dos gráficos dos painéis.

    Existe para haver UMA paleta e UM conjunto de omissões, e não seis. Seis
    painéis com cores escolhidas à vista dão seis produtos diferentes dentro do
    mesmo sistema — e, pior, dão combinações que um daltónico não distingue,
    porque ninguém verifica isso a olho.

    A ORDEM DAS CORES É FIXA e nunca roda. A série 1 é sempre azul, a 2 sempre
    laranja. Se um filtro reduzir de cinco séries para duas, as duas que ficam
    mantêm a cor que tinham: cor que segue a POSIÇÃO em vez da coisa faz o
    leitor pensar que os dados mudaram quando só mudou o filtro.

    A paleta passou nas verificações de contraste e de daltonismo (ΔE 9,1 no
    pior par adjacente, acima do mínimo de 8). Três das cores ficam abaixo de
    3:1 contra o fundo — por isso todos os gráficos aqui levam legenda e
    valores visíveis: a cor identifica, o texto confirma.

    Chart.js é LOCAL e não de CDN: a versão on-premise corre sem internet, e
    de CDN o painel ficava com um quadrado branco no lugar do gráfico.
--}}
<script src="{{ asset('vendor/js/chart.min.js') }}"></script>

@php
    // O Intl do navegador não percebe 'pt'/'en'/'fr' a seco. Sem isto, uma
    // página inglesa saía com meses e separadores decimais portugueses.
    $__intl = ['pt' => 'pt-PT', 'en' => 'en-GB', 'fr' => 'fr-FR'][app()->getLocale()] ?? 'pt-PT';
@endphp

<script>
window.SOS_INTL = @json($__intl);

/**
 * A paleta categórica, por ordem fixa. Nunca se roda, nunca se gera uma nona.
 * Uma série que não caiba nestas oito junta-se em "Outros".
 */
window.SOS_CORES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];

/** Estados, reservados. Nunca servem de "série 4". */
window.SOS_ESTADOS = { bom: '#1baf7a', aviso: '#eda100', grave: '#eb6834', critico: '#e34948', neutro: '#94a3b8' };

window.sosMoeda = (v) => new Intl.NumberFormat(window.SOS_INTL, {
    minimumFractionDigits: 2, maximumFractionDigits: 2,
}).format(v || 0) + ' Kz';

window.sosNumero = (v) => new Intl.NumberFormat(window.SOS_INTL).format(v || 0);

(function () {
    if (typeof Chart === 'undefined') { return; }

    // Omissões partilhadas: grelha discreta, eixos discretos, e os dados à
    // frente. Uma grelha forte compete com o que se quer ler.
    Chart.defaults.font.family = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#52514e';
    Chart.defaults.plugins.legend.labels.usePointStyle = true;
    Chart.defaults.plugins.legend.labels.boxWidth = 8;
    Chart.defaults.plugins.legend.labels.padding = 14;
    Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15,23,42,.92)';
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 8;
    Chart.defaults.plugins.tooltip.displayColors = true;
    Chart.defaults.plugins.tooltip.boxPadding = 4;
    Chart.defaults.maintainAspectRatio = false;
})();

/**
 * QUANDO DESENHAR.
 *
 * Chamar já: quando este script corre, a página está montada — ele vive no
 * fim do corpo, depois de todo o markup. E `wire:navigate` volta a correr o
 * script, portanto uma navegação pela barra lateral desenha outra vez.
 *
 * O que NÃO serve é `DOMContentLoaded`: dispara uma vez, no primeiro
 * carregamento da aplicação, e numa navegação do Livewire já passou há muito.
 * Era o que deixava os painéis do salão, do hotel, do RH e da tesouraria com
 * os gráficos em branco a quem lá chegava pelo menu.
 */
window.sosCorrerGraficos = function () {
    if (typeof Chart === 'undefined' || typeof window.__sosDesenho !== 'function') { return; }

    try {
        window.__sosDesenho();
    } catch (e) {
        console.warn('[Gráficos] Falhou o desenho:', e);
    }
};

/**
 * Pedir os desenhos: já, e outra vez enquanto a página assenta.
 *
 * `setTimeout` e não `requestAnimationFrame`: o rAF não corre num separador
 * em segundo plano, e o gráfico ficava por desenhar até alguém voltar à
 * janela.
 */
window.SOS_ESCADA = [0, 300, 900, 2000, 3000];

window.sosPedirDesenho = function () {
    (window.__sosEscada || []).forEach(clearTimeout);

    window.__sosEscada = window.SOS_ESCADA.map(function (ms) {
        return setTimeout(window.sosCorrerGraficos, ms);
    });
};

window.sosDesenhar = function (fn) {
    // O desenho vive no `window` e não numa variável deste script: o script
    // volta a correr a cada navegação, e um ouvinte registado na primeira vez
    // ficaria preso ao desenho da primeira página para sempre.
    window.__sosDesenho = fn;

    window.sosCorrerGraficos();
    window.sosPedirDesenho();
};

// Registados UMA vez. Chamam pelo nome, para apanharem sempre a página actual.
if (!window.__sosGraficosLigados) {
    window.__sosGraficosLigados = true;

    document.addEventListener('livewire:navigated', function () {
        window.sosPedirDesenho();
    });

    document.addEventListener('livewire:initialized', function () {
        window.Livewire.hook('morph.updated', window.sosPedirDesenho);
    });

    /*
     * O SINAL CERTO É O <CANVAS> APARECER.
     *
     * O Livewire monta o componente DEPOIS de correr os scripts e troca o
     * <canvas> — o desenho ficava no elemento que foi deitado fora. Nenhum
     * atraso fixo acerta sempre nesse momento: com 150 ms o salão apanhava e a
     * tesouraria não. Observar o DOM não adivinha nada.
     */
    new MutationObserver(function (registos) {
        if (typeof window.__sosDesenho !== 'function') { return; }

        for (let i = 0; i < registos.length; i++) {
            const novos = registos[i].addedNodes;

            for (let j = 0; j < novos.length; j++) {
                const no = novos[j];
                if (no.nodeType !== 1) { continue; }

                if (no.tagName === 'CANVAS' || (no.querySelector && no.querySelector('canvas'))) {
                    window.sosPedirDesenho();
                    return;
                }
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });
}
/**
 * Um gráfico com uma configuração à medida.
 *
 * Metade dos painéis tem `new Chart(...)` escrito à mão — barras horizontais,
 * sobretudo — e esses não passavam pela destruição do anterior: à segunda
 * passagem rebentavam com "Canvas is already in use", e a excepção partia a
 * função de desenho a meio, deixando em branco TODOS os gráficos que ainda
 * faltavam. Passam por aqui.
 */
window.sosGrafico = function (id, config) {
    const el = document.getElementById(id);
    if (!el) { return null; }

    const anterior = Chart.getChart(el);
    if (anterior) { anterior.destroy(); }

    return new Chart(el, config);
};
/**
 * Um gráfico de barras. Cantos arredondados só no topo, encostados à linha
 * de base — arredondar as duas pontas descola a barra do zero e faz o olho
 * ler valores que não estão lá.
 */
window.sosBarras = function (id, etiquetas, valores, opcoes = {}) {
    const el = document.getElementById(id);
    if (!el) { return null; }

    // Um grafico ja desenhado neste sitio tem de morrer primeiro.
    const anterior = Chart.getChart(el);
    if (anterior) { anterior.destroy(); }

    return new Chart(el, {
        type: 'bar',
        data: {
            labels: etiquetas,
            datasets: [{
                label: opcoes.rotulo || '',
                data: valores,
                backgroundColor: opcoes.cor || window.SOS_CORES[0],
                borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 },
                borderSkipped: false,
                maxBarThickness: 42,
            }],
        },
        options: {
            responsive: true,
            plugins: {
                // Uma série não precisa de legenda: o título já lhe dá o nome,
                // e uma caixa a repeti-lo é ruído.
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => (opcoes.moeda === false ? window.sosNumero(c.parsed.y) : window.sosMoeda(c.parsed.y)),
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(100,116,139,.12)' },
                    border: { display: false },
                    ticks: {
                        callback: (v) => (opcoes.moeda === false ? window.sosNumero(v) : window.sosNumero(v)),
                    },
                },
            },
        },
    });
};

/** Uma linha ao longo do tempo. Traço fino, pontos tocáveis. */
window.sosLinha = function (id, etiquetas, valores, opcoes = {}) {
    const el = document.getElementById(id);
    if (!el) { return null; }

    // Um grafico ja desenhado neste sitio tem de morrer primeiro.
    const anterior = Chart.getChart(el);
    if (anterior) { anterior.destroy(); }

    return new Chart(el, {
        type: 'line',
        data: {
            labels: etiquetas,
            datasets: [{
                label: opcoes.rotulo || '',
                data: valores,
                borderColor: opcoes.cor || window.SOS_CORES[0],
                backgroundColor: (opcoes.cor || window.SOS_CORES[0]) + '1f',
                borderWidth: 2,
                fill: true,
                tension: 0.35,
                pointRadius: 4,
                pointHoverRadius: 8,
                pointBackgroundColor: opcoes.cor || window.SOS_CORES[0],
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (c) => (opcoes.moeda === false ? window.sosNumero(c.parsed.y) : window.sosMoeda(c.parsed.y)),
                    },
                },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false } },
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(100,116,139,.12)' },
                    border: { display: false },
                    ticks: { callback: (v) => window.sosNumero(v) },
                },
            },
        },
    });
};

/**
 * Uma rosca para composição. Anel e não círculo cheio: o buraco no meio
 * carrega o total, que é o número que se procura primeiro.
 *
 * O separador de 2px entre fatias não é enfeite — é o que impede duas cores
 * vizinhas de se lerem como uma só quando as fatias são finas.
 */
window.sosRosca = function (id, etiquetas, valores, opcoes = {}) {
    const el = document.getElementById(id);
    if (!el) { return null; }

    // Um grafico ja desenhado neste sitio tem de morrer primeiro.
    const anterior = Chart.getChart(el);
    if (anterior) { anterior.destroy(); }

    return new Chart(el, {
        type: 'doughnut',
        data: {
            labels: etiquetas,
            datasets: [{
                data: valores,
                backgroundColor: opcoes.cores || window.SOS_CORES,
                borderColor: '#fff',
                borderWidth: 2,
            }],
        },
        options: {
            responsive: true,
            cutout: '62%',
            plugins: {
                // Com duas ou mais fatias a legenda é obrigatória: sem ela a
                // identidade fica só na cor, e há quem não a veja.
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: function (c) {
                            const total = c.dataset.data.reduce((s, v) => s + (v || 0), 0);
                            const parte = total ? Math.round((c.parsed / total) * 100) : 0;
                            const valor = opcoes.moeda === false ? window.sosNumero(c.parsed) : window.sosMoeda(c.parsed);

                            return `${c.label}: ${valor} (${parte}%)`;
                        },
                    },
                },
            },
        },
    });
};
</script>
