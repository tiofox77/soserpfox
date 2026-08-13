<?php

namespace App\Livewire\SuperAdmin;

use App\Models\SystemSetting;
use Livewire\Component;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Storage;

#[Layout('layouts.superadmin')]
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
    public $seo_canonical_url;
    public $seo_robots;
    public $seo_og_image;
    public $current_og_image;
    public $google_analytics_id;
    public $gtm_id;
    public $facebook_pixel_id;
    public $google_site_verification;
    public $bing_site_verification;
    
    // Features
    public $enable_registration;
    public $enable_email_verification;
    public $maintenance_mode;
    
    // Social
    public $facebook_url;
    public $instagram_url;
    public $twitter_url;
    public $linkedin_url;
    
    // Schema.org
    public $schema_app_name;
    public $schema_app_description;
    public $schema_app_url;
    public $schema_app_category;
    public $schema_price;
    public $schema_currency;
    public $schema_region;
    public $schema_rating_value;
    public $schema_review_count;
    public $schema_creator_name;
    public $schema_creator_url;

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
        $this->seo_canonical_url = SystemSetting::get('seo_canonical_url');
        $this->seo_robots = SystemSetting::get('seo_robots', 'index, follow');
        $this->current_og_image = SystemSetting::get('seo_og_image');
        $this->google_analytics_id = SystemSetting::get('google_analytics_id');
        $this->gtm_id = SystemSetting::get('gtm_id');
        $this->facebook_pixel_id = SystemSetting::get('facebook_pixel_id');
        $this->google_site_verification = SystemSetting::get('google_site_verification');
        $this->bing_site_verification = SystemSetting::get('bing_site_verification');
        
        // Features
        $this->enable_registration = SystemSetting::get('enable_registration', 'true') === 'true';
        $this->enable_email_verification = SystemSetting::get('enable_email_verification', 'false') === 'true';
        $this->maintenance_mode = SystemSetting::get('maintenance_mode', 'false') === 'true';
        
        // Social
        $this->facebook_url = SystemSetting::get('facebook_url');
        $this->instagram_url = SystemSetting::get('instagram_url');
        $this->twitter_url = SystemSetting::get('twitter_url');
        $this->linkedin_url = SystemSetting::get('linkedin_url');
        
        // Schema.org
        $this->schema_app_name = SystemSetting::get('schema_app_name', 'SOSERP');
        $this->schema_app_description = SystemSetting::get('schema_app_description');
        $this->schema_app_url = SystemSetting::get('schema_app_url', 'https://soserp.vip');
        $this->schema_app_category = SystemSetting::get('schema_app_category', 'BusinessApplication');
        $this->schema_price = SystemSetting::get('schema_price', '0');
        $this->schema_currency = SystemSetting::get('schema_currency', 'AOA');
        $this->schema_region = SystemSetting::get('schema_region', 'Angola');
        // Sem omissão: com 4,8 e 150 pré-preenchidos, qualquer gravação desta
        // página voltava a plantar uma avaliação que ninguém recolheu — e a
        // landing publicava-a em Schema.org, que é o que o Google transforma em
        // estrelas no resultado de pesquisa. Em branco, a landing não a declara.
        $this->schema_rating_value = SystemSetting::get('schema_rating_value');
        $this->schema_review_count = SystemSetting::get('schema_review_count');
        $this->schema_creator_name = SystemSetting::get('schema_creator_name', 'SOSERP');
        $this->schema_creator_url = SystemSetting::get('schema_creator_url', 'https://soserp.vip');
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
        SystemSetting::set('seo_canonical_url', $this->seo_canonical_url);
        SystemSetting::set('seo_robots', $this->seo_robots);
        SystemSetting::set('google_analytics_id', $this->google_analytics_id);
        SystemSetting::set('gtm_id', $this->gtm_id);
        SystemSetting::set('facebook_pixel_id', $this->facebook_pixel_id);
        SystemSetting::set('google_site_verification', $this->google_site_verification);
        SystemSetting::set('bing_site_verification', $this->bing_site_verification);
        
        // Upload OG Image
        if ($this->seo_og_image) {
            if ($this->current_og_image) {
                Storage::disk('public')->delete($this->current_og_image);
            }
            $ogPath = $this->seo_og_image->store('settings', 'public');
            SystemSetting::set('seo_og_image', $ogPath);
            $this->current_og_image = $ogPath;
            $this->seo_og_image = null;
        }
        
        SystemSetting::clearCache();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => 'Configurações SEO salvas com sucesso!'
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
    
    public function saveSchema()
    {
        SystemSetting::set('schema_app_name', $this->schema_app_name);
        SystemSetting::set('schema_app_description', $this->schema_app_description);
        SystemSetting::set('schema_app_url', $this->schema_app_url);
        SystemSetting::set('schema_app_category', $this->schema_app_category);
        SystemSetting::set('schema_price', $this->schema_price);
        SystemSetting::set('schema_currency', $this->schema_currency);
        SystemSetting::set('schema_region', $this->schema_region);
        SystemSetting::set('schema_rating_value', $this->schema_rating_value);
        SystemSetting::set('schema_review_count', $this->schema_review_count);
        SystemSetting::set('schema_creator_name', $this->schema_creator_name);
        SystemSetting::set('schema_creator_url', $this->schema_creator_url);
        
        SystemSetting::clearCache();
        
        $this->dispatch('notify', [
            'type' => 'success',
            'message' => '✅ Configurações Schema.org (JSON-LD) salvas com sucesso!'
        ]);
    }

    /**
     * Auditoria SEO — recolhe informação dos ficheiros públicos.
     */
    public function getSeoAuditProperty(): array
    {
        $audit = [
            'sitemap'  => ['path' => public_path('sitemap.xml'),  'url' => url('/sitemap.xml')],
            'robots'   => ['path' => public_path('robots.txt'),   'url' => url('/robots.txt')],
            'manifest' => ['path' => public_path('manifest.json'),'url' => url('/manifest.json')],
        ];
        foreach ($audit as $k => $v) {
            $exists = is_file($v['path']);
            $audit[$k]['exists']  = $exists;
            $audit[$k]['size']    = $exists ? filesize($v['path']) : 0;
            $audit[$k]['mtime']   = $exists ? \Carbon\Carbon::createFromTimestamp(filemtime($v['path'])) : null;
            $audit[$k]['preview'] = $exists ? substr(@file_get_contents($v['path']), 0, 600) : null;
        }

        // Contar URLs no sitemap
        $audit['sitemap']['url_count'] = 0;
        if ($audit['sitemap']['exists']) {
            $xml = @simplexml_load_file($audit['sitemap']['path']);
            if ($xml) $audit['sitemap']['url_count'] = count($xml->url ?? []);
        }

        // Analisar landing page
        $landing = resource_path('views/landing/home.blade.php');
        $audit['landing'] = ['path' => $landing, 'exists' => is_file($landing), 'checks' => []];
        if ($audit['landing']['exists']) {
            $html = file_get_contents($landing);
            $audit['landing']['checks'] = [
                'title_tag'          => (bool) preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $m1) ? trim($m1[1]) : null,
                'meta_description'   => (bool) preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']+)/i', $html, $m2) ? $m2[1] : null,
                'meta_keywords'      => (bool) preg_match('/<meta\s+name=["\']keywords["\']\s+content=["\']([^"\']+)/i', $html, $m3) ? $m3[1] : null,
                'canonical'          => (bool) preg_match('/<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']+)/i', $html, $m4) ? $m4[1] : null,
                'og_title'           => (bool) preg_match('/property=["\']og:title["\']/i', $html),
                'og_description'     => (bool) preg_match('/property=["\']og:description["\']/i', $html),
                'og_image'           => (bool) preg_match('/property=["\']og:image["\']/i', $html),
                'twitter_card'       => (bool) preg_match('/name=["\']twitter:card["\']/i', $html),
                'geo_region'         => (bool) preg_match('/name=["\']geo\.region["\']\s+content=["\']([^"\']+)/i', $html, $m5) ? $m5[1] : null,
                'geo_placename'      => (bool) preg_match('/name=["\']geo\.placename["\']\s+content=["\']([^"\']+)/i', $html, $m6) ? $m6[1] : null,
                'geo_position'       => (bool) preg_match('/name=["\']geo\.position["\']\s+content=["\']([^"\']+)/i', $html, $m7) ? $m7[1] : null,
                'hreflang_count'     => preg_match_all('/<link\s+rel=["\']alternate["\']\s+hreflang=/i', $html),
                'jsonld_count'       => preg_match_all('/<script\s+type=["\']application\/ld\+json["\']/i', $html),
                'favicon'            => (bool) preg_match('/<link\s+rel=["\'](?:icon|shortcut icon)["\']/i', $html),
                'apple_touch_icon'   => (bool) preg_match('/<link\s+rel=["\']apple-touch-icon["\']/i', $html),
                'theme_color'        => (bool) preg_match('/<meta\s+name=["\']theme-color["\']/i', $html),
                'manifest_link'      => (bool) preg_match('/<link\s+rel=["\']manifest["\']/i', $html),
            ];

            // Detectar tipos de JSON-LD presentes
            preg_match_all('/<script\s+type=["\']application\/ld\+json["\'][^>]*>([\s\S]*?)<\/script>/i', $html, $jm);
            $types = [];
            foreach ($jm[1] ?? [] as $json) {
                if (preg_match('/"@type"\s*:\s*"([^"]+)"/', $json, $tm)) $types[] = $tm[1];
            }
            $audit['landing']['schemas'] = array_values(array_unique($types));
        }

        return $audit;
    }

    public function render()
    {
        return view('livewire.super-admin.systemsettings', [
            'seoAudit' => $this->seoAudit,
        ]);
    }
}

