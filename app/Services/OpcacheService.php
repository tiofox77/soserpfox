<?php

namespace App\Services;

class OpcacheService
{
    /**
     * Verificar se OPcache está disponível
     */
    public function isAvailable(): bool
    {
        return function_exists('opcache_get_status');
    }

    /**
     * Verificar se OPcache está ativo
     */
    public function isEnabled(): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $status = @opcache_get_status();
        return $status !== false && ($status['opcache_enabled'] ?? false);
    }

    /**
     * Obter status completo do OPcache
     */
    public function getStatus(): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $status = opcache_get_status();
        $config = opcache_get_configuration();

        return [
            'enabled' => $status['opcache_enabled'] ?? false,
            'cache_full' => $status['cache_full'] ?? false,
            'restart_pending' => $status['restart_pending'] ?? false,
            'restart_in_progress' => $status['restart_in_progress'] ?? false,
            
            // Estatísticas
            'statistics' => [
                'num_cached_scripts' => $status['opcache_statistics']['num_cached_scripts'] ?? 0,
                'num_cached_keys' => $status['opcache_statistics']['num_cached_keys'] ?? 0,
                'max_cached_keys' => $status['opcache_statistics']['max_cached_keys'] ?? 0,
                'hits' => $status['opcache_statistics']['hits'] ?? 0,
                'misses' => $status['opcache_statistics']['misses'] ?? 0,
                'blacklist_misses' => $status['opcache_statistics']['blacklist_misses'] ?? 0,
                'blacklist_miss_ratio' => $status['opcache_statistics']['blacklist_miss_ratio'] ?? 0,
                'opcache_hit_rate' => $status['opcache_statistics']['opcache_hit_rate'] ?? 0,
            ],
            
            // Memória
            'memory_usage' => [
                'used_memory' => $status['memory_usage']['used_memory'] ?? 0,
                'free_memory' => $status['memory_usage']['free_memory'] ?? 0,
                'wasted_memory' => $status['memory_usage']['wasted_memory'] ?? 0,
                'current_wasted_percentage' => $status['memory_usage']['current_wasted_percentage'] ?? 0,
            ],
            
            // Configuração
            'configuration' => [
                'memory_consumption' => $config['directives']['opcache.memory_consumption'] ?? 0,
                'max_accelerated_files' => $config['directives']['opcache.max_accelerated_files'] ?? 0,
                'validate_timestamps' => $config['directives']['opcache.validate_timestamps'] ?? false,
                'revalidate_freq' => $config['directives']['opcache.revalidate_freq'] ?? 0,
                'enable_cli' => $config['directives']['opcache.enable_cli'] ?? false,
            ],
        ];
    }

    /**
     * Limpar cache do OPcache
     */
    public function clear(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return opcache_reset();
    }

    /**
     * Invalidar arquivo específico
     */
    public function invalidate(string $file, bool $force = true): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        return opcache_invalidate($file, $force);
    }

    /**
     * Obter estatísticas formatadas
     */
    public function getFormattedStats(): array
    {
        $status = $this->getStatus();
        
        if (!$status) {
            return [
                'available' => false,
                'enabled' => false,
            ];
        }

        $stats = $status['statistics'];
        $memory = $status['memory_usage'];
        $config = $status['configuration'];

        $totalMemory = $memory['used_memory'] + $memory['free_memory'];
        $memoryUsagePercentage = $totalMemory > 0 ? ($memory['used_memory'] / $totalMemory) * 100 : 0;

        $totalRequests = $stats['hits'] + $stats['misses'];
        $hitRate = $totalRequests > 0 ? ($stats['hits'] / $totalRequests) * 100 : 0;

        return [
            'available' => true,
            'enabled' => true,
            'cache_full' => $status['cache_full'],
            'restart_pending' => $status['restart_pending'],
            
            'stats' => [
                'hit_rate' => round($hitRate, 2),
                'hits' => number_format($stats['hits']),
                'misses' => number_format($stats['misses']),
                'cached_scripts' => number_format($stats['num_cached_scripts']),
                'max_scripts' => number_format($config['max_accelerated_files']),
            ],
            
            'memory' => [
                'total_mb' => round($totalMemory / 1024 / 1024, 2),
                'used_mb' => round($memory['used_memory'] / 1024 / 1024, 2),
                'free_mb' => round($memory['free_memory'] / 1024 / 1024, 2),
                'wasted_mb' => round($memory['wasted_memory'] / 1024 / 1024, 2),
                'usage_percentage' => round($memoryUsagePercentage, 2),
                'wasted_percentage' => round($memory['current_wasted_percentage'], 2),
            ],
            
            'config' => [
                'memory_consumption' => $config['memory_consumption'] . ' MB',
                'max_files' => number_format($config['max_accelerated_files']),
                'validate_timestamps' => $config['validate_timestamps'] ? 'Sim' : 'Não',
                'revalidate_freq' => $config['revalidate_freq'] . ' segundos',
                'cli_enabled' => $config['enable_cli'] ? 'Sim' : 'Não',
            ],
        ];
    }

    /**
     * Verificar saúde do OPcache
     */
    public function getHealthStatus(): array
    {
        $stats = $this->getFormattedStats();
        
        if (!$stats['available'] || !$stats['enabled']) {
            return [
                'status' => 'error',
                'message' => 'OPcache não está disponível ou ativo',
                'issues' => ['OPcache não está ativo'],
            ];
        }

        $issues = [];
        $warnings = [];

        // Verificar cache cheio
        if ($stats['cache_full']) {
            $issues[] = 'Cache está cheio! Aumente opcache.memory_consumption';
        }

        // Verificar hit rate
        if ($stats['stats']['hit_rate'] < 90) {
            $warnings[] = 'Hit rate baixo (' . $stats['stats']['hit_rate'] . '%). Ideal: > 95%';
        }

        // Verificar uso de memória
        if ($stats['memory']['usage_percentage'] > 90) {
            $issues[] = 'Uso de memória alto (' . $stats['memory']['usage_percentage'] . '%). Considere aumentar memória.';
        }

        // Verificar memória desperdiçada
        if ($stats['memory']['wasted_percentage'] > 10) {
            $warnings[] = 'Memória desperdiçada alta (' . $stats['memory']['wasted_percentage'] . '%). Execute opcache_reset().';
        }

        // Verificar reinício pendente
        if ($stats['restart_pending']) {
            $warnings[] = 'Reinício do cache pendente.';
        }

        // Determinar status geral
        if (!empty($issues)) {
            $status = 'warning';
            $message = 'OPcache com problemas que requerem atenção';
        } elseif (!empty($warnings)) {
            $status = 'info';
            $message = 'OPcache funcionando com avisos';
        } else {
            $status = 'success';
            $message = 'OPcache funcionando perfeitamente';
        }

        return [
            'status' => $status,
            'message' => $message,
            'issues' => $issues,
            'warnings' => $warnings,
        ];
    }
}
