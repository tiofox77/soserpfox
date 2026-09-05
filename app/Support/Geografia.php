<?php

namespace App\Support;

/**
 * Países, províncias, municípios e bairros — num sítio só.
 *
 * PORQUE EXISTE. A lista das províncias estava escrita três vezes (no Client,
 * no Supplier e nos contactos do restaurante) e os países eram sete, escritos
 * à mão dentro de uma vista Blade. A morada da empresa não tinha lista
 * nenhuma: escrevia-se o país à mão, e viu-se uma empresa angolana com
 * «Portugal» no campo.
 *
 * ISSO NÃO É COSMÉTICO. O país do cliente viaja para a AGT como
 * `customerCountry`, que a DS.120 exige em ISO 3166-1 alfa-2 — duas letras.
 * Um «Portugal» escrito à mão chegava lá como «PORTUGAL», oito caracteres num
 * campo de dois.
 *
 * Os dados vivem em `resources/dados/*.json` e não no código: corrigir a
 * divisão administrativa passa a ser mudar um ficheiro, e não caçar cópias.
 */
final class Geografia
{
    /** O país por omissão de toda a aplicação. */
    public const PAIS_PADRAO = 'AO';

    private static ?array $paises = null;
    private static ?array $angola = null;
    private static ?array $indicePaises = null;

    // ── Países ────────────────────────────────────────────────────────

    /** @return array<string,string> código ISO => nome, por ordem de nome */
    public static function paises(): array
    {
        return self::$paises ??= self::ler('paises.json');
    }

    public static function nomeDoPais(?string $codigo): ?string
    {
        return self::paises()[strtoupper(trim((string) $codigo))] ?? null;
    }

    public static function ehPaisValido(?string $codigo): bool
    {
        return $codigo !== null && isset(self::paises()[strtoupper(trim($codigo))]);
    }

    /**
     * Qualquer coisa que alguém tenha escrito → código ISO, ou null.
     *
     * É isto que salva os registos antigos: aceita o código («AO», «ao»), o
     * nome («Angola», «ANGOLA», «angola») e as formas que aparecem nos dados
     * reais. Devolve null quando não reconhece — quem chama é que decide se
     * assume o padrão ou se pede à pessoa que corrija. Adivinhar o país de um
     * documento fiscal é pior do que admitir que não se sabe.
     */
    public static function normalizarPais(?string $valor): ?string
    {
        $v = trim((string) $valor);

        if ($v === '') {
            return null;
        }

        if (self::ehPaisValido($v)) {
            return strtoupper($v);
        }

        return self::indiceDePaises()[self::chave($v)] ?? null;
    }

    /** nome comparável → código. Construído uma vez. */
    private static function indiceDePaises(): array
    {
        if (self::$indicePaises !== null) {
            return self::$indicePaises;
        }

        $indice = [];

        foreach (self::paises() as $codigo => $nome) {
            $indice[self::chave($nome)] = $codigo;
        }

        // Formas que aparecem nos dados reais e que o ICU não escreve assim.
        foreach ([
            'AO' => ['angola', 'republica de angola', 'ao'],
            'PT' => ['portugal', 'republica portuguesa'],
            'CD' => ['congo democratico', 'rd congo', 'rdc', 'congo kinshasa', 'zaire'],
            'CG' => ['congo', 'congo brazzaville'],
            'CV' => ['cabo verde'],
            'ST' => ['sao tome', 'sao tome e principe'],
            'GW' => ['guine bissau'],
            'GQ' => ['guine equatorial'],
            'ZA' => ['africa do sul', 'south africa'],
            'NA' => ['namibia'],
            'BR' => ['brasil', 'brazil'],
            'US' => ['estados unidos', 'eua', 'usa'],
            'GB' => ['inglaterra', 'reino unido', 'uk'],
            'ES' => ['espanha'],
            'FR' => ['franca'],
            'CN' => ['china'],
            'AE' => ['dubai', 'emirados arabes unidos'],
        ] as $codigo => $formas) {
            foreach ($formas as $forma) {
                $indice[$forma] = $codigo;
            }
        }

        return self::$indicePaises = $indice;
    }

