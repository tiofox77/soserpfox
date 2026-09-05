/**
 * O turno de caixa do PWA — uma definição, dois ecrãs.
 *
 * PORQUE EXISTE. O turno vivia todo dentro do POS de balcão: o crachá no topo,
 * os dois modais, a aritmética do esperado em caixa. O restaurante ficou sem
 * nada disso — só com um aviso vermelho a mandar o empregado ir ao POS abrir
 * o turno e voltar. Numa sala cheia isso é uma viagem que ninguém faz, e o
 * resultado era pior do que parece: as comandas eram tiradas na mesma, o
 * servidor recusava-as por falta de turno, e a comida já tinha saído.
 *
 * Duplicar o bloco no restaurante resolvia hoje e estragava daqui a três
 * meses — duas cópias da mesma regra de caixa acabam sempre a discordar, e a
 * caixa é precisamente onde não se pode discordar. Por isso mudou-se de sítio
 * em vez de se copiar.
 *
 * COMO SE USA. No Alpine do ecrã:
 *
 *     posRestaurante() {
 *         return { ...TurnoDoPwa(), ... o resto do ecrã ... }
 *     }
 *
 * e no Blade `@include('partials.pwa-turno')` para os modais.
 *
 * Depois, `iniciarTurno()` no arranque do ecrã.
 *
 * OS NOMES SÃO OS QUE O POS JÁ USAVA (`shift`, `showShiftOpen`,
 * `confirmCloseShift`, …), de propósito: assim o markup do POS não mexeu uma
 * linha ao passar a usar isto. Os dados próprios levam prefixo `_turno` para
 * não colidirem com o que o ecrã anfitrião já tem.
 */
