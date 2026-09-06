<?php

namespace App\Services\POS;

use DomainException;
use Illuminate\Http\UploadedFile;

/**
 * Lê o ficheiro de uma cópia de segurança do PWA, e diz o que lá está.
 *
 * O ficheiro é relido a cada passo em vez de ficar guardado: uma cópia de
 * alguns MB não pode viajar em cada pedido. Aqui só se abre e se valida; a
 * importação a sério é da `ImportacaoDeCopiaOffline`.
 */
class LeituraDeCopiaOffline
{
    /** 20 MB: uma fila de milhares de vendas cabe bem abaixo disto. */
    public const TECTO_KB = 20480;

    public function __construct(private readonly ImportacaoDeCopiaOffline $importacao)
    {
    }

    /** O JSON do ficheiro, ou a razão de não servir. */
    public function ler(?UploadedFile $ficheiro): array
    {
        if (!$ficheiro || !$ficheiro->isValid() || $ficheiro->getSize() > self::TECTO_KB * 1024) {
            throw new DomainException('Escolha um ficheiro .json com menos de 20 MB.');
        }

        $copia = json_decode((string) file_get_contents($ficheiro->getRealPath()), true);

        if (!is_array($copia)) {
            throw new DomainException('O ficheiro não é um JSON válido. Não foi alterado à mão, pois não?');
        }

        return $copia;
    }

    /** Lê, valida contra a empresa e devolve o inventário — sem gravar nada. */
    public function analisar(?UploadedFile $ficheiro, int $tenantId): array
    {
        $copia = $this->ler($ficheiro);

        if ($problema = $this->importacao->validar($copia, $tenantId)) {
            throw new DomainException($problema);
        }

        return $this->importacao->inventario($copia);
    }

    /** Lê, valida e importa. Devolve o resumo da importação. */
    public function importar(?UploadedFile $ficheiro, int $tenantId, int $userId): array
    {
        $copia = $this->ler($ficheiro);

        if ($problema = $this->importacao->validar($copia, $tenantId)) {
            throw new DomainException($problema);
        }

        return $this->importacao->importar($copia, $tenantId, $userId);
    }
}
