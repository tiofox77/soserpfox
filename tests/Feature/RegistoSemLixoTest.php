<?php

namespace Tests\Feature;

use App\Rules\EmailQueExiste;
use App\Rules\NomeQueParecePessoa;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * O registo não aceita nomes gerados nem emails que não existem.
 *
 * Registos de lixo entram na base, disparam avisos a quem administra, ocupam
 * a fila de aprovação — e ficam lá para sempre, porque ninguém sabe se são
 * reais ou não.
 */
class RegistoSemLixoTest extends TestCase
{
    private function nomePassa(string $nome): bool
    {
        return Validator::make(['n' => $nome], ['n' => [new NomeQueParecePessoa()]])->passes();
    }

    private function emailPassa(string $email): bool
    {
        return Validator::make(['e' => $email], ['e' => [new EmailQueExiste()]])->passes();
    }

    public function test_recusa_um_identificador_gerado(): void
    {
        // Exactamente o que apareceu no aviso: "D 6a8441fb975b3".
        $this->assertFalse($this->nomePassa('D 6a8441fb975b3'));
        $this->assertFalse($this->nomePassa('a1b2c3d4e5f6'));
    }

    public function test_recusa_o_lixo_que_se_reconhece_de_longe(): void
    {
        $this->assertFalse($this->nomePassa('qwrtps'), 'sem uma vogal não é palavra de língua nenhuma');
        $this->assertFalse($this->nomePassa('aaaa'), 'letras repetidas');
        $this->assertFalse($this->nomePassa('12345'), 'só dígitos');

        // O que NÃO se tenta apanhar, de propósito: 'asdfgh' tem vogal e é
        // indistinguível de um nome curto sem um dicionário. Recusar um nome
        // verdadeiro é pior do que deixar passar um falso — quem se regista
        // desiste, e não há como lhe explicar o que fez de errado.
        $this->assertTrue($this->nomePassa('asdfgh'));
    }

    public function test_aceita_nomes_reais_incluindo_os_angolanos(): void
    {
        // Recusar um nome verdadeiro é pior do que deixar passar um falso:
        // quem se regista desiste e não há como lhe explicar o que fez mal.
        foreach ([
            'Judite Victoriano',
            'Farmácia Vital Saúde',
            'Kienga, Limitada',
            'UPITE SHOP - COMÉRCIO GERAL E PRESTAÇÃO DE SERVIÇOS, LDA',
            'Nguinamau Eduardo',
            'Gur Distribuição, Lda',
            'SOS ERP',
            'Ana',
        ] as $nome) {
            $this->assertTrue($this->nomePassa($nome), "recusou um nome real: {$nome}");
        }
    }

    public function test_recusa_dominios_que_nao_existem(): void
    {
        // Foi por aqui que os meus registos de diagnóstico dispararam avisos.
        foreach (['x@t.local', 'a@servidor.test', 'b@coisa.invalid', 'c@dominio.example'] as $email) {
            $this->assertFalse($this->emailPassa($email), "deixou passar: {$email}");
        }
    }

    public function test_recusa_caixas_descartaveis(): void
    {
        $this->assertFalse($this->emailPassa('alguem@mailinator.com'));
    }

    public function test_aceita_emails_normais(): void
    {
        foreach ([
            'kienga.lda@gmail.com',
            'carla.borges@kienga.co.ao',
            'geral@empresa.ao',
            'a.b@sub.dominio.pt',
        ] as $email) {
            $this->assertTrue($this->emailPassa($email), "recusou um email real: {$email}");
        }
    }
}
