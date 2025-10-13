<?php

namespace App\Livewire\Accounting;

use Livewire\Component;
use Livewire\Attributes\Layout;
use App\Models\Accounting\Currency;
use App\Models\Accounting\ExchangeRate;

#[Layout('layouts.app')]
class CurrencyManagement extends Component
{
    public $showCurrencyModal = false;
    public $showRateModal = false;
    
    // Currency fields
    public $currencyId = null;
    public $code = '';
    public $name = '';
    public $symbol = '';
    
    // Exchange Rate fields
    public $currencyFromId = null;
    public $currencyToId = null;
    public $rateDate = null;
    public $rate = null;
    
    public function mount()
    {
        $this->rateDate = now()->format('Y-m-d');
    }
    
    public function editCurrency($id)
    {
        $currency = Currency::findOrFail($id);
        $this->currencyId = $currency->id;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->symbol = $currency->symbol;
        $this->showCurrencyModal = true;
    }
    
    public function saveCurrency()
    {
        $this->validate([
            'code' => 'required|max:3',
            'name' => 'required',
            'symbol' => 'required|max:10',
        ]);
        
        try {
            if ($this->currencyId) {
                $currency = Currency::findOrFail($this->currencyId);
                $currency->update([
                    'code' => strtoupper($this->code),
                    'name' => $this->name,
                    'symbol' => $this->symbol,
                ]);
                session()->flash('success', 'Moeda atualizada com sucesso!');
            } else {
                Currency::create([
                    'code' => strtoupper($this->code),
                    'name' => $this->name,
                    'symbol' => $this->symbol,
                    'decimal_places' => 2,
                    'is_active' => true,
                ]);
                session()->flash('success', 'Moeda criada com sucesso!');
            }
            
            $this->reset(['currencyId', 'code', 'name', 'symbol']);
            $this->showCurrencyModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }
    
    public function saveRate()
    {
        $this->validate([
            'currencyFromId' => 'required',
            'currencyToId' => 'required|different:currencyFromId',
            'rateDate' => 'required|date',
            'rate' => 'required|numeric|min:0',
        ]);
        
        try {
            ExchangeRate::updateOrCreate(
                [
                    'currency_from_id' => $this->currencyFromId,
                    'currency_to_id' => $this->currencyToId,
                    'date' => $this->rateDate,
                ],
                [
                    'rate' => $this->rate,
                    'source' => 'manual',
                ]
            );
            
            session()->flash('success', 'Taxa de câmbio salva com sucesso!');
            $this->reset(['currencyFromId', 'currencyToId', 'rate']);
            $this->rateDate = now()->format('Y-m-d');
            $this->showRateModal = false;
            
        } catch (\Exception $e) {
            session()->flash('error', 'Erro: ' . $e->getMessage());
        }
    }
    
    public function render()
    {
        $currencies = Currency::where('is_active', true)->orderBy('code')->get();
        
        $exchangeRates = ExchangeRate::with(['currencyFrom', 'currencyTo'])
            ->orderBy('date', 'desc')
            ->take(20)
            ->get();
        
        return view('livewire.accounting.currencies.currencies', [
            'currencies' => $currencies,
            'exchangeRates' => $exchangeRates,
        ]);
    }
}
