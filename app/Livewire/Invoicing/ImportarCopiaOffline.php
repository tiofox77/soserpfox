<?php

namespace App\Livewire\Invoicing;

use App\Services\POS\ImportacaoDeCopiaOffline;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Recupera uma cópia de segurança exportada do PWA.
 *
 * O caminho normal é o dispositivo sincronizar sozinho. Isto é para quando
 * não sincronizou e já não vai: o telemóvel partiu-se, o navegador limpou os
 * dados do site, alguém carregou em "Reiniciar tudo" antes de enviar. As
 * vendas que lá estavam já aconteceram — dinheiro trocado, talão entregue — e
 * sem o ficheiro não há de onde as tirar.
 *
 * Em dois passos de propósito: primeiro mostra-se o que está no ficheiro,
 * depois é que se importa. Quem recupera dados está normalmente com pressa e
 * a fazê-lo pela primeira vez, e não pode ser o clique a decidir.
 */
#[Layout('layouts.app')]
#[Title('Importar Cópia Offline')]
class ImportarCopiaOffline extends Component
{
    use WithFileUploads;

    public $ficheiro;

    public ?array $inventario = null;
    public ?string $erro = null;
    public ?array $resultado = null;
    public bool $aImportar = false;

    private ?array $copia = null;

    protected function rules(): array
    {
        return [
            // 20 MB: uma fila de milhares de vendas com linhas cabe bem
            // abaixo disto, e o limite existe para travar um ficheiro trocado.
            'ficheiro' => 'required|file|max:20480',
        ];
    }

    public function updatedFicheiro(): void
    {
        $this->reset(['inventario', 'erro', 'resultado']);
        $this->analisar();
    }

    /** Lê o ficheiro e mostra o que lá está, sem gravar nada. */
    private function analisar(): void
    {
        $copia = $this->lerFicheiro();

        if (!$copia) {
            return;
        }

        $servico = app(ImportacaoDeCopiaOffline::class);

        if ($problema = $servico->validar($copia, activeTenantId())) {
            $this->erro = $problema;

            return;
        }

        $this->inventario = $servico->inventario($copia);
    }

    /**
     * O ficheiro é relido a cada passo em vez de ficar numa propriedade.
     *
     * Uma propriedade pública do Livewire viaja no payload de CADA pedido, ida
     * e volta — um ficheiro de alguns MB tornava o ecrã inutilizável. E uma
     * propriedade privada não sobrevive entre pedidos.
     */
    private function lerFicheiro(): ?array
    {
        try {
            $this->validate();
        } catch (\Throwable $e) {
            $this->erro = 'Escolha um ficheiro .json com menos de 20 MB.';

            return null;
        }

        $bruto = file_get_contents($this->ficheiro->getRealPath());
        $copia = json_decode($bruto, true);

        if (!is_array($copia)) {
            $this->erro = 'O ficheiro não é um JSON válido. Não foi alterado à mão, pois não?';

            return null;
        }

        return $copia;
    }

    public function importar(): void
    {
        abort_unless(
            auth()->user()?->can('invoicing.pos.sell') || auth()->user()?->isSuperAdmin(),
            403,
            'Sem permissão para importar vendas.'
        );

        $this->erro = null;
        $this->aImportar = true;

        try {
            $copia = $this->lerFicheiro();

            if (!$copia) {
                return;
            }

            $servico = app(ImportacaoDeCopiaOffline::class);

            if ($problema = $servico->validar($copia, activeTenantId())) {
                $this->erro = $problema;

                return;
            }

            $this->resultado = $servico->importar($copia, activeTenantId(), auth()->id());
            $this->inventario = null;

            $this->dispatch('success', message: sprintf(
                '%d venda(s) importada(s), %d já existiam.',
                $this->resultado['importadas'],
                $this->resultado['ja_existiam']
            ));
        } catch (\Throwable $e) {
            $this->erro = 'A importação falhou: ' . $e->getMessage();

            \Log::error('ImportarCopiaOffline', ['error' => $e->getMessage()]);
        } finally {
            $this->aImportar = false;
        }
    }

    public function render()
    {
        return view('livewire.invoicing.importar-copia-offline');
    }
}
