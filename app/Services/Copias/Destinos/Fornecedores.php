<?php

namespace App\Services\Copias\Destinos;

use App\Models\Copias\DestinoDeCopia;
use RuntimeException;

/**
 * OS FORNECEDORES — o que cada um pede, e a fábrica que os abre.
 *
 * O ecrã desenha o formulário a partir daqui (`catalogo()`): os campos, se são
 * obrigatórios, se são segredos, e a ajuda de cada um. Um campo que se
 * acrescente aqui aparece no formulário — não há um formulário por fornecedor
 * escrito à mão para se esquecer de um campo.
 */
class Fornecedores
{
    public const TIPOS = ['google_drive', 'onedrive', 'dropbox', 'ftp', 'sftp', 's3', 'webdav'];

    public static function criar(DestinoDeCopia $d): Destino
    {
        return match ($d->tipo) {
            'google_drive' => new GoogleDrive($d),
            'onedrive' => new OneDrive($d),
            'dropbox' => new Dropbox($d),
            'ftp', 'sftp' => new Ftp($d),
            's3' => new S3($d),
            'webdav' => new WebDav($d),
            default => throw new RuntimeException("Tipo de destino desconhecido: {$d->tipo}"),
        };
    }

    public static function catalogo(): array
    {
        $protocolos = Ftp::protocolosDisponiveis();
        $campoPasta = fn (string $omissao, string $ajuda) => ['chave' => 'pasta', 'rotulo' => __('Pasta'), 'tipo' => 'text', 'obrigatorio' => false, 'omissao' => $omissao, 'ajuda' => $ajuda];

        return [
            'google_drive' => [
                'nome' => 'Google Drive', 'icone' => 'fab fa-google-drive', 'cor' => 'from-emerald-500 to-yellow-500',
                'oauth' => 'google', 'configurado' => OAuth::configurado('google'),
                'descricao' => __('Liga a sua conta Google. A aplicação só vê a pasta das cópias que ela própria cria.'),
                'campos' => [$campoPasta('SOSERP Copias', __('Criada no seu Drive na primeira cópia.'))],
                'disponivel' => true,
            ],
            'onedrive' => [
                'nome' => 'OneDrive', 'icone' => 'fab fa-microsoft', 'cor' => 'from-sky-500 to-blue-600',
                'oauth' => 'microsoft', 'configurado' => OAuth::configurado('microsoft'),
                'descricao' => __('Liga a sua conta Microsoft (pessoal ou empresarial). As cópias ficam em Apps → SOSERP.'),
                'campos' => [$campoPasta('SOSERP Copias', __('Dentro da pasta da aplicação no OneDrive.'))],
                'disponivel' => true,
            ],
            'dropbox' => [
                'nome' => 'Dropbox', 'icone' => 'fab fa-dropbox', 'cor' => 'from-blue-500 to-indigo-600',
                'oauth' => 'dropbox', 'configurado' => OAuth::configurado('dropbox'),
                'descricao' => __('Liga a sua conta Dropbox.'),
                'campos' => [$campoPasta('SOSERP Copias', __('Criada no seu Dropbox na primeira cópia.'))],
                'disponivel' => true,
            ],
            'ftp' => [
                'nome' => 'FTP / FTPS', 'icone' => 'fas fa-server', 'cor' => 'from-slate-500 to-slate-700',
                'oauth' => null, 'configurado' => true,
                'descricao' => __('Um servidor FTP seu. Prefira FTPS: o FTP simples manda a senha às claras.'),
                'campos' => [
                    ['chave' => 'anfitriao', 'rotulo' => __('Anfitrião'), 'tipo' => 'text', 'obrigatorio' => true, 'ajuda' => __('Ex.: ftp.exemplo.com')],
                    ['chave' => 'porta', 'rotulo' => __('Porta'), 'tipo' => 'number', 'obrigatorio' => false, 'omissao' => '21', 'ajuda' => __('21 para FTP e FTPS explícito; 990 para FTPS implícito.')],
                    ['chave' => 'utilizador', 'rotulo' => __('Utilizador'), 'tipo' => 'text', 'obrigatorio' => true],
                    ['chave' => 'senha', 'rotulo' => __('Senha'), 'tipo' => 'password', 'obrigatorio' => true, 'segredo' => true],
                    ['chave' => 'seguranca', 'rotulo' => __('Segurança'), 'tipo' => 'select', 'obrigatorio' => true, 'omissao' => 'explicito', 'opcoes' => [
                        ['valor' => 'explicito', 'rotulo' => __('FTPS explícito (recomendado)')],
                        ['valor' => 'implicito', 'rotulo' => __('FTPS implícito')],
                        ['valor' => 'nenhuma', 'rotulo' => __('Sem cifra (FTP simples)')],
                    ]],
                    ['chave' => 'verificar_certificado', 'rotulo' => __('Verificar o certificado'), 'tipo' => 'checkbox', 'obrigatorio' => false, 'omissao' => true, 'ajuda' => __('Desligue só se o servidor usar um certificado próprio.')],
                    ['chave' => 'passivo', 'rotulo' => __('Modo passivo'), 'tipo' => 'checkbox', 'obrigatorio' => false, 'omissao' => true],
                    $campoPasta('soserp-copias', __('Criada no servidor se não existir.')),
                ],
                'disponivel' => in_array('ftp', $protocolos, true),
            ],
            'sftp' => [
                'nome' => 'SFTP', 'icone' => 'fas fa-terminal', 'cor' => 'from-gray-700 to-gray-900',
                'oauth' => null, 'configurado' => true,
                'descricao' => __('Um servidor por SSH (SFTP). Cifrado de ponta a ponta.'),
                'campos' => [
                    ['chave' => 'anfitriao', 'rotulo' => __('Anfitrião'), 'tipo' => 'text', 'obrigatorio' => true, 'ajuda' => __('Ex.: backup.exemplo.com')],
                    ['chave' => 'porta', 'rotulo' => __('Porta'), 'tipo' => 'number', 'obrigatorio' => false, 'omissao' => '22'],
                    ['chave' => 'utilizador', 'rotulo' => __('Utilizador'), 'tipo' => 'text', 'obrigatorio' => true],
                    ['chave' => 'senha', 'rotulo' => __('Senha'), 'tipo' => 'password', 'obrigatorio' => true, 'segredo' => true],
                    $campoPasta('soserp-copias', __('Relativa à pasta do utilizador; comece por / para um caminho absoluto.')),
                ],
                'disponivel' => in_array('sftp', $protocolos, true),
            ],
            's3' => [
                'nome' => __('S3 compatível'), 'icone' => 'fas fa-bucket', 'cor' => 'from-orange-500 to-amber-600',
                'oauth' => null, 'configurado' => true,
                'descricao' => __('Amazon S3, Backblaze B2, Wasabi, Cloudflare R2, MinIO ou DigitalOcean Spaces.'),
                'campos' => [
                    ['chave' => 'endpoint', 'rotulo' => __('Endpoint'), 'tipo' => 'text', 'obrigatorio' => true, 'omissao' => 'https://s3.amazonaws.com', 'ajuda' => __('Ex.: https://s3.eu-west-1.amazonaws.com, https://s3.us-west-004.backblazeb2.com, https://<conta>.r2.cloudflarestorage.com')],
                    ['chave' => 'regiao', 'rotulo' => __('Região'), 'tipo' => 'text', 'obrigatorio' => true, 'omissao' => 'us-east-1', 'ajuda' => __('No Cloudflare R2 escreva «auto».')],
                    ['chave' => 'bucket', 'rotulo' => __('Bucket'), 'tipo' => 'text', 'obrigatorio' => true],
                    ['chave' => 'chave_acesso', 'rotulo' => __('Chave de acesso'), 'tipo' => 'text', 'obrigatorio' => true],
                    ['chave' => 'chave_secreta', 'rotulo' => __('Chave secreta'), 'tipo' => 'password', 'obrigatorio' => true, 'segredo' => true],
                    ['chave' => 'estilo_caminho', 'rotulo' => __('Endereço com o bucket no caminho'), 'tipo' => 'checkbox', 'obrigatorio' => false, 'omissao' => true, 'ajuda' => __('Ligado funciona em quase todos; desligue só se o fornecedor exigir bucket.endpoint.')],
                    $campoPasta('soserp-copias', __('Prefixo das cópias dentro do bucket.')),
                ],
                'disponivel' => true,
            ],
            'webdav' => [
                'nome' => 'WebDAV / Nextcloud', 'icone' => 'fas fa-cloud', 'cor' => 'from-cyan-500 to-teal-600',
                'oauth' => null, 'configurado' => true,
                'descricao' => __('Nextcloud, ownCloud, pCloud, Koofr ou um NAS (Synology, QNAP).'),
                'campos' => [
                    ['chave' => 'url', 'rotulo' => __('Endereço WebDAV'), 'tipo' => 'text', 'obrigatorio' => true, 'ajuda' => __('Ex.: https://nuvem.exemplo.com/remote.php/dav/files/utilizador')],
                    ['chave' => 'utilizador', 'rotulo' => __('Utilizador'), 'tipo' => 'text', 'obrigatorio' => true],
                    ['chave' => 'senha', 'rotulo' => __('Senha'), 'tipo' => 'password', 'obrigatorio' => true, 'segredo' => true, 'ajuda' => __('No Nextcloud use uma senha de aplicação.')],
                    $campoPasta('SOSERP Copias', __('Criada se não existir.')),
                ],
                'disponivel' => true,
            ],
        ];
    }

