@extends('layouts.pwa', ['title' => 'Novo Cliente (offline)'])

@section('content')
<div x-data="clientForm()" x-cloak>
    <div class="mb-4 flex items-center gap-3">
        <a href="{{ route('invoicing.offline.clients') }}" class="w-9 h-9 bg-white rounded-lg shadow flex items-center justify-center text-gray-600 hover:bg-gray-50">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <h1 class="text-xl font-bold text-gray-900">Novo Cliente</h1>
            <p class="text-xs text-gray-500">Guarda local + sincroniza ao reconectar</p>
        </div>
    </div>

    <form @submit.prevent="save()" class="bg-white rounded-2xl shadow-lg p-5 space-y-4">
        {{-- Tipo --}}
        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Tipo de Cliente</label>
            <div class="grid grid-cols-2 gap-2">
                <button type="button" @click="form.type = 'pessoa_fisica'" :class="form.type === 'pessoa_fisica' ? 'bg-purple-600 text-white border-purple-600' : 'bg-white text-gray-600 border-gray-200'" class="border-2 rounded-xl py-3 text-sm font-bold transition">
                    <i class="fas fa-user block mb-1"></i>Singular
                </button>
                <button type="button" @click="form.type = 'pessoa_juridica'" :class="form.type === 'pessoa_juridica' ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200'" class="border-2 rounded-xl py-3 text-sm font-bold transition">
                    <i class="fas fa-building block mb-1"></i>Empresa
                </button>
            </div>
        </div>

        {{-- Nome --}}
        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Nome <span class="text-red-500">*</span></label>
            <input x-model="form.name" type="text" required maxlength="255"
                   class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm"
                   placeholder="Nome ou Designação Social">
        </div>

        {{-- NIF --}}
        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">NIF / BI</label>
            <input x-model="form.nif" type="text" maxlength="50" inputmode="numeric"
                   class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm"
                   placeholder="Ex: 5417654321">
            <p class="text-xs text-gray-400 mt-1">Recomendado para faturas legais</p>
        </div>

        {{-- Contactos --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Telefone</label>
                <input x-model="form.phone" type="tel" maxlength="50"
                       class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Telemóvel</label>
                <input x-model="form.mobile" type="tel" maxlength="50"
                       class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Email</label>
            <input x-model="form.email" type="email" maxlength="255"
                   class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
        </div>

        {{-- Localização --}}
        <div>
            <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Endereço</label>
            <textarea x-model="form.address" rows="2" maxlength="500"
                   class="w-full px-4 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm"></textarea>
        </div>

        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Cidade</label>
                <input x-model="form.city" type="text" maxlength="120"
                       class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Província</label>
                <select x-model="form.province" class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
                    <option value="">—</option>
                    <template x-for="p in provinces" :key="p">
                        <option :value="p" x-text="p"></option>
                    </template>
                </select>
            </div>
        </div>

        {{-- Regime fiscal --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-bold text-gray-600 uppercase mb-1">Regime Fiscal</label>
                <select x-model="form.tax_regime" class="w-full px-3 py-2.5 border-2 border-gray-200 rounded-xl focus:border-blue-500 focus:outline-none text-sm">
                    <option value="regime_geral">Regime Geral</option>
                    <option value="regime_simplificado">Simplificado</option>
                    <option value="regime_exclusao">Exclusão</option>
                    <option value="nao_sujeito">Não Sujeito</option>
                </select>
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm">
                    <input x-model="form.is_iva_subject" type="checkbox" class="w-5 h-5 rounded text-blue-600">
                    <span class="font-semibold text-gray-700">Sujeito a IVA</span>
                </label>
            </div>
        </div>

        {{-- Alerta offline --}}
        <div x-show="!online" class="bg-amber-50 border-l-4 border-amber-500 p-3 rounded-lg text-xs text-amber-900">
            <i class="fas fa-wifi-slash mr-1"></i>
            <strong>Sem conexão.</strong> O cliente será guardado localmente e enviado ao servidor automaticamente quando voltar online.
        </div>

        <div class="flex gap-3 pt-2">
            <a href="{{ route('invoicing.offline.clients') }}" class="flex-1 text-center py-3 border-2 border-gray-300 text-gray-700 rounded-xl font-bold text-sm hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" :disabled="saving" class="flex-1 bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 rounded-xl font-bold text-sm shadow-lg hover:shadow-xl transition disabled:opacity-60">
                <i class="fas fa-save mr-1" x-show="!saving"></i>
                <i class="fas fa-spinner fa-spin mr-1" x-show="saving"></i>
                <span x-text="saving ? 'A guardar...' : 'Guardar Cliente'"></span>
            </button>
        </div>
    </form>

    {{-- Toast de sucesso --}}
    <div x-show="successMsg" x-transition class="fixed bottom-24 inset-x-3 z-50 bg-emerald-600 text-white px-4 py-3 rounded-xl shadow-2xl">
        <i class="fas fa-check-circle mr-2"></i><span x-text="successMsg"></span>
    </div>
</div>

@push('scripts')
<script>
function clientForm() {
    return {
        online: navigator.onLine,
        saving: false,
        successMsg: '',
        form: {
            type: 'pessoa_fisica',
            name: '',
            nif: '',
            email: '',
            phone: '',
            mobile: '',
            address: '',
            city: '',
            province: '',
            country: 'Angola',
            tax_regime: 'regime_geral',
            is_iva_subject: false,
        },
        provinces: [
            'Bengo', 'Benguela', 'Bié', 'Cabinda', 'Cuando Cubango', 'Cuanza Norte',
            'Cuanza Sul', 'Cunene', 'Huambo', 'Huíla', 'Luanda', 'Lunda Norte',
            'Lunda Sul', 'Malanje', 'Moxico', 'Namibe', 'Uíge', 'Zaire'
        ],

        async save() {
            if (this.saving) return;
            if (!this.form.name.trim()) {
                alert('Nome obrigatório');
                return;
            }
            this.saving = true;

            try {
                // 1) Guarda local sempre (mesmo se online — segurança)
                const record = await window.SosPwa.createClientOffline({ ...this.form });

                // 2) Feedback
                this.successMsg = navigator.onLine
                    ? 'Cliente guardado — a sincronizar com o servidor...'
                    : 'Cliente guardado localmente. Será enviado quando voltar online.';

                // 3) Redirect após 1.5s
                setTimeout(() => {
                    window.location.href = '{{ route("invoicing.offline.clients") }}';
                }, 1500);
            } catch (err) {
                console.error(err);
                alert('Erro ao guardar: ' + err.message);
                this.saving = false;
            }
        },
    };
}

window.addEventListener('online', () => { if (window.Alpine) Alpine.store; });
</script>
@endpush
@endsection
