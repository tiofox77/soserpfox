<?php

namespace App\Services\Copias\Destinos;

use App\Models\Copias\DestinoDeCopia;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * LIGAR UM GOOGLE DRIVE, UM ONEDRIVE OU UM DROPBOX — o OAuth dos três.
 *
 * O dono da plataforma cria UMA aplicação em cada fornecedor e grava aqui o
 * «client id» e o «client secret» (cifrados). Depois, a plataforma e cada
 * empresa carregam em «Ligar», autorizam na página do fornecedor, e voltam com
 * um código que se troca por um token de renovação — guardado cifrado no
 * destino. Nunca se pede nem se guarda a senha da conta Google ou Microsoft.
 *
 * PERMISSÕES MÍNIMAS: o Google só vê os ficheiros que ESTA aplicação criou
 * (drive.file), o OneDrive só a pasta da aplicação (Files.ReadWrite.AppFolder).
 * Uma cópia de segurança não precisa de ler o resto do Drive de ninguém.
 *
 * O `state` da ida é cifrado com a APP_KEY e leva o destino, a pessoa e um prazo
 * de 15 minutos: um retorno forjado ou reaproveitado é recusado.
 */
class OAuth
{
    public const FORNECEDORES = ['google', 'microsoft', 'dropbox'];

    private const DO_TIPO = ['google_drive' => 'google', 'onedrive' => 'microsoft', 'dropbox' => 'dropbox'];

    public static function doTipo(string $tipo): ?string
    {
        return self::DO_TIPO[$tipo] ?? null;
    }

    /** @return array{client_id: ?string, client_secret: ?string} */
    public static function credenciais(string $fornecedor): array
    {
        $gravado = SystemSetting::get("copias_oauth_{$fornecedor}");
        if ($gravado) {
            try {
                $c = json_decode(Crypt::decryptString($gravado), true);
                if (! empty($c['client_id']) && ! empty($c['client_secret'])) {
                    return $c;
                }
            } catch (\Throwable) {
                // Cifrado com outra APP_KEY: cai para o .env.
            }
        }

        return config("copias.oauth.{$fornecedor}") + ['client_id' => null, 'client_secret' => null];
    }

    public static function guardarCredenciais(string $fornecedor, string $clientId, string $clientSecret): void
    {
        SystemSetting::set("copias_oauth_{$fornecedor}", Crypt::encryptString(json_encode([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ])));
    }

    public static function configurado(string $fornecedor): bool
    {
        $c = self::credenciais($fornecedor);

        return ! empty($c['client_id']) && ! empty($c['client_secret']);
    }

    public static function retorno(): string
    {
        return route('copias.oauth.retorno');
    }

