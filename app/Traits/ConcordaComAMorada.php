<?php

namespace App\Traits;

use App\Support\Geografia;

/**
 * O comportamento partilhado dos formulários que têm uma morada.
 *
 * Três coisas que todos precisam de fazer igual, e que escritas em cada um
 * acabavam diferentes:
 *
 * 1. Mudar de país limpa a província e o município — senão ficava «Huíla» numa
 *    morada portuguesa, e ia assim para a factura.
 * 2. Mudar de província limpa o município, pelo mesmo motivo.
 * 3. A CIDADE ACOMPANHA O MUNICÍPIO. A coluna `city` é lida por relatórios,
 *    filtros e pelo SAFT; se o município passasse a viver noutra coluna, tudo
 *    isso via os endereços antigos para sempre. Em Angola a cidade passa a ser
 *    o município escolhido, e nada a jusante dá por isso.
 */
trait ConcordaComAMorada
{
    public function updatedCountry($valor): void
    {
        $this->country = Geografia::normalizarPais($valor) ?? Geografia::PAIS_PADRAO;

        if (!property_exists($this, 'province') || !$this->province) {
            $this->limparMunicipio();

            return;
        }

        // A província e o município são divisões ANGOLANAS, e é nos DOIS
        // sentidos que deixam de servir: sair de Angola com «Luanda» no campo
        // punha uma província angolana numa morada portuguesa — e ia assim
        // para a factura. Entrar em Angola com o que se escreveu à mão para
        // outro país é o mesmo problema ao contrário.
        $ehProvinciaAngolana = in_array(
            Geografia::normalizarProvincia($this->province), Geografia::provincias(), true
        );

        $serve = $this->country === Geografia::PAIS_PADRAO
            ? $ehProvinciaAngolana
            : !$ehProvinciaAngolana;

        if (!$serve) {
            $this->province = null;
            $this->limparMunicipio();
        }
    }

    public function updatedProvince($valor): void
    {
        if (strtoupper((string) $this->country) !== Geografia::PAIS_PADRAO) {
            return;
        }

        $this->province = Geografia::normalizarProvincia($valor);

        if (property_exists($this, 'municipality') && $this->municipality
            && !in_array($this->municipality, Geografia::municipios($this->province), true)) {
            $this->limparMunicipio();
        }
    }

    public function updatedMunicipality($valor): void
    {
        if (property_exists($this, 'city')) {
            $this->city = $valor ?: null;
        }

        if (property_exists($this, 'neighbourhood')) {
            $this->neighbourhood = null;
        }
    }

    private function limparMunicipio(): void
    {
        if (property_exists($this, 'municipality')) {
            $this->municipality = null;
        }

        if (property_exists($this, 'neighbourhood')) {
            $this->neighbourhood = null;
        }

        if (property_exists($this, 'city')) {
            $this->city = null;
        }
    }

    /**
     * As regras de validação da morada. Iguais em todo o lado — o país TEM de
     * ser um código que a AGT aceite, e não um texto qualquer.
     */
    protected function regrasDaMorada(bool $obrigatorio = false): array
    {
        $req = $obrigatorio ? 'required' : 'nullable';

        return [
            'address'      => [$req, 'string', 'max:255'],
            'country'      => ['required', 'string', 'size:2', new \App\Rules\PaisIso()],
            'province'     => [$req, 'string', 'max:100'],
            'municipality' => ['nullable', 'string', 'max:100'],
            'neighbourhood'=> ['nullable', 'string', 'max:100'],
            'city'         => ['nullable', 'string', 'max:100'],
            'postal_code'  => ['nullable', 'string', 'max:20'],
        ];
    }

    /** Os valores prontos a gravar, já normalizados. */
    protected function moradaParaGravar(): array
    {
        $pais = Geografia::normalizarPais($this->country) ?? Geografia::PAIS_PADRAO;
        $ehAo = $pais === Geografia::PAIS_PADRAO;

        $provincia = property_exists($this, 'province') ? trim((string) $this->province) : '';
        $municipio = property_exists($this, 'municipality') ? trim((string) $this->municipality) : '';

        return [
            'address'       => property_exists($this, 'address') ? (trim((string) $this->address) ?: null) : null,
            'country'       => $pais,
            'province'      => $provincia === '' ? null : ($ehAo ? Geografia::normalizarProvincia($provincia) : $provincia),
            'municipality'  => $municipio === '' ? null : $municipio,
            'neighbourhood' => property_exists($this, 'neighbourhood') ? (trim((string) $this->neighbourhood) ?: null) : null,
            // Em Angola a cidade é o município; fora dela é o que se escreveu.
            'city'          => $ehAo
                ? ($municipio ?: null)
                : (property_exists($this, 'city') ? (trim((string) $this->city) ?: null) : null),
            'postal_code'   => property_exists($this, 'postal_code') ? (trim((string) $this->postal_code) ?: null) : null,
        ];
    }
}