    // ── Angola: províncias, municípios, bairros ───────────────────────

    /** @return list<string> as 21 províncias, por ordem alfabética */
    public static function provincias(): array
    {
        return array_column(self::angola()['provincias'] ?? [], 'nome');
    }

    /** As que nasceram na reforma de 2024 — a UI assinala-as. */
    public static function provinciasNovas(): array
    {
        return array_column(
            array_filter(self::angola()['provincias'] ?? [], fn ($p) => isset($p['desde'])),
            'nome'
        );
    }

    /**
     * O nome actual de uma província escrita de outra maneira.
     *
     * Guarda os registos antigos: «Cuando Cubango» (que a reforma dividiu),
     * «Kwanza Norte», «Huila» sem acento. Devolve o que recebeu quando não
     * reconhece — um nome estranho não se apaga, mostra-se.
     */
    public static function normalizarProvincia(?string $nome): ?string
    {
        $n = trim((string) $nome);

        if ($n === '') {
            return null;
        }

        foreach (self::provincias() as $provincia) {
            if (self::chave($provincia) === self::chave($n)) {
                return $provincia;
            }
        }

        foreach (self::angola()['equivalencias'] ?? [] as $antiga => $actual) {
            if (self::chave($antiga) === self::chave($n)) {
                return $actual;
            }
        }

        return $n;
    }

    /** @return list<string> os municípios de uma província */
    public static function municipios(?string $provincia): array
    {
        $alvo = self::normalizarProvincia($provincia);

        foreach (self::angola()['provincias'] ?? [] as $p) {
            if ($p['nome'] === $alvo) {
                return $p['municipios'] ?? [];
            }
        }

        return [];
    }

    /** Todos os municípios do país, para procurar sem saber a província. */
    public static function todosOsMunicipios(): array
    {
        $todos = [];

        foreach (self::angola()['provincias'] ?? [] as $p) {
            foreach ($p['municipios'] ?? [] as $m) {
                $todos[$m] = $p['nome'];
            }
        }

        ksort($todos, SORT_LOCALE_STRING);

        return $todos;
    }

    public static function provinciaDoMunicipio(?string $municipio): ?string
    {
        return self::todosOsMunicipios()[trim((string) $municipio)] ?? null;
    }

    /**
     * Bairros SUGERIDOS de um município — nunca uma lista fechada.
     *
     * Angola não tem registo nacional de bairros: nascem, mudam de nome, e
     * raramente entram numa lista oficial. Um select fechado obrigaria as
     * pessoas a escolher o bairro errado por o certo não estar lá. Por isso o
     * campo sugere e aceita o que se escrever.
     */
    public static function bairros(?string $municipio): array
    {
        return self::angola()['bairros'][trim((string) $municipio)] ?? [];
    }

    // ── Utilitários ───────────────────────────────────────────────────

    /** Para comparar nomes sem tropeçar em acentos, caixa ou espaços. */
    public static function chave(string $texto): string
    {
        $t = mb_strtolower(trim($texto), 'UTF-8');
        $t = strtr($t, [
            'á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'õ' => 'o', 'ô' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $t));
    }

    private static function angola(): array
    {
        return self::$angola ??= self::ler('angola.json');
    }

    private static function ler(string $ficheiro): array
    {
        $caminho = resource_path('dados/' . $ficheiro);

        if (!is_file($caminho)) {
            return [];
        }

        $dados = json_decode(file_get_contents($caminho), true);

        if (!is_array($dados)) {
            return [];
        }

        // As chaves com underscore são notas para quem lê o ficheiro.
        return array_filter($dados, fn ($k) => !str_starts_with((string) $k, '_'), ARRAY_FILTER_USE_KEY);
    }

    /** Só para os ensaios: obriga a reler os ficheiros. */
    public static function esquecer(): void
    {
        self::$paises = self::$angola = self::$indicePaises = null;
    }
}
