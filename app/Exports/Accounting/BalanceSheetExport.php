<?php

namespace App\Exports\Accounting;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BalanceSheetExport implements FromCollection, WithHeadings, WithStyles, WithTitle
{
    protected $data;
    protected $date;
    
    public function __construct($data, $date)
    {
        $this->data = $data;
        $this->date = $date;
    }
    
    public function collection()
    {
        $rows = collect();
        
        // ACTIVO
        $rows->push(['ACTIVO', '', '']);
        $rows->push(['', '', '']);
        $rows->push(['ACTIVO CORRENTE', '', '']);
        $rows->push(['Caixa e Bancos', $this->data['assets']['current']['cash_bank'], '']);
        $rows->push(['Clientes', $this->data['assets']['current']['clients'], '']);
        $rows->push(['Inventários', $this->data['assets']['current']['inventory'], '']);
        $rows->push(['Outros Activos Correntes', $this->data['assets']['current']['other'], '']);
        $rows->push(['TOTAL ACTIVO CORRENTE', $this->data['assets']['current']['total'], '']);
        $rows->push(['', '', '']);
        
        $rows->push(['ACTIVO NÃO CORRENTE', '', '']);
        $rows->push(['Investimentos Financeiros', $this->data['assets']['non_current']['investments'], '']);
        $rows->push(['Imobilizado Corpóreo', $this->data['assets']['non_current']['fixed_assets'], '']);
        $rows->push(['Activos Intangíveis', $this->data['assets']['non_current']['intangible'], '']);
        $rows->push(['TOTAL ACTIVO NÃO CORRENTE', $this->data['assets']['non_current']['total'], '']);
        $rows->push(['', '', '']);
        
        $rows->push(['TOTAL ACTIVO', $this->data['assets']['total'], '']);
        $rows->push(['', '', '']);
        
        // PASSIVO
        $rows->push(['PASSIVO', '', '']);
        $rows->push(['', '', '']);
        $rows->push(['PASSIVO CORRENTE', '', '']);
        $rows->push(['Fornecedores', $this->data['liabilities']['current']['suppliers'], '']);
        $rows->push(['Estado e Outros Entes Públicos', $this->data['liabilities']['current']['state'], '']);
        $rows->push(['Outros Passivos Correntes', $this->data['liabilities']['current']['other'], '']);
        $rows->push(['TOTAL PASSIVO CORRENTE', $this->data['liabilities']['current']['total'], '']);
        $rows->push(['', '', '']);
        
        $rows->push(['PASSIVO NÃO CORRENTE', '', '']);
        $rows->push(['Empréstimos Obtidos', $this->data['liabilities']['non_current']['loans'], '']);
        $rows->push(['Financiamentos Obtidos', $this->data['liabilities']['non_current']['financing'], '']);
        $rows->push(['Provisões', $this->data['liabilities']['non_current']['provisions'], '']);
        $rows->push(['TOTAL PASSIVO NÃO CORRENTE', $this->data['liabilities']['non_current']['total'], '']);
        $rows->push(['', '', '']);
        
        $rows->push(['TOTAL PASSIVO', $this->data['liabilities']['total'], '']);
        $rows->push(['', '', '']);
        
        // CAPITAL PRÓPRIO
        $rows->push(['CAPITAL PRÓPRIO', '', '']);
        $rows->push(['', '', '']);
        $rows->push(['Capital Realizado', $this->data['equity']['capital'], '']);
        $rows->push(['Reservas', $this->data['equity']['reserves'], '']);
        $rows->push(['Resultados Transitados', $this->data['equity']['retained'], '']);
        $rows->push(['Resultado Líquido do Período', $this->data['equity']['net_income'], '']);
        $rows->push(['TOTAL CAPITAL PRÓPRIO', $this->data['equity']['total'], '']);
        $rows->push(['', '', '']);
        
        $rows->push(['TOTAL PASSIVO + CAPITAL PRÓPRIO', $this->data['liabilities']['total'] + $this->data['equity']['total'], '']);
        
        return $rows;
    }
    
    public function headings(): array
    {
        return [
            ['BALANÇO (POSIÇÃO FINANCEIRA)'],
            ['Data: ' . date('d/m/Y', strtotime($this->date))],
            ['SNC Angola - Valores em Kwanzas (Kz)'],
            [''],
            ['Rubrica', 'Valor', 'Notas'],
        ];
    }
    
    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            2 => ['font' => ['italic' => true]],
            3 => ['font' => ['italic' => true]],
            5 => ['font' => ['bold' => true]],
        ];
    }
    
    public function title(): string
    {
        return 'Balanço';
    }
}
