/**
 * PDF a partir do que está no ecrã — feito no browser, sem servidor.
 *
 * PORQUE EXISTE. O PDF do servidor é desenhado pelo DomPDF, que não sabe
 * flexbox. Por isso os modelos trazem uma camada de correcções só para ele, e
 * o papel nunca é exactamente a pré-visualização. A forma de não haver dois
 * desenhos é não desenhar duas vezes: fotografa-se a própria pré-visualização,
 * que já é o desenho aprovado, e embrulha-se num PDF.
 *
 * É a mesma mecânica que o PWA usa sem rede. A diferença é que aqui as
 * bibliotecas só se carregam quando alguém carrega no botão — são 560 KB que
 * não têm de pesar em todas as páginas.
 *
 * LIMITE HONESTO: o PDF sai em imagem. Não tem texto para copiar nem para
 * pesquisar. Para quem precisa disso, o PDF do servidor continua onde estava,
 * e a pré-visualização nunca sai do sítio.
 */
(function () {
    'use strict';

    const A4_LARGURA_MM = 210;
    const A4_ALTURA_MM = 297;
    const PX_POR_MM = 96 / 25.4;

    const BIBLIOTECAS = [
        '/vendor/js/html2canvas.min.js',
        '/vendor/js/jspdf.umd.min.js',
    ];

    let aCarregar = null;

    function carregarScript(src) {
        return new Promise(function (resolve, reject) {
            const existente = document.querySelector('script[data-pdf-lib="' + src + '"]');
            if (existente) {
                if (existente.dataset.pronto) {
                    resolve();
                } else {
                    existente.addEventListener('load', function () { resolve(); }, { once: true });
                    existente.addEventListener('error', function () { reject(new Error(src)); }, { once: true });
                }
                return;
            }

            const s = document.createElement('script');
            s.src = src;
            s.dataset.pdfLib = src;
            s.addEventListener('load', function () { s.dataset.pronto = '1'; resolve(); }, { once: true });
            s.addEventListener('error', function () { reject(new Error('Nao foi possivel carregar ' + src)); }, { once: true });
            document.head.appendChild(s);
        });
    }

    /** As bibliotecas so descem a primeira utilizacao. */
    async function garantirBibliotecas() {
        if (window.html2canvas && window.jspdf && window.jspdf.jsPDF) return;

        if (!aCarregar) {
            aCarregar = Promise.all(BIBLIOTECAS.map(carregarScript));
        }

        try {
            await aCarregar;
        } catch (erro) {
            aCarregar = null;
            throw erro;
        }

        if (!window.html2canvas || !window.jspdf || !window.jspdf.jsPDF) {
            aCarregar = null;
            throw new Error('O gerador de PDF nao ficou disponivel.');
        }
    }

    /**
     * O logotipo e o QR sao imagens: fotografar antes de estarem carregadas
     * da um documento com buracos brancos.
     */
    function esperarImagens(doc, limiteMs) {
        const imagens = Array.prototype.slice.call(doc.images || []);

        const todas = Promise.all(imagens.map(function (img) {
            if (img.complete) return Promise.resolve();

            return new Promise(function (r) {
                img.addEventListener('load', r, { once: true });
                img.addEventListener('error', r, { once: true });
            });
        }));

        return Promise.race([todas, new Promise(function (r) { setTimeout(r, limiteMs || 4000); })]);
    }

    /**
     * A imagem do documento cortada em folhas A4.
     *
     * A TOLERANCIA NAO E UM ENFEITE. A altura da fatia e arredondada para
     * baixo, por isso um documento que ocupa exactamente uma folha fica um ou
     * dois pixeis acima do corte e gera uma segunda pagina em branco. Um
     * milimetro de folga resolve, e nao chega para esconder conteudo real.
     */
    function pdfDeCanvas(canvas) {
        const jsPDF = window.jspdf.jsPDF;
        const pdf = new jsPDF({ unit: 'mm', format: 'a4', orientation: 'portrait' });

        const alturaFatiaPx = Math.floor(canvas.width * A4_ALTURA_MM / A4_LARGURA_MM);
        const tolerancia = Math.max(2, Math.ceil(canvas.width * 0.004));

        let primeira = true;

        for (let topo = 0; topo + tolerancia < canvas.height; topo += alturaFatiaPx) {
            const altura = Math.min(alturaFatiaPx, canvas.height - topo);

            const fatia = document.createElement('canvas');
            fatia.width = canvas.width;
            fatia.height = altura;
            fatia.getContext('2d').drawImage(canvas, 0, topo, canvas.width, altura, 0, 0, canvas.width, altura);

            if (!primeira) pdf.addPage();

            pdf.addImage(
                fatia.toDataURL('image/jpeg', 0.92),
                'JPEG',
                0, 0,
                A4_LARGURA_MM,
                altura * A4_LARGURA_MM / canvas.width
            );

            primeira = false;
        }

        return pdf;
    }

    /**
     * A altura e a do DOCUMENTO, nao a da pagina que o contem.
     *
     * Medir documentElement.scrollHeight traz as margens do corpo e o fundo
     * cinzento a volta da folha, e isso empurra conteudo para uma pagina a mais.
     */
    function fotografar(elemento, escala) {
        const larguraPx = Math.round(elemento.getBoundingClientRect().width)
            || Math.round(A4_LARGURA_MM * PX_POR_MM);
        const alturaPx = Math.max(elemento.scrollHeight, 1);

        return window.html2canvas(elemento, {
            scale: escala || 2,
            useCORS: true,
            backgroundColor: '#ffffff',
            logging: false,
            width: larguraPx,
            height: alturaPx,
            windowWidth: larguraPx,
            windowHeight: alturaPx,

            // Num relatorio fotografa-se o ecra inteiro, e o ecra tem botoes.
            // Quem marcar um elemento com data-pdf-fora fica de fora do papel:
            // barras de accoes, filtros, paginacao.
            ignoreElements: function (el) {
                return el.nodeType === 1 && el.hasAttribute && el.hasAttribute('data-pdf-fora');
            },
        });
    }

    /**
     * Guarda e devolve o PDF.
     *
     * `guardar: false` faz o PDF sem o descarregar. Serve aos ensaios, que
     * precisam de medir paginas e tamanho sem abrir a caixa de gravacao, e
     * deixa a porta aberta a quem um dia queira anexar em vez de descarregar.
     */
    function guardar(pdf, nome, opcoes) {
        if (!opcoes || opcoes.guardar !== false) {
            pdf.save(nomeSeguro(nome));
        }

        return pdf;
    }

    function nomeSeguro(nome) {
        const limpo = String(nome || 'documento')
            .replace(/[\\/:*?"<>|]+/g, '-')
            .replace(/\s+/g, '_')
            .replace(/\.pdf$/i, '');

        return (limpo || 'documento') + '.pdf';
    }

    /**
     * O PDF de uma pre-visualizacao, pelo seu endereco.
     *
     * Vai buscar o MESMO HTML que abre no separador novo, poe-no numa moldura
     * escondida a largura de uma folha, e fotografa a caixa do documento.
     */
    async function daPreVisualizacao(url, nomeFicheiro, opcoes) {
        const o = Object.assign({ escala: 2, seletor: '.page-wrapper' }, opcoes || {});
        await garantirBibliotecas();

        const resposta = await fetch(url, {
            credentials: 'same-origin',
            headers: { 'Accept': 'text/html' },
        });

        if (!resposta.ok) {
            throw new Error('A pre-visualizacao respondeu ' + resposta.status + '.');
        }

        const html = await resposta.text();
        const larguraPx = Math.round(A4_LARGURA_MM * PX_POR_MM);

        const moldura = document.createElement('iframe');
        moldura.setAttribute('aria-hidden', 'true');
        moldura.style.cssText = 'position:fixed;left:-10000px;top:0;width:' + larguraPx
            + 'px;height:10px;border:0;opacity:0;pointer-events:none';
        document.body.appendChild(moldura);

        try {
            const doc = moldura.contentDocument;
            doc.open();
            doc.write(html);
            doc.close();

            /*
             * A BARRA DE DESLOCAMENTO ROUBAVA 15 PIXEIS À FOLHA.
             *
             * A moldura nasce com 10 px de altura e o documento tem 1123: o
             * browser mete logo uma barra vertical, e a caixa do documento
             * passa a medir 779 px em vez dos 794 que são 210 mm. A altura,
             * essa, continua a ser a de uma folha inteira. A proporção deixa
             * de ser A4, a fatia da página fica mais curta do que o conteúdo,
             * e um documento de duas linhas saía em DUAS páginas.
             *
             * Por isso: sem barra, e a moldura cresce ANTES de se medir.
             */
            const semBarra = doc.createElement('style');
            semBarra.textContent = 'html{scrollbar-width:none}html::-webkit-scrollbar{display:none}';
            (doc.head || doc.documentElement).appendChild(semBarra);
            moldura.style.height = '4000px';

            await esperarImagens(doc, 4000);

            const alvo = doc.querySelector(o.seletor) || doc.body;
            moldura.style.height = Math.max(alvo.scrollHeight, 1) + 'px';

            // Uma volta para o desenho assentar depois de a moldura crescer.
            await new Promise(function (r) {
                requestAnimationFrame(function () { requestAnimationFrame(r); });
            });

            const canvas = await fotografar(alvo, o.escala);

            // O nome sai do TITULO da propria pre-visualizacao — "Fatura de
            // Venda FT A/000035". Assim nenhum ecra tem de saber como se chama
            // o numero em cada tipo de documento.
            return guardar(pdfDeCanvas(canvas), nomeFicheiro || doc.title, o);
        } finally {
            moldura.remove();
        }
    }

    /**
     * O PDF de um pedaco da pagina actual — um relatorio, um resumo de turno.
     * Fotografa-se o elemento onde ele esta, com o desenho que tem no ecra.
     */
    async function doElemento(elemento, nomeFicheiro, opcoes) {
        const o = Object.assign({ escala: 2 }, opcoes || {});
        await garantirBibliotecas();

        const alvo = typeof elemento === 'string' ? document.querySelector(elemento) : elemento;
        if (!alvo) throw new Error('Nao encontrei o que fotografar na pagina.');

        await esperarImagens(document, 2000);

        const canvas = await fotografar(alvo, o.escala);

        return guardar(pdfDeCanvas(canvas), nomeFicheiro, o);
    }

    // ─────────────────────────────────────────────────────────────────
    // O BOTAO
    // ─────────────────────────────────────────────────────────────────

    function avisar(mensagem, erro) {
        if (window.toastr) {
            erro ? window.toastr.error(mensagem) : window.toastr.success(mensagem);
            return;
        }
        if (erro) window.alert(mensagem);
    }

    /**
     * Enquanto trabalha, o botao diz que esta a trabalhar.
     *
     * Fotografar uma folha A4 leva perto de um quinto de segundo, mas numa
     * maquina do balcao pode levar mais. Sem sinal nenhum, quem carrega
     * carrega outra vez, e sai o mesmo ficheiro duas vezes.
     */
    function ocupar(botao) {
        const icone = botao.querySelector('i');
        const original = icone ? icone.className : null;

        botao.dataset.aTrabalhar = '1';
        botao.setAttribute('aria-busy', 'true');
        botao.style.pointerEvents = 'none';
        botao.style.opacity = '0.6';
        if (icone) icone.className = 'fas fa-spinner fa-spin';

        return function () {
            delete botao.dataset.aTrabalhar;
            botao.removeAttribute('aria-busy');
            botao.style.pointerEvents = '';
            botao.style.opacity = '';
            if (icone && original) icone.className = original;
        };
    }

    /**
     * Delegacao no documento, de proposito.
     *
     * As listas sao Livewire: cada filtro ou mudanca de pagina troca as linhas
     * da tabela por outras. Um ouvinte preso a cada botao morria nessa troca;
     * um ouvinte no documento sobrevive a todas.
     */
    document.addEventListener('click', function (evento) {
        const botao = evento.target.closest('[data-pdf-preview], [data-pdf-elemento]');
        if (!botao || botao.dataset.aTrabalhar) return;

        evento.preventDefault();

        const libertar = ocupar(botao);
        const nome = botao.dataset.pdfNome || null;

        const trabalho = botao.dataset.pdfPreview
            ? daPreVisualizacao(botao.dataset.pdfPreview, nome)
            : doElemento(botao.dataset.pdfElemento, nome || document.title);

        trabalho
            .catch(function (erro) {
                console.error('[PDF]', erro);
                avisar(botao.dataset.pdfErro || 'Nao foi possivel gerar o PDF.', true);
            })
            .finally(libertar);
    });

    window.PdfDoDocumento = {
        garantirBibliotecas: garantirBibliotecas,
        daPreVisualizacao: daPreVisualizacao,
        doElemento: doElemento,
        nomeSeguro: nomeSeguro,
    };
})();