    public static function urlDeAutorizacao(DestinoDeCopia $destino, int $userId): string
    {
        $fornecedor = self::doTipo($destino->tipo) ?? throw new RuntimeException('Este destino não se liga por OAuth.');
        $c = self::credenciais($fornecedor);

        if (empty($c['client_id'])) {
            throw new RuntimeException(__('A aplicação :f ainda não foi configurada pelo dono da plataforma.', ['f' => ucfirst($fornecedor)]));
        }

        $state = Crypt::encryptString(json_encode(['d' => $destino->id, 'u' => $userId, 'x' => now()->addMinutes(15)->timestamp]));

        return match ($fornecedor) {
            'google' => 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
                'client_id' => $c['client_id'], 'redirect_uri' => self::retorno(), 'response_type' => 'code',
                'scope' => 'https://www.googleapis.com/auth/drive.file', 'access_type' => 'offline',
                'prompt' => 'consent', 'include_granted_scopes' => 'true', 'state' => $state,
            ]),
            'microsoft' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?' . http_build_query([
                'client_id' => $c['client_id'], 'redirect_uri' => self::retorno(), 'response_type' => 'code',
                'response_mode' => 'query', 'scope' => 'Files.ReadWrite.AppFolder offline_access', 'state' => $state,
            ]),
            'dropbox' => 'https://www.dropbox.com/oauth2/authorize?' . http_build_query([
                'client_id' => $c['client_id'], 'redirect_uri' => self::retorno(), 'response_type' => 'code',
                'token_access_type' => 'offline', 'state' => $state,
            ]),
        };
    }

    /** Lê o `state` e devolve o destino, se for válido, desta pessoa e dentro do prazo. */
    public static function lerState(string $state, int $userId): DestinoDeCopia
    {
        try {
            $d = json_decode(Crypt::decryptString($state), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw new RuntimeException(__('O pedido de ligação não é válido. Tente de novo.'));
        }

        if (($d['u'] ?? null) !== $userId || ($d['x'] ?? 0) < now()->timestamp) {
            throw new RuntimeException(__('O pedido de ligação expirou. Tente de novo.'));
        }

        return DestinoDeCopia::findOrFail((int) $d['d']);
    }

    /** Troca o código de autorização pelos tokens e grava-os no destino. */
    public static function concluir(DestinoDeCopia $destino, string $codigo): void
    {
        $fornecedor = self::doTipo($destino->tipo);
        $c = self::credenciais($fornecedor);

        $r = Http::asForm()->timeout(30)->post(self::urlDoToken($fornecedor), array_filter([
            'grant_type' => 'authorization_code',
            'code' => $codigo,
            'redirect_uri' => self::retorno(),
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'scope' => $fornecedor === 'microsoft' ? 'Files.ReadWrite.AppFolder offline_access' : null,
        ]));

        if (! $r->successful() || ! $r->json('refresh_token')) {
            throw new RuntimeException(__('O fornecedor recusou a ligação: :m', ['m' => $r->json('error_description') ?? $r->json('error') ?? $r->status()]));
        }

        $destino->juntarCfg([
            'refresh_token' => $r->json('refresh_token'),
            'access_token' => $r->json('access_token'),
            'expira_em' => now()->addSeconds((int) $r->json('expires_in', 3600) - 60)->timestamp,
            'pasta_id' => null,
        ]);
        $destino->forceFill(['ligado' => true, 'ultimo_erro' => null])->save();
    }

    /** Um token de acesso válido — renovado quando está a acabar. */
    public static function acesso(DestinoDeCopia $destino): string
    {
        if ($destino->cfg('access_token') && (int) $destino->cfg('expira_em', 0) > now()->timestamp) {
            return $destino->cfg('access_token');
        }

        $fornecedor = self::doTipo($destino->tipo);
        $c = self::credenciais($fornecedor);

        if (! $destino->cfg('refresh_token')) {
            throw new RuntimeException(__('Este destino ainda não foi ligado. Carregue em «Ligar conta».'));
        }

        $r = Http::asForm()->timeout(30)->post(self::urlDoToken($fornecedor), array_filter([
            'grant_type' => 'refresh_token',
            'refresh_token' => $destino->cfg('refresh_token'),
            'client_id' => $c['client_id'],
            'client_secret' => $c['client_secret'],
            'scope' => $fornecedor === 'microsoft' ? 'Files.ReadWrite.AppFolder offline_access' : null,
        ]));

        if (! $r->successful() || ! $r->json('access_token')) {
            $destino->forceFill(['ligado' => false])->save();
            throw new RuntimeException(__('A autorização do destino expirou ou foi retirada. Volte a ligar a conta. (:m)', ['m' => $r->json('error') ?? $r->status()]));
        }

        $destino->juntarCfg(array_filter([
            'access_token' => $r->json('access_token'),
            'expira_em' => now()->addSeconds((int) $r->json('expires_in', 3600) - 60)->timestamp,
            // A Microsoft devolve um token de renovação novo a cada renovação.
            'refresh_token' => $r->json('refresh_token'),
        ]));

        return $r->json('access_token');
    }

    private static function urlDoToken(string $fornecedor): string
    {
        return match ($fornecedor) {
            'google' => 'https://oauth2.googleapis.com/token',
            'microsoft' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
            'dropbox' => 'https://api.dropboxapi.com/oauth2/token',
        };
    }
}
