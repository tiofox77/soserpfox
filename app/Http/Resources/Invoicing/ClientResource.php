<?php

namespace App\Http\Resources\Invoicing;

use App\Support\Geografia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

            // Quantos documentos tem — é o que decide se pode ser apagado, e
            // vem contado do servidor para o ecrã não ter de adivinhar.
            'documentos' => (int) ($this->facturas_count ?? 0),
            'pode_apagar' => (int) ($this->facturas_count ?? 0) === 0,
        ];
    }
}
