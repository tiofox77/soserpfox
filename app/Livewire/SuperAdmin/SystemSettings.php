<?php

namespace App\Livewire\SuperAdmin;

use App\Models\SystemSetting;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

#[Layout('layouts.app')]
#[Title('Configurações do Sistema')]
class SystemSettings extends Component
{
    use WithFileUploads;

    public $activeTab = 'general';
    
    // General
    public $app_name;
    public $app_description;
    public $app_version;
    public $app_url;
    public $contact_email;
    public $contact_phone;
    
    // Appearance
    public $app_logo;
    public $app_favicon;
    public $primary_color;
    public $secondary_color;
    public $current_logo;
    public $current_favicon;
    
    // SEO
    public $seo_title;
    public $seo_description;
    public $seo_keywords;
    public $seo_author;
    
    // Features
    public $enable_registration;
    public $enable_email_verification;
    public $maintenance_mode;
    
    // Social
    public $facebook_url;
    public $instagram_url;
    public $twitter_url;
    public $linkedin_url;

    public function mount()
    {
        $this->loadSettings();
    }

    public function loadSettings()
    {
        // General
        $this->app_name = SystemSetting::get('app_name', 'SOS ERP');
        $this->app_description = SystemSetting::get('app_description');
        $this->app_version = SystemSetting::get('app_version', '5.0.0');
        $this->app_url = SystemSetting::get('app_url');
        $this->contact_email = SystemSetting::get('contact_email');
        $this->contact_phone = SystemSetting::get('contact_phone');
        
        // Appearance
        $this->current_logo = SystemSetting::get('app_logo');
        $this->current_favicon = SystemSetting::get('app_favicon');
        $this->primary_color = SystemSetting::get('primary_color', '#4F46E5');
        $this->secondary_color = SystemSetting::get('secondary_color', '#06B6D4');
        
        // SEO
        $this->seo_title = SystemSetting::get('seo_title');
        $this->seo_description = SystemSetting::get('seo_description');
        $this->seo_keywords = SystemSetting::get('seo_keywords');
        $this->seo_author = SystemSetting::get('seo_author');
        
        // Features
        $this->enable_registration = SystemSetting::get('enable_registration', 'true') === 'true';
        $this->enable_email_verification = SystemSetting::get('enable_email_verification', 'false') === 'true';
        $this->maintenance_mode = SystemSetting::get('maintenance_mode', 'false') === 'true';
        
        // Social
        $this->facebook_url = SystemSetting::get('facebook_url');
        $this->instagram_url = SystemSetting::get('instagram_url');
        $this->twitter_url = SystemSetting::get('twitter_url');
        $this->linkedin_url = SystemSetting::get('linkedin_url');
    }

    public function saveGeneral()
    {
        SystemSetting::set('app_name', $this->app_name);
        SystemSetting::set('app_description', $this->app_description);
        SystemSetting::set('app_version', $this->app_version);
        SystemSetting::set('app_url', $this->app_url);
        SystemSetting::set('contact_email', $this->contact_email);
        SystemSetting::set('contact_phone', $this->contact_phone);
        
        SystemSetting::clearCache();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Configurações gerais salvas com sucesso!'
        ]);
    }

    public function saveAppearance()
    {
        // Upload logo
        if ($this->app_logo) {
            if ($this->current_logo) {
                Storage::disk('public')->delete($this->current_logo);
            }
            $logoPath = $this->app_logo->store('settings', 'public');
            SystemSetting::set('app_logo', $logoPath);
            $this->current_logo = $logoPath;
        }
        
        // Upload favicon
        if ($this->app_favicon) {
            if ($this->current_favicon) {
                Storage::disk('public')->delete($this->current_favicon);
            }
            $faviconPath = $this->app_favicon->store('settings', 'public');
            SystemSetting::set('app_favicon', $faviconPath);
            $this->current_favicon = $faviconPath;
        }
        
        SystemSetting::set('primary_color', $this->primary_color);
        SystemSetting::set('secondary_color', $this->secondary_color);
        
        SystemSetting::clearCache();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Aparência atualizada com sucesso!'
        ]);
        
        $this->app_logo = null;
        $this->app_favicon = null;
    }

    public function saveSEO()
    {
        SystemSetting::set('seo_title', $this->seo_title);
        SystemSetting::set('seo_description', $this->seo_description);
        SystemSetting::set('seo_keywords', $this->seo_keywords);
        SystemSetting::set('seo_author', $this->seo_author);
        
        SystemSetting::clearCache();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Configurações SEO salvas com sucesso!'
        ]);
    }

    public function saveFeatures()
    {
        SystemSetting::set('enable_registration', $this->enable_registration ? 'true' : 'false');
        SystemSetting::set('enable_email_verification', $this->enable_email_verification ? 'true' : 'false');
        SystemSetting::set('maintenance_mode', $this->maintenance_mode ? 'true' : 'false');
        
        SystemSetting::clearCache();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Funcionalidades atualizadas com sucesso!'
        ]);
    }

    public function saveSocial()
    {
        SystemSetting::set('facebook_url', $this->facebook_url);
        SystemSetting::set('instagram_url', $this->instagram_url);
        SystemSetting::set('twitter_url', $this->twitter_url);
        SystemSetting::set('linkedin_url', $this->linkedin_url);
        
        SystemSetting::clearCache();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Redes sociais salvas com sucesso!'
        ]);
    }

    public function render()
    {
        return view('livewire.super-admin.systemsettings');
    }
}

