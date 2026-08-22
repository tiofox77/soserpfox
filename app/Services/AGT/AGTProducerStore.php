<?php

namespace App\Services\AGT;

use App\Models\Invoicing\InvoicingSettings;
use Illuminate\Support\Facades\Storage;

/**
 * Credenciais e chaves do PRODUTOR de software, por ambiente.
 *
 * O produtor — o SOS ERP — tem as suas próprias credenciais e o seu próprio par
 * RSA junto da AGT, distintos dos do contribuinte. E a AGT entrega conjuntos
 * DIFERENTES para homologação e para produção, tal como faz com os do
 * contribuinte.
 *
 * Até aqui havia um único conjunto: `saft/{public,private}_key.pem` e um par
 * `AGT_API_USERNAME`/`AGT_API_PASSWORD`. Uma empresa em produção assinava com a
 * chave de produtor de testes e autenticava-se com as credenciais de testes —
 * e a AGT de produção recusa ambas. Instalar as de produção obrigava a apagar
 * as de homologação, deixando quem ainda testava sem forma de o fazer.
 *
 * Estrutura, igual à que o AGTKeyStore já usa para o contribuinte:
 *   saft/sandbox/{public,private}_key.pem
 *   saft/production/{public,private}_key.pem
 *
 * O caminho legado `saft/{public,private}_key.pem` continua a ser lido como
 * recurso, para as instalações que ainda não separaram os ambientes.
 */
class AGTProducerStore
{
    public const AMBIENTES = ['sandbox', 'production'];

    public static function normalizar(?string $ambiente): string
    {
        return in_array($ambiente, self::AMBIENTES, true) ? $ambiente : 'sandbox';
    }

    /** Ambiente activo de uma empresa — é ele que decide o conjunto a usar. */
    public static function ambienteDaEmpresa(?int $tenantId): string
    {
        if (!$tenantId) {
            return 'sandbox';
        }

        return self::normalizar(InvoicingSettings::forTenant($tenantId)->agt_environment ?? null);
    }

    public static function directory(string $ambiente): string
    {
        return 'saft/' . self::normalizar($ambiente);
    }

    /**
     * Caminho efectivo de uma chave do produtor.
     *
     * O par legado é o par certificado do PRODUTO SOS ERP e é deliberadamente
     * partilhado entre homologação e produção. As chaves que têm de ser
     * diferentes por ambiente são as do contribuinte/empresa, não esta chave
     * global do produtor.
     */
    public static function keyPath(string $ambiente, string $tipo): string
    {
        $ficheiro = $tipo === 'private' ? 'private_key.pem' : 'public_key.pem';
        $doAmbiente = self::directory($ambiente) . '/' . $ficheiro;

        if (Storage::disk('local')->exists($doAmbiente)) {
            return $doAmbiente;
        }

        return 'saft/' . $ficheiro;
    }

    public static function publicKeyPath(string $ambiente): string
    {
        return self::keyPath($ambiente, 'public');
    }

    public static function privateKeyPath(string $ambiente): string
    {
        return self::keyPath($ambiente, 'private');
    }

    /** Há par de chaves utilizável neste ambiente (próprio ou legado)? */
    public static function temChaves(string $ambiente): bool
    {
        $disco = Storage::disk('local');

        return $disco->exists(self::publicKeyPath($ambiente))
            && $disco->exists(self::privateKeyPath($ambiente));
    }

    /** As chaves deste ambiente são as PRÓPRIAS, ou está a usar as legadas? */
    public static function temChavesProprias(string $ambiente): bool
    {
        $disco = Storage::disk('local');
        $dir = self::directory($ambiente);

        return $disco->exists($dir . '/public_key.pem')
            && $disco->exists($dir . '/private_key.pem');
    }

