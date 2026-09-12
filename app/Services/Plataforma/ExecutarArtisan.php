<?php

namespace App\Services\Plataforma;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * CORRER UM COMANDO ARTISAN A PARTIR DE UM ECRÃ, e devolver o que ele disse.
 *
 * Os ecrãs de sistema (optimização, comandos, actualizações) chamavam o
 * `Artisan::call` cada um à sua maneira, e dois deles liam o resultado com
 * `Artisan::output()` depois de terem passado um buffer próprio — que fica
 * vazio nesse caso, pelo que o seeder dizia sempre «executado sem output».
 *
 * Existe também para os ensaios o poderem trocar: um `config:cache` corrido
 * durante os ensaios grava `bootstrap/cache/config.php` com a configuração de
 * ensaio e parte a aplicação local.
 */
class ExecutarArtisan
{
    /** @return array{codigo: int, saida: string} */
    public function correr(string $comando, array $parametros = []): array
    {
        $buffer = new BufferedOutput();
        $codigo = Artisan::call($comando, $parametros, $buffer);

        return ['codigo' => (int) $codigo, 'saida' => trim($buffer->fetch())];
    }
}
