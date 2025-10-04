<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class NIFLookupService
{
    /**
     * Tenta buscar informações de um NIF
     * 
     * Ordem de busca:
     * 1. Cache local (NIFs já consultados)
     * 2. Banco de dados interno (clientes/fornecedores existentes)
     * 3. API externa (se disponível no futuro)
     */
    public function lookup(string $nif): ?array
    {
        // Remove caracteres não numéricos
        $nif = preg_replace('/[^0-9]/', '', $nif);
        
        // 1. Busca no cache
        $cached = $this->getFromCache($nif);
        if ($cached) {
            return $cached;
        }
        
        // 2. Busca no banco de dados interno
        $fromDatabase = $this->getFromDatabase($nif);
        if ($fromDatabase) {
            // Salva no cache para próximas consultas
            $this->saveToCache($nif, $fromDatabase);
            return $fromDatabase;
        }
        
        // 3. API externa (placeholder para futuro)
        // $fromApi = $this->getFromApi($nif);
        // if ($fromApi) {
        //     $this->saveToCache($nif, $fromApi);
        //     return $fromApi;
        // }
        
        return null;
    }
    
    /**
     * Busca NIF no cache local
     */
    protected function getFromCache(string $nif): ?array
    {
        return Cache::remember("nif_lookup_{$nif}", now()->addDays(30), function () use ($nif) {
            return $this->getFromDatabase($nif);
        });
    }
    
    /**
     * Busca NIF no banco de dados (clientes e fornecedores existentes)
     */
    protected function getFromDatabase(string $nif): ?array
    {
        // Busca em clientes
        $client = \App\Models\Client::where('nif', $nif)->first();
        if ($client) {
            return [
                'nif' => $client->nif,
                'name' => $client->name,
                'type' => $client->type,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
                'city' => $client->city,
                'province' => $client->province,
                'country' => $client->country,
                'source' => 'database_client',
            ];
        }
        
        // Busca em fornecedores
        $supplier = \App\Models\Supplier::where('nif', $nif)->first();
        if ($supplier) {
            return [
                'nif' => $supplier->nif,
                'name' => $supplier->name,
                'type' => $supplier->type,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'city' => $supplier->city,
                'province' => $supplier->province,
                'country' => $supplier->country,
                'source' => 'database_supplier',
            ];
        }
        
        return null;
    }
    
    /**
     * Salva informações de NIF no cache
     */
    protected function saveToCache(string $nif, array $data): void
    {
        Cache::put("nif_lookup_{$nif}", $data, now()->addDays(30));
    }
    
    /**
     * Placeholder para API externa futura
     * 
     * Quando a AGT disponibilizar API pública, implementar aqui
     */
    protected function getFromApi(string $nif): ?array
    {
        // TODO: Implementar quando API AGT estiver disponível
        
        // Exemplo de estrutura futura:
        /*
        try {
            $response = Http::timeout(5)->get('https://api.agt.minfin.gov.ao/v1/nif/' . $nif, [
                'api_key' => config('services.agt.api_key'),
            ]);
            
            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            \Log::warning('Erro ao consultar API AGT: ' . $e->getMessage());
        }
        */
        
        return null;
    }
    
    /**
     * Limpa cache de um NIF específico
     */
    public function clearCache(string $nif): void
    {
        $nif = preg_replace('/[^0-9]/', '', $nif);
        Cache::forget("nif_lookup_{$nif}");
    }
    
    /**
     * Verifica se NIF existe (foi usado antes no sistema)
     */
    public function exists(string $nif): bool
    {
        return $this->lookup($nif) !== null;
    }
}