window.TurnoDoPwa = function () {
    return {
        shift: { open: false },
        shiftBusy: false,
        showShiftOpen: false,
        showShiftClose: false,
        shiftOpenForm: { opening_balance: '', opening_notes: '' },
        shiftCloseForm: { actual_cash: '', closing_notes: '' },

        // Dados próprios. O ecrã anfitrião pode ter os seus (o POS tem, para o
        // gaveto de pendentes); estes são os do turno e não dependem disso.
        _turnoVendas: [],
        _turnoPendentes: 0,
        _turnoEmpresa: {},
        _turnoUltimoSync: null,

        async iniciarTurno() {
            this.shift = (await window.SosPwa.getShift()) || { open: false };
            this._turnoEmpresa = (await window.SosPwa.getCompany()) || {};
            await this.refrescarTurno();

            // O turno muda por baixo dos pés: outro aparelho fecha-o, ou a
            // sincronização traz o número real de um turno aberto offline.
            window.addEventListener('pwa:synced', async () => {
                this.shift = (await window.SosPwa.getShift()) || { open: false };
                await this.refrescarTurno();
            });
        },

        async refrescarTurno() {
            this._turnoVendas = await window.SosPwa.getPosSales();
            this._turnoPendentes = this._turnoVendas.filter((v) => !v._synced).length;
            this._turnoUltimoSync = await window.SosPwa.getLastSyncDate();
        },

        /** O dinheiro das vendas feitas sem rede desde que o turno abriu. */
        get localCashSinceOpen() {
            if (!this.shift.open) {
                return 0;
            }

            const abertura = this.shift.opened_at ? new Date(this.shift.opened_at).getTime() : 0;

            return this._turnoVendas
                .filter((v) => !v._synced && v.payment_method === 'cash' && new Date(v.created_at).getTime() >= abertura)
                .reduce((soma, v) => soma + (parseFloat(v.total) || 0), 0);
        },

        get expectedCash() {
            return (parseFloat(this.shift.opening_balance) || 0)
                + (parseFloat(this.shift.cash_sales) || 0)
                + this.localCashSinceOpen;
        },

        get closeDifference() {
            const contado = parseFloat(this.shiftCloseForm.actual_cash);

            if (isNaN(contado)) {
                return 0;
            }

            return Math.round((contado - this.expectedCash) * 100) / 100;
        },

        /** Quantos documentos estão por subir — o fecho depende disto. */
        get turnoPendentes() {
            return this._turnoPendentes;
        },

        get turnoOnline() {
            return navigator.onLine;
        },

        get turnoUltimoSyncLabel() {
            if (!this._turnoUltimoSync) {
                return __('Nunca sincronizado');
            }

            const d = new Date(this._turnoUltimoSync);

            return d.toLocaleString('pt-PT', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
        },

        get turnoUltimoSync() {
            return this._turnoUltimoSync;
        },

        manageShift() {
            if (this.shift.open) {
                this.closeShift();
            } else {
                this.openOpenShiftModal();
            }
        },

        openOpenShiftModal() {
            this.shiftOpenForm = { opening_balance: '', opening_notes: '' };
            this.showShiftOpen = true;
        },

        async confirmOpenShift() {
            if (this.shiftBusy) {
                return;
            }

            const saldo = parseFloat(this.shiftOpenForm.opening_balance);

            if (isNaN(saldo) || saldo < 0) {
                alert(__('Informe o saldo inicial (0 ou mais).'));

                return;
            }

            this.shiftBusy = true;

            try {
                this.shift = await window.SosPwa.openShiftOffline({
                    opening_balance: saldo,
                    opening_notes: this.shiftOpenForm.opening_notes.trim() || null,
                });
                this.showShiftOpen = false;

                // O ecrã que abriu o turno pode ter coisas a reagir a isso —
                // as mesas do restaurante deixam de estar trancadas.
                window.dispatchEvent(new CustomEvent('pwa:turno-mudou', { detail: this.shift }));
            } catch (err) {
                console.error(err);
                alert(__('Erro ao abrir turno: :erro', { erro: err.message }));
            } finally {
                this.shiftBusy = false;
            }
        },

        async closeShift() {
            // Tenta subir o que está pendente antes de mostrar o modal: o
            // esperado em caixa fica mais perto da verdade.
            if (this._turnoPendentes > 0 && navigator.onLine) {
                window.SosPwa.sync(true).then(() => this.refrescarTurno());
            }

            await this.refrescarTurno();
            this.shiftCloseForm = { actual_cash: this.expectedCash.toFixed(2), closing_notes: '' };
            this.showShiftClose = true;
        },

        async confirmCloseShift() {
            if (this.shiftBusy) {
                return;
            }

            const contado = parseFloat(this.shiftCloseForm.actual_cash);

            if (isNaN(contado) || contado < 0) {
                alert(__('Informe o dinheiro contado em caixa (0 ou mais).'));

                return;
            }

            this.shiftBusy = true;
            const retrato = { ...this.shift };

            try {
                await window.SosPwa.closeShiftOffline({
                    actual_cash: contado,
                    closing_notes: this.shiftCloseForm.closing_notes.trim() || null,
                    // NÃO traduzir: isto é gravado no fecho de turno do servidor e sai
                    // nos relatórios de caixa. Texto de dados, não de interface.
                    difference_reason: this.closeDifference !== 0
                        ? ('Diferença apurada no fecho offline: ' + this.closeDifference.toFixed(2) + ' Kz')
                        : null,
                });

                this.shift = await window.SosPwa.getShift();
                this.showShiftClose = false;
                await this.refrescarTurno();

                window.dispatchEvent(new CustomEvent('pwa:turno-mudou', { detail: this.shift }));

                // Relatório de fecho (X/Z) — imprime offline.
                try {
                    window.PosOfflineTicket.printShiftReport(retrato, this._turnoVendas, this._turnoEmpresa);
                } catch (re) {
                    console.warn('[PWA] Erro ao imprimir relatório de fecho:', re);
                }

                if (!navigator.onLine || this._turnoPendentes > 0) {
                    // Duas frases, duas cadeias: o \n é pontuação, não texto.
                    alert(__('Fecho registado.') + '\n' + __('Será efetivado no servidor assim que todas as vendas sincronizarem.'));
                }
            } catch (err) {
                console.error(err);
                alert(__('Erro ao fechar turno: :erro', { erro: err.message }));
            } finally {
                this.shiftBusy = false;
            }
        },
    };
};