    /**
     * Credenciais de acesso à API da AGT para este ambiente.
     *
     * Recorre ao par sem ambiente pela mesma razão das chaves: é o que hoje
     * autentica todas as submissões e cortá-lo às cegas parava-as.
     *
     * @return array{username:string, password:string, proprias:bool}
     */
    public static function credenciais(string $ambiente): array
    {
        $ambiente = self::normalizar($ambiente);

        $utilizador = (string) config("services.agt.{$ambiente}.username", '');
        $palavra    = (string) config("services.agt.{$ambiente}.password", '');

        if ($utilizador !== '' && $palavra !== '') {
            return ['username' => $utilizador, 'password' => $palavra, 'proprias' => true];
        }

        return [
            'username' => (string) config('services.agt.username', ''),
            'password' => (string) config('services.agt.password', ''),
            'proprias' => false,
        ];
    }

    /** Estão configuradas credenciais utilizáveis neste ambiente? */
    public static function temCredenciais(string $ambiente): bool
    {
        $c = self::credenciais($ambiente);

        return $c['username'] !== '' && $c['password'] !== '';
    }

    /**
     * Número do Processo de Certificação do Software, POR AMBIENTE.
     *
     * A AGT certifica o software separadamente em cada ambiente e emite uma
     * resolução para cada um. Vai em `softwareValidationNumber`, dentro do que
     * a jwsSoftwareSignature assina.
     *
     * Havia um só, e era o de produção: as chamadas a homologação levavam-no e
     * a AGT devolvia E39 — «os dados constantes na assinatura do produtor de
     * software não estão de acordo com a informação constante no Processo de
     * Certificação». A assinatura estava boa; o número é que era do outro
     * ambiente.
     */
    public static function numeroCertificacao(string $ambiente): string
    {
        $ambiente = self::normalizar($ambiente);

        $doAmbiente = (string) softwareSetting('invoicing', "saft_software_cert_{$ambiente}", '');

        if (trim($doAmbiente) !== '') {
            return trim($doAmbiente);
        }

        // Recurso ao valor único de sempre, para não parar quem ainda não os
        // separou. O ecrã diz quando é este o caso.
        return trim((string) softwareSetting('invoicing', 'saft_software_cert', ''));
    }

    /** O número deste ambiente é o próprio, ou está a usar o antigo partilhado? */
    public static function temCertificacaoPropria(string $ambiente): bool
    {
        $ambiente = self::normalizar($ambiente);

        return trim((string) softwareSetting('invoicing', "saft_software_cert_{$ambiente}", '')) !== '';
    }

    /**
     * productId e productVersion, POR AMBIENTE.
     *
     * Estes dois campos vão dentro do que a jwsSoftwareSignature assina, ao
     * lado do número de certificação, e a AGT compara-os LETRA A LETRA com o
     * que consta no Processo de Certificação. Um "1.0" contra "1.0.0", ou um
     * hífen onde o certificado tem travessão, devolve o mesmo E39 que o número
     * errado devolvia.
     *
     * E a AGT certifica cada ambiente separadamente: nada obriga a que o nome
     * e a versão registados em homologação sejam os mesmos que em produção.
     * Foi o que aconteceu aqui — homologação ficou com «SOS ERP - …» / «1.0» e
     * produção com «SOS ERP — …» / «1.0.0». Enquanto isto era um valor único
     * partilhado, acertar num ambiente estragava o outro.
     *
     * Recorre ao valor único de sempre quando o ambiente não tem o seu — para
     * não mexer em quem ainda não os separou.
     */
    public static function productId(string $ambiente): string
    {
        return self::porAmbienteOuGlobal($ambiente, 'saft_product_id', 'SOS ERP');
    }

    public static function productVersion(string $ambiente): string
    {
        return self::porAmbienteOuGlobal($ambiente, 'saft_version', '1.0');
    }

    private static function porAmbienteOuGlobal(string $ambiente, string $chave, string $omissao): string
    {
        $ambiente = self::normalizar($ambiente);

        $doAmbiente = (string) softwareSetting('invoicing', "{$chave}_{$ambiente}", '');

        if (trim($doAmbiente) !== '') {
            return trim($doAmbiente);
        }

        return (string) softwareSetting('invoicing', $chave, $omissao);
    }
}
