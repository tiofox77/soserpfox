<?php

namespace App\Livewire\Invoicing;

use App\Services\POS\LeituraDeCopiaOffline;
use DomainException;
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
 * vendas que lá estavam já aconteceram — dinheiro trocado, talão entregue.
 *
 * Em dois passos de propósito: primeiro mostra-se o que está no ficheiro,
 * depois é que se importa. A leitura e a importação vivem na
 * `LeituraDeCopiaOffline`, partilhada com o ecrã em React.
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

    public function updatedFicheiro(): void
    {
        $this->reset(['inventario', 'erro', 'resultado']);

        try {
            $this->inventario = app(LeituraDeCopiaOffline::class)->analisar($this->ficheiro, (int) activeTenantId());
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();
        }
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
            $this->resultado = app(LeituraDeCopiaOffline::class)->importar($this->ficheiro, (int) activeTenantId(), (int) auth()->id());
            $this->inventario = null;
            $this->dispatch('success', message: sprintf(
                '%d venda(s) importada(s), %d já existiam.',
                $this->resultado['importadas'],
                $this->resultado['ja_existiam']
            ));
        } catch (DomainException $e) {
            $this->erro = $e->getMessage();
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
