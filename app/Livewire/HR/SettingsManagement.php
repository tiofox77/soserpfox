<?php

namespace App\Livewire\HR;

use Livewire\Component;
use App\Models\HR\HRSetting;

class SettingsManagement extends Component
{
    public $settings = [];
    public $categoryFilter = 'all';
    public $editingSettings = [];

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        $query = HRSetting::where('tenant_id', tenant('id'))
            ->where('is_active', true)
            ->orderBy('category')
            ->orderBy('display_order');

        if ($this->categoryFilter !== 'all') {
            $query->where('category', $this->categoryFilter);
        }

        $this->settings = $query->get()->groupBy('category');
    }

    public function updatedCategoryFilter()
    {
        $this->loadSettings();
    }

    public function save()
    {
        try {
            foreach ($this->editingSettings as $key => $value) {
                $setting = HRSetting::where('tenant_id', tenant('id'))
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
                }
            }

            HRSetting::clearCache();
            session()->flash('success', 'Configurações salvas com sucesso!');
            $this->loadSettings();
        } catch (\Illuminate\Validation\ValidationException $e) {
            session()->flash('error', 'Erro de validação: ' . implode(', ', $e->validator->errors()->all()));
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao salvar configurações: ' . $e->getMessage());
        }
    }

    public function resetToDefaults()
    {
        try {
            $settings = HRSetting::where('tenant_id', tenant('id'))->get();
            
            foreach ($settings as $setting) {
                $setting->update(['value' => $setting->default_value]);
            }

            HRSetting::clearCache();
            session()->flash('success', 'Configurações restauradas para os valores padrão!');
            $this->loadSettings();
            $this->editingSettings = [];
        } catch (\Exception $e) {
            session()->flash('error', 'Erro ao restaurar configurações: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.hr.settings.settings')
            ->layout('layouts.app', ['title' => 'Configurações RH']);
    }
}
