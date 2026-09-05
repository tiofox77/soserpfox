/**
 * Os gráficos do painel de facturação.
 *
 * PORQUE VIVE NUM FICHEIRO E NÃO NO BLADE. Isto estava num `<script>` dentro
 * da página. Numa navegação por `wire:navigate` — que é como a barra lateral
 * navega — o Livewire troca o corpo da página e um script em linha NÃO volta a
 * correr. Medido: chegando ao painel pela barra lateral, os cinco gráficos
 * ficavam em branco, sem erro nenhum à vista. Só recarregando a página é que
 * apareciam, e quase ninguém recarrega.
 *
 * Tentou-se `@script` e `@assets` do Livewire; nem um nem outro correram nesta
 * navegação. O que corre sempre é um ficheiro carregado pelo layout com os
 * ouvintes presos ao `document` — o mesmo caminho do gerador de PDF.
 *
 * OS DADOS VÊM DO DOM, não de dentro do script: três nós JSON que o Livewire
 * volta a escrever a cada mudança de período. Assim trocar de semana para ano
 * muda mesmo a linha do gráfico, e não só os números dos cartões.
 */
(function () {
    'use strict';

    let desenhados = [];

    /** Uma biblioteca desce uma vez por página, e só quando faz falta. */
    function carregarUmaVez(src) {
        window.__sosBibliotecas = window.__sosBibliotecas || {};

        if (window.__sosBibliotecas[src]) return window.__sosBibliotecas[src];

        window.__sosBibliotecas[src] = new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = src;
            s.addEventListener('load', resolve, { once: true });
            s.addEventListener('error', reject, { once: true });
            document.head.appendChild(s);
        });

        return window.__sosBibliotecas[src];
    }

    function lerJson(id) {
        const no = document.getElementById(id);
        if (!no) return null;

        try {
            return JSON.parse(no.textContent || 'null');
        } catch (_) {
            return null;
        }
    }

    /** Os textos e o formato de números, escritos pelo servidor. */
    function textos() {
        return lerJson('textosPainel') || { intl: 'pt-PT', t: {} };
    }

    function kz(v, intl) {
        return new Intl.NumberFormat(intl, { maximumFractionDigits: 0 }).format(v) + ' Kz';
    }

    function kzCurto(v) {
        return Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1) + 'M'
             : Math.abs(v) >= 1e3 ? Math.round(v / 1e3) + 'k'
             : v;
    }

    // ── O gráfico da evolução de vendas ────────────────────────────────

    function desenharVendas(cfg) {
        const el = document.getElementById('salesChart');
        if (!el) return;

        // O Chart.js guarda-se pelo elemento e recusa desenhar duas vezes no
        // mesmo sítio: destrói-se o anterior antes de voltar.
        const anterior = Chart.getChart(el);
        if (anterior) anterior.destroy();

        const dados = lerJson('dadosVendas') || [];
        const intl = cfg.intl;

        new Chart(el, {
            type: 'line',
            data: {
                // O rotulo vem escrito do servidor — e la que se sabe se a
                // linha e um dia ou um mes inteiro. O calculo aqui fica so
                // como rede, para dados antigos sem rotulo.
                labels: dados.map(function (i) {
                    return i.rotulo || new Date(i.date).toLocaleDateString(intl, { day: '2-digit', month: 'short' });
                }),
                datasets: [{
                    label: cfg.t.vendasAoa,
                    data: dados.map(function (i) { return parseFloat(i.total); }),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 8,
                    pointBackgroundColor: 'rgb(59, 130, 246)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (c) {
                                const n = new Intl.NumberFormat(intl, {
                                    style: 'decimal', minimumFractionDigits: 2, maximumFractionDigits: 2,
                                }).format(c.parsed.y);

                                return (c.dataset.label ? c.dataset.label + ': ' : '') + n + ' AOA';
                            },
                        },
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        borderColor: 'rgba(59, 130, 246, 0.8)',
                        borderWidth: 2,
                    },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function (v) {
                                return new Intl.NumberFormat(intl, { notation: 'compact', compactDisplay: 'short' }).format(v) + ' AOA';
                            },
                        },
                        grid: { color: 'rgba(0, 0, 0, 0.05)' },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
    }

    // ── Os quatro gráficos do período ──────────────────────────────────

    function desenharPainel(cfg) {
        // Sem isto o Chart.js queixa-se de "Canvas is already in use" ao
        // mudar de período.
        desenhados.forEach(function (c) { try { c.destroy(); } catch (_) {} });
        desenhados = [];

        const d = lerJson('dadosPainel');
        if (!d || !d.estados) return;

        const intl = cfg.intl;

        const novo = function (id, conf) {
            const el = document.getElementById(id);
            if (el) desenhados.push(new Chart(el, conf));
        };

        const rosca = function (id, fonte) {
            if (!fonte || !fonte.valores || !fonte.valores.length) return;

            novo(id, {
                type: 'doughnut',
                data: { labels: fonte.rotulos, datasets: [{ data: fonte.valores, backgroundColor: fonte.cores, borderWidth: 0 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '58%',
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 10, font: { size: 10 } } },
                        tooltip: { callbacks: { label: function (c) { return ' ' + c.label + ': ' + kz(c.parsed, intl); } } },
                    },
                },
            });
        };

        rosca('pEstados', d.estados);
        rosca('pMeios', d.meiosPagamento);

        if (d.topProdutos && d.topProdutos.valores && d.topProdutos.valores.length) {
            novo('pProdutos', {
                type: 'bar',
                data: { labels: d.topProdutos.rotulos, datasets: [{ data: d.topProdutos.valores, backgroundColor: d.topProdutos.cores }] },
                options: {
                    responsive: true, maintainAspectRatio: false, indexAxis: 'y',
                    scales: { x: { beginAtZero: true, ticks: { callback: kzCurto } } },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: { label: function (c) { return ' ' + kz(c.parsed.x, intl); } } },
                    },
                },
            });
        }

        if (d.vendasCompras && d.vendasCompras.rotulos && d.vendasCompras.rotulos.length) {
            novo('pVendasCompras', {
                type: 'bar',
                data: {
                    labels: d.vendasCompras.rotulos,
                    datasets: [
                        { label: cfg.t.vendas, data: d.vendasCompras.vendas, backgroundColor: '#4f46e5' },
                        { label: cfg.t.compras, data: d.vendasCompras.compras, backgroundColor: '#ea580c' },
                    ],
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { callback: kzCurto } } },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: { callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + kz(c.parsed.y, intl); } } },
                    },
                },
            });
        }
    }

    // ── Quando desenhar ────────────────────────────────────────────────

    function desenharTudo() {
        // Só há painel se os dados lá estiverem: noutras páginas não se faz nada.
        if (!document.getElementById('dadosPainel')) return;

        const cfg = textos();

        carregarUmaVez('/vendor/js/chart.min.js')
            .then(function () {
                desenharVendas(cfg);
                desenharPainel(cfg);
            })
            .catch(function () {
                console.warn('[Painel] O Chart.js não carregou; os gráficos ficam vazios.');
            });
    }

    if (document.readyState !== 'loading') desenharTudo();
    else document.addEventListener('DOMContentLoaded', desenharTudo);

    // A barra lateral navega sem recarregar a página.
    document.addEventListener('livewire:navigated', desenharTudo);

    // Trocar de período troca o HTML: redesenhar com os dados novos.
    document.addEventListener('livewire:initialized', function () {
        window.Livewire.hook('morph.updated', function () {
            requestAnimationFrame(desenharTudo);
        });
    });

    // ── Exportar ───────────────────────────────────────────────────────

    window.exportToPDF = async function () {
        const cfg = textos();

        await carregarUmaVez('/vendor/js/jspdf.umd.min.js');
        await carregarUmaVez('/vendor/js/html2canvas.min.js');

        const jsPDF = window.jspdf.jsPDF;
        const doc = new jsPDF('p', 'mm', 'a4');

        doc.setFontSize(18);
        doc.setTextColor(59, 130, 246);
        doc.text(cfg.t.titulo, 15, 20);

        doc.setFontSize(10);
        doc.setTextColor(100);
        doc.text(String(cfg.t.geradoEm).replace(':data', new Date().toLocaleDateString(cfg.intl)), 15, 28);

        doc.setFontSize(12);
        doc.setTextColor(0);

        let y = 40;
        [
            [cfg.t.facturado, cfg.valores.facturado],
            [cfg.t.recebido, cfg.valores.recebido],
            [cfg.t.pendente, cfg.valores.pendente],
            [cfg.t.vencido, cfg.valores.vencido],
        ].forEach(function (linha) {
            doc.text(linha[0] + ': ' + linha[1] + ' AOA', 15, y);
            y += 8;
        });

        y += 7;

        const canvas = document.getElementById('salesChart');
        if (canvas) doc.addImage(canvas.toDataURL('image/png'), 'PNG', 15, y, 180, 90);

        doc.save('dashboard-faturacao.pdf');
    };

    window.exportToExcel = function () {
        const cfg = textos();
        const dados = lerJson('dadosVendas') || [];

        let csv = cfg.t.data + ',' + cfg.t.valorAoa + '\n';

        dados.forEach(function (i) {
            csv += new Date(i.date).toLocaleDateString(cfg.intl) + ',' + i.total + '\n';
        });

        csv += '\n' + cfg.t.estatisticas + '\n';
        csv += cfg.t.facturado + ',' + cfg.valores.facturadoCru + '\n';
        csv += cfg.t.recebido + ',' + cfg.valores.recebidoCru + '\n';
        csv += cfg.t.pendente + ',' + cfg.valores.pendenteCru + '\n';
        csv += cfg.t.vencido + ',' + cfg.valores.vencidoCru + '\n';

        const url = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8;' }));
        const link = document.createElement('a');

        link.setAttribute('href', url);
        link.setAttribute('download', 'dashboard-faturacao.csv');
        link.style.visibility = 'hidden';

        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);
    };
})();
