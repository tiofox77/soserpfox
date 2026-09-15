<?php

namespace App\Http\Resources\Invoicing;

use App\Support\Geografia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * A forma de um cliente quando sai para o React.
 *
 * O QUE NÃO SAI DAQUI, e é a razão de este ficheiro existir: a tabela
 * `invoicing_clients` guarda `password`, `remember_token` e
 * `password_changed_at` — o cliente autentica-se no portal com elas. Um
 * modelo cru numa resposta publicava-as. Aqui só sai o que está escrito.
 */
class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'tipo_rotulo' => $this->type === 'pessoa_fisica' ? __('Particular') : __('Empresa'),
            'name' => $this->name,
            'nif' => $this->nif,
            'email' => $this->email,
            'phone' => $this->phone,
            'mobile' => $this->mobile,

            /*
             * O LOGÓTIPO SAI EM DOIS FORMATOS, como o do artigo: a URL para
             * mostrar e o CAMINHO que está gravado na coluna. O caminho é a
             * única chave que não muda quando o domínio das imagens muda —
             * devolver só a URL obrigava o ecrã a desfazê-la para adivinhar.
             */
            'logo' => self::urlDaImagem($this->logo),
            'logo_caminho' => $this->logo,

            /*
             * A CONDIÇÃO DE PAGAMENTO é o que dá o vencimento à factura deste
             * cliente. Sai o id (para o formulário) e o nome já feito (para a
             * lista) — o ecrã não tem de cruzar duas listas para o escrever.
             */
            'payment_term_id' => $this->payment_term_id,
            'condicao_pagamento' => $this->whenLoaded(
                'paymentTerm',
                fn () => $this->paymentTerm?->name,
                null
            ),

            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'municipality' => $this->municipality,
            'neighbourhood' => $this->neighbourhood,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'pais_nome' => Geografia::nomeDoPais($this->country),

            // SE TEM PORTA ABERTA PARA O PORTAL — o facto, nunca a senha. É o
            // que diz ao ecrã se há acesso para repor ou para dar.
            'portal_access' => (bool) $this->portal_access,
            // As áreas do portal que este cliente vê — as marcadas, ou as de sempre.
            'portal_modulos' => is_array($this->portal_modulos) ? $this->portal_modulos : \App\Support\PortalDoCliente::DE_SEMPRE,

            // Quantos documentos tem — é o que decide se pode ser apagado, e
            // vem contado do servidor para o ecrã não ter de adivinhar.
            'documentos' => (int) ($this->facturas_count ?? 0),
            'pode_apagar' => (int) ($this->facturas_count ?? 0) === 0,
        ];
    }

    /**
     * A morada de uma imagem guardada — a mesma regra do artigo.
     *
     * Uma ficha importada pode trazer um endereço completo em vez de um
     * caminho no disco; passá-lo pelo `Storage::url` dava
     * `/storage/https://…`, uma imagem partida sem explicação nenhuma.
     */
    private static function urlDaImagem(?string $caminho): ?string
    {
        if (! filled($caminho)) {
            return null;
        }

        return str_starts_with($caminho, 'http://') || str_starts_with($caminho, 'https://')
            ? $caminho
            : Storage::disk('public')->url($caminho);
    }
}
