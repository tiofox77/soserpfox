<?php

namespace App\Livewire\HR;

use Livewire\Component;
use App\Models\HR\HRSetting;
use App\Services\HR\DefinicoesRH;

class SettingsManagement extends Component
{
    public $categoryFilter = 'all';
    public $editingSettings = [];

    /** Quantas definições foram criadas nesta visita (avisa-se uma vez). */
    public int $criadasAgora = 0;

    public function mount()
    {
        // Criar o que falta ANTES de ler.
        //
        // É isto que faz o ecrã funcionar. O catálogo estava fixo à empresa 1,
        // pelo que todas as outras com o módulo de RH abriam esta página e não
        // viam nada — e não havia forma de o corrigir daqui, porque o ecrã só
        // sabe editar linhas que já existem.
        //
        // Só ACRESCENTA o que falta, nunca altera valores: quem baixou o INSS
        // ou mudou o subsídio não pode ver isso revertido por ter aberto o ecrã.
        $this->criadasAgora = DefinicoesRH::garantirPara(activeTenantId());

        $this->carregarValores();
    }

    /** Traz para o formulário o valor actual de cada definição. */
    private function carregarValores(): void
    {
        $this->editingSettings = HRSetting::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->pluck('value', 'key')
            ->all();
    }

    public function updatedCategoryFilter()
    {
        // Recarrega a view
    }

    // Salvar uma configuração individual
    public function saveSetting($key)
    {
        try {
            if (!array_key_exists($key, $this->editingSettings)) {
                return;
            }

            $setting = HRSetting::where('tenant_id', activeTenantId())
                ->where('key', $key)
                ->first();

            if ($setting) {
                $value = $this->editingSettings[$key];

                // Validar o valor baseado nas regras
                if ($setting->validation_rules) {
                    $this->validate([
                        "editingSettings.{$key}" => $setting->validation_rules,
                    ], [
                        "editingSettings.{$key}.required" => 'Este campo é obrigatório.',
                        "editingSettings.{$key}.numeric" => 'O valor deve ser numérico.',
                        "editingSettings.{$key}.min" => 'O valor está abaixo do mínimo permitido.',
                        "editingSettings.{$key}.max" => 'O valor está acima do máximo permitido.',
                    ]);
                }

                // Converter valores booleanos
                if ($setting->value_type === 'boolean') {
                    $value = $value ? '1' : '0';
                }

                $setting->update(['value' => $value]);
                HRSetting::clearCache($key);
                
                // Atualizar o valor no array de edição
                $this->editingSettings[$key] = $value;

                $this->dispatch('setting-saved', key: $key);
                $this->dispatch('notify', message: "✓ {$setting->label} salvo!");
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Pegar primeira mensagem de erro (mais limpa)
            $errors = $e->validator->errors()->all();
            $firstError = $errors[0] ?? 'Erro de validação';
            $this->dispatch('notify', type: 'error', message: $firstError);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao salvar: ' . $e->getMessage());
        }
    }

    public function save()
    {
        try {
            $count = 0;
            foreach ($this->editingSettings as $key => $value) {
                $setting = HRSetting::where('tenant_id', activeTenantId())
                    ->where('key', $key)
                    ->first();

                if ($setting) {
                    // Validar o valor baseado nas regras
                    if ($setting->validation_rules) {
                        $this->validate([
                            "editingSettings.{$key}" => $setting->validation_rules,
                        ]);
                    }

                    // Converter valores booleanos
                    if ($setting->value_type === 'boolean') {
                        $value = $value ? '1' : '0';
                    }

                    $setting->update(['value' => $value]);
                    $count++;
                }
            }

            HRSetting::clearCache();

            // Recarregar e NÃO limpar. O `editingSettings = []` que aqui estava
            // esvaziava o formulário: gravava-se tudo e os sessenta campos
            // ficavam em branco no ecrã, o que se lê como "apagou as minhas
            // configurações". Os valores estão gravados — o que faltava era
            // voltar a lê-los.
            $this->carregarValores();

            $this->dispatch('notify', type: 'success', message: "{$count} configuração(ões) salva(s) com sucesso!");
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Pegar primeira mensagem de erro (mais limpa)
            $errors = $e->validator->errors()->all();
            $firstError = $errors[0] ?? 'Erro de validação';
            $this->dispatch('notify', type: 'error', message: $firstError);
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao salvar: ' . $e->getMessage());
        }
    }

    public function resetToDefaults()
    {
        try {
            $settings = HRSetting::where('tenant_id', activeTenantId())->get();

            foreach ($settings as $setting) {
                $setting->update(['value' => $setting->default_value]);
            }

            HRSetting::clearCache();
            $this->carregarValores();

            $this->dispatch('notify', type: 'success', message: 'Configurações restauradas para os valores padrão!');
        } catch (\Exception $e) {
            $this->dispatch('notify', type: 'error', message: 'Erro ao restaurar: ' . $e->getMessage());
        }
    }

    /*
     * NOTA sobre permissões, deixada de propósito.
     *
     * Este ecrã não verifica nenhuma: quem tiver acesso ao módulo de RH pode
     * alterar a taxa de INSS, os limites de isenção e o multiplicador de horas
     * extra — valores que decidem quanto cada empregado recebe e quanto a
     * empresa entrega ao Estado.
     *
     * Cheguei a pôr aqui um `hr.settings.edit`, e tirei-o: não existe UMA
     * permissão `hr.*` na base, e nenhum dos catorze ecrãs de RH verifica seja
     * o que for. Impor uma permissão inexistente dava 403 a toda a gente,
     * incluindo a quem precisa do ecrã — trocar "não mostra nada" por "não
     * deixa entrar" não é melhorar.
     *
     * Fica por fazer, e é trabalho do módulo inteiro: definir as permissões de
     * RH, semeá-las e atribuí-las aos papéis. A quem dar cada uma é decisão de
     * quem gere a empresa, não minha.
     */

    public function render()
    {
        $query = HRSetting::where('tenant_id', activeTenantId())
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('display_order');

        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        $settings = $query->get()->groupBy('category');

        return view('livewire.hr.settings.settings', [
            'settings' => $settings
        ])->layout('layouts.app', ['title' => 'Configurações RH']);
    }
}
