<?php

namespace App\Livewire\HR;

use Livewire\Component;
use App\Models\HR\HRSetting;

class SettingsManagement extends Component
{
    public $categoryFilter = 'all';
    public $editingSettings = [];

    public function mount()
    {
        // Pré-carregar valores atuais para o wire:model funcionar
        $settings = HRSetting::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_active', true)
            ->get();

        foreach ($settings as $setting) {
            $this->editingSettings[$setting->key] = $setting->value;
        }
    }

    public function updatedCategoryFilter()
    {
        // Recarrega a view
    }

    // Salvar uma configuração individual
    public function saveSetting($key)
    {
        try {
            if (!isset($this->editingSettings[$key])) {
                return;
            }

            $setting = HRSetting::where('tenant_id', auth()->user()->tenant_id)
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
            $this->dispatch('notify', message: "✗ Erro: " . implode(', ', $e->validator->errors()->all()));
            throw $e;
        } catch (\Exception $e) {
            $this->dispatch('notify', message: "✗ Erro ao salvar: " . $e->getMessage());
        }
    }

    public function save()
    {
        try {
            $count = 0;
            foreach ($this->editingSettings as $key => $value) {
                $setting = HRSetting::where('tenant_id', auth()->user()->tenant_id)
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
            $this->editingSettings = [];
            session()->flash('success', "{$count} configuração(ões) salva(s) com sucesso!");
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Erro de validação: ' . implode(', ', $e->validator->errors()->all()));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao salvar configurações: ' . $e->getMessage());
        }
    }

    public function resetToDefaults()
    {
        try {
            $settings = HRSetting::where('tenant_id', auth()->user()->tenant_id)->get();
            
            foreach ($settings as $setting) {
                $setting->update(['value' => $setting->default_value]);
            }

            HRSetting::clearCache();
            
            // Recarregar valores
            $this->editingSettings = [];
            foreach ($settings as $setting) {
                $setting->refresh();
                $this->editingSettings[$setting->key] = $setting->value;
            }
            
            session()->flash('success', 'Configurações restauradas para os valores padrão!');
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao restaurar configurações: ' . $e->getMessage());
        }
    }

    public function render()
    {
        $query = HRSetting::where('tenant_id', auth()->user()->tenant_id)
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