    /** As regras de validação da configuração de um tipo (a pasta vive noutra coluna). */
    public static function regras(string $tipo, bool $aCriar): array
    {
        $regras = [];
        foreach (self::catalogo()[$tipo]['campos'] ?? [] as $c) {
            if ($c['chave'] === 'pasta') {
                continue;
            }
            $base = match ($c['tipo']) {
                'number' => ['integer', 'min:1', 'max:65535'],
                'checkbox' => ['boolean'],
                'select' => ['in:' . implode(',', array_column($c['opcoes'] ?? [], 'valor'))],
                default => ['string', 'max:500'],
            };
            // Um segredo em branco ao editar = manter o que está gravado.
            $obrigatorio = $c['obrigatorio'] && ($aCriar || empty($c['segredo']));
            $regras["configuracao.{$c['chave']}"] = array_merge([$obrigatorio ? 'required' : 'nullable'], $base);
        }

        return $regras;
    }

    /** O destino para o ecrã: nunca os segredos, só se estão preenchidos. */
    public static function paraEcra(DestinoDeCopia $d): array
    {
        $catalogo = self::catalogo()[$d->tipo] ?? null;
        $config = [];
        foreach ($catalogo['campos'] ?? [] as $c) {
            if ($c['chave'] === 'pasta') {
                continue;
            }
            $config[$c['chave']] = ! empty($c['segredo'])
                ? (filled($d->cfg($c['chave'])) ? '••••••••' : '')
                : $d->cfg($c['chave'], $c['omissao'] ?? null);
        }

        return [
            'id' => $d->id,
            'nome' => $d->nome,
            'tipo' => $d->tipo,
            'fornecedor' => $catalogo['nome'] ?? $d->tipo,
            'icone' => $catalogo['icone'] ?? 'fas fa-cloud',
            'cor' => $catalogo['cor'] ?? 'from-slate-500 to-slate-700',
            'oauth' => $catalogo['oauth'] ?? null,
            'pasta' => $d->pasta,
            'manter' => (int) $d->manter,
            'activo' => (bool) $d->activo,
            'ligado' => (bool) $d->ligado,
            'testado_em' => $d->testado_em?->toIso8601String(),
            'ultimo_erro' => $d->ultimo_erro,
            'configuracao' => $config,
        ];
    }
}
