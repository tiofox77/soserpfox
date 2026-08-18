<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * A lista de empresas tem de COMPILAR.
 *
 * Uma etiqueta desequilibrada neste ficheiro não dá aviso nenhum até alguém
 * abrir a página — e aí é um 500 no ecrã principal do super admin. Foi o que
 * aconteceu: uma substituição minha deixou um @endif órfão e a página caiu.
 */
class ListaDeTenantsCompilaTest extends TestCase
{
    public function test_o_blade_da_lista_compila(): void
    {
        $caminho = resource_path('views/livewire/super-admin/tenants/tenants.blade.php');

        $php = Blade::compileString(file_get_contents($caminho));

        // O compilador do Blade não valida o PHP que produz; quem apanha o
        // desequilíbrio é o parser.
        $erro = null;

        try {
            token_get_all($php, TOKEN_PARSE);
        } catch (\ParseError $e) {
            $erro = $e->getMessage();
        }

        $this->assertNull($erro, "a lista de empresas não compila: {$erro}");
    }

    public function test_os_ifs_do_ficheiro_fecham_todos(): void
    {
        $fonte = file_get_contents(resource_path('views/livewire/super-admin/tenants/tenants.blade.php'));

        $abertos = preg_match_all('/@if\s*\(|@foreach\s*\(|@forelse\s*\(/', $fonte);
        $fechados = preg_match_all('/@endif\b|@endforeach\b|@endforelse\b/', $fonte);

        $this->assertSame($abertos, $fechados, 'há blocos abertos a mais ou a menos');
    }
}
