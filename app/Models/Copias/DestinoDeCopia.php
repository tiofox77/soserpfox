<?php

namespace App\Models\Copias;

use Illuminate\Database\Eloquent\Model;

/**
 * Para onde vai uma cópia além do servidor: Google Drive, OneDrive, Dropbox,
 * FTP/FTPS/SFTP, S3 compatível ou WebDAV.
 *
 * As credenciais (senhas, chaves, tokens de acesso e de renovação) vivem em
 * `configuracao`, CIFRADAS com a APP_KEY, e nunca saem na API — o ecrã recebe
 * só se estão preenchidas (App\Services\Copias\Destinos\Fornecedores::paraEcra).
 */
class DestinoDeCopia extends Model
{
    protected $table = 'destinos_de_copia';

    protected $guarded = ['id'];

    protected $casts = [
        'configuracao' => 'encrypted:array',
        'activo' => 'boolean',
        'ligado' => 'boolean',
        'testado_em' => 'datetime',
    ];

    protected $hidden = ['configuracao'];

    public function scopeDoAmbito($q, ?int $tenantId)
    {
        return $tenantId === null ? $q->whereNull('tenant_id') : $q->where('tenant_id', $tenantId);
    }

    public function cfg(string $chave, mixed $omissao = null): mixed
    {
        return ($this->configuracao ?? [])[$chave] ?? $omissao;
    }

    /** Junta chaves à configuração sem perder as outras (ex.: um token renovado). */
    public function juntarCfg(array $valores): void
    {
        $this->configuracao = array_merge($this->configuracao ?? [], $valores);
        $this->save();
    }
}
