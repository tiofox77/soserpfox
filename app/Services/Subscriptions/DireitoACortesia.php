<?php

namespace App\Services\Subscriptions;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Cada cliente tem direito a UMA cortesia. Uma só, para sempre.
 *
 * Chamamos cortesia ao que se dá sem receber nada em troca: o plano gratuito
 * e o período de teste. Sem esta regra, o sistema oferece-se em ciclo — 180
 * dias de FOX Friendly, depois 30 de teste do Business, depois 30 do
 * Enterprise, depois 14 do Pacote Vendas... um ano e meio de ERP completo sem
 * uma factura pelo meio, e ainda sobram planos.
 *
 * As três regras, ditas pelo cliente e traduzidas para aqui:
 *
 *   · quem já teve o plano gratuito não o volta a ter;
 *   · quem já gastou um teste não tem direito a outro, nem mudando de plano;
 *   · quem já foi cliente — pagou ou teve o sistema em teste — não passa a
 *     seguir para o gratuito.
 *
 * ONDE SE APLICA: nos dois sítios onde alguém ESCOLHE um plano, o registo e a
 * área de conta. Não se aplica às subscrições que são cópia de uma existente
 * (abrir a 2.ª empresa dentro do mesmo plano, propagação entre empresas do
 * mesmo dono): essas não são cortesias novas, é a mesma a servir as empresas
 * que o plano já paga.
 *
 * QUEM É "O MESMO CLIENTE": as empresas do utilizador e as empresas com o
 * mesmo NIF — incluindo as apagadas, senão bastava eliminar a empresa para o
 * contador voltar a zero. Um NIF é um contribuinte; duas empresas com o mesmo
 * NIF são a mesma empresa.
 */
class DireitoACortesia
{
    /** Subscrição que nunca chegou a dar nada — não gasta a cortesia. */
    private const NUNCA_ARRANCOU = 'pending';

    private bool $jaFoiCliente = false;
    private bool $jaTeveGratuito = false;
    private bool $jaTeveTeste = false;

    private function __construct(private array $empresas)
    {
        if (empty($empresas)) {
            return;
        }

        $linhas = DB::table('subscriptions')
            ->leftJoin('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->whereIn('subscriptions.tenant_id', $empresas)
            ->get([
                'subscriptions.status',
                'subscriptions.trial_ends_at',
                'plans.price_monthly',
            ]);

        foreach ($linhas as $linha) {
            // Um pedido que ficou por pagar não deu acesso a nada. Contá-lo
            // fecharia a porta a quem começou a assinar o Business, desistiu a
            // meio, e agora nunca mais poderia sequer experimentar o gratuito.
            if ($linha->status === self::NUNCA_ARRANCOU) {
                continue;
            }

            $this->jaFoiCliente = true;

            if ((float) ($linha->price_monthly ?? 0) <= 0) {
                $this->jaTeveGratuito = true;
            }

            if ($linha->trial_ends_at !== null || $linha->status === 'trial') {
                $this->jaTeveTeste = true;
            }
        }
    }

    /**
     * Pelo utilizador e/ou pelo NIF da empresa.
     *
     * No registo ainda não há utilizador — sobra o NIF, que é o que impede a
     * mesma empresa de voltar com outro email. Na área de conta há os dois.
     */
    public static function de(?User $utilizador = null, ?string $nif = null): self
    {
        $empresas = [];

        if ($utilizador) {
            $empresas = $utilizador->tenants()->withTrashed()->pluck('tenants.id')->all();

            if ($utilizador->tenant_id) {
                $empresas[] = $utilizador->tenant_id;
            }
        }

        $nif = self::limparNif($nif);

        if ($nif !== null) {
            $empresas = array_merge(
                $empresas,
                Tenant::withTrashed()->where('nif', $nif)->pluck('id')->all()
            );
        }

        return new self(array_values(array_unique(array_map('intval', $empresas))));
    }

    /** Para uma empresa concreta (área de conta). */
    public static function daEmpresa(Tenant $empresa, ?User $utilizador = null): self
    {
        $direito = self::de($utilizador, $empresa->nif);

        if (in_array((int) $empresa->id, $direito->empresas, true)) {
            return $direito;
        }

        return new self(array_merge($direito->empresas, [(int) $empresa->id]));
    }

    /** Um plano sem preço mensal é o plano gratuito. */
    public static function ehGratuito(?Plan $plano): bool
    {
        return $plano !== null && (float) ($plano->getPrice('monthly') ?? 0) <= 0;
    }

    public function podeEscolher(?Plan $plano): bool
    {
        return $this->motivoParaRecusar($plano) === null;
    }

    /**
     * Porque é que este plano não está disponível — ou null se estiver.
     *
     * Devolve a frase pronta a mostrar: quem é recusado tem direito a saber
     * porquê, senão fica a olhar para um botão que não faz nada.
     */
    public function motivoParaRecusar(?Plan $plano): ?string
    {
        if (!self::ehGratuito($plano)) {
            return null;
        }

        if ($this->jaTeveGratuito) {
            return 'Este plano gratuito só pode ser usado uma vez. Já foi utilizado nesta conta.';
        }

        if ($this->jaFoiCliente) {
            return 'O plano gratuito é para quem começa agora. Esta conta já teve um plano activo.';
        }

        return null;
    }

    /**
     * Tem direito ao período de teste deste plano?
     *
     * Gastar o teste não impede a subscrição — só a impede de começar de
     * graça. O plano fica a aguardar pagamento em vez de arrancar sozinho.
     */
    public function temDireitoATeste(?Plan $plano = null): bool
    {
        if ($plano !== null && (int) ($plano->trial_days ?? 0) <= 0) {
            return false;
        }

        return !$this->jaTeveTeste && !$this->jaTeveGratuito;
    }

    public function motivoSemTeste(): ?string
    {
        if ($this->jaTeveTeste) {
            return 'O período de teste já foi utilizado nesta conta e só se dá uma vez, seja qual for o plano.';
        }

        if ($this->jaTeveGratuito) {
            return 'Esta conta já usufruiu do plano gratuito, que substitui o período de teste.';
        }

        return null;
    }

    public function jaFoiCliente(): bool
    {
        return $this->jaFoiCliente;
    }

    public function jaTeveGratuito(): bool
    {
        return $this->jaTeveGratuito;
    }

    public function jaTeveTeste(): bool
    {
        return $this->jaTeveTeste;
    }

    /** As empresas consideradas — útil para diagnóstico. */
    public function empresasConsideradas(): array
    {
        return $this->empresas;
    }

    private static function limparNif(?string $nif): ?string
    {
        if ($nif === null) {
            return null;
        }

        $nif = strtoupper(preg_replace('/\s+/', '', $nif));

        return $nif === '' ? null : $nif;
    }
}
