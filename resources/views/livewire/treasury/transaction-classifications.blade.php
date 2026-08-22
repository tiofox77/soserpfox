<div class="space-y-6">
    <div class="rounded-2xl bg-gradient-to-r from-teal-600 to-cyan-600 p-6 text-white shadow-lg">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold"><i class="fas {{ $kind === 'type' ? 'fa-list' : 'fa-tags' }} mr-2"></i>{{ $kind === 'type' ? 'Tipos de Transação' : 'Categorias de Transação' }}</h1>
                <p class="mt-1 text-sm text-cyan-50">{{ $kind === 'type' ? 'Defina os tipos e indique se movimentam dinheiro como entrada, saída ou transferência.' : 'Organize a finalidade dos movimentos e, opcionalmente, associe cada categoria a um tipo.' }}</p>
            </div>
            <button wire:click="create" class="rounded-xl bg-white px-5 py-3 font-bold text-teal-700 shadow hover:bg-cyan-50"><i class="fas fa-plus mr-2"></i>Novo {{ $kind === 'type' ? 'Tipo' : 'Categoria' }}</button>
        </div>
    </div>

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
        <i class="fas fa-info-circle mr-2"></i>Os exemplos padrão já estão disponíveis. Pode criar outros, editar nomes ou desativar os que não usa. Registos com histórico nunca são destruídos.
    </div>

    <div class="rounded-2xl bg-white p-5 shadow">
        <input wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-2 border-gray-200 px-4 py-3 focus:border-teal-500" placeholder="Pesquisar por nome ou código...">
    </div>

    <div class="overflow-hidden rounded-2xl bg-white shadow">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-xs font-bold uppercase text-gray-500">Nome / código</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-gray-500">{{ $kind === 'type' ? 'Natureza financeira' : 'Tipo associado' }}</th><th class="px-5 py-3 text-left text-xs font-bold uppercase text-gray-500">Estado</th><th class="px-5 py-3 text-right text-xs font-bold uppercase text-gray-500">Ações</th></tr></thead>
                <tbody class="divide-y divide-gray-100">
                @forelse($records as $record)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-4"><div class="font-semibold text-gray-900">{{ $record->name }}</div><div class="text-xs text-gray-500">{{ $record->code }}</div></td>
                        <td class="px-5 py-4 text-sm text-gray-700">{{ $kind === 'type' ? ['income'=>'Entrada','expense'=>'Saída','transfer'=>'Transferência'][$record->nature] : ($record->transactionType?->name ?? 'Todas') }}</td>
                        <td class="px-5 py-4"><button wire:click="toggleStatus({{ $record->id }})" class="rounded-full px-3 py-1 text-xs font-bold {{ $record->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">{{ $record->is_active ? 'Ativo' : 'Inativo' }}</button></td>
                        <td class="px-5 py-4 text-right"><button wire:click="edit({{ $record->id }})" class="mr-2 text-blue-600 hover:text-blue-800"><i class="fas fa-edit"></i></button><button wire:click="confirmDelete({{ $record->id }})" class="text-red-600 hover:text-red-800"><i class="fas fa-trash"></i></button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-gray-500">Nenhum registo encontrado.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t px-5 py-4">{{ $records->links() }}</div>
    </div>

    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4" wire:click.self="closeModal">
        <form wire:submit="save" class="w-full max-w-xl overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-center justify-between bg-gradient-to-r from-teal-600 to-cyan-600 px-6 py-4 text-white"><h2 class="text-lg font-bold">{{ $recordId ? 'Editar' : 'Novo' }} {{ $kind === 'type' ? 'Tipo' : 'Categoria' }}</h2><button type="button" wire:click="closeModal"><i class="fas fa-times text-xl"></i></button></div>
            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">
                <label class="text-sm font-semibold text-gray-700">Nome*<input wire:model="form.name" class="mt-2 w-full rounded-xl border-2 border-gray-200 px-4 py-3"></label>
                <label class="text-sm font-semibold text-gray-700">Código*<input wire:model="form.code" class="mt-2 w-full rounded-xl border-2 border-gray-200 px-4 py-3" placeholder="ex.: servicos"></label>
                @if($kind === 'type')
                    <label class="text-sm font-semibold text-gray-700">Natureza financeira*<select wire:model="form.nature" class="mt-2 w-full rounded-xl border-2 border-gray-200 px-4 py-3"><option value="income">Entrada</option><option value="expense">Saída</option><option value="transfer">Transferência</option></select></label>
                    <label class="text-sm font-semibold text-gray-700">Cor<select wire:model="form.color" class="mt-2 w-full rounded-xl border-2 border-gray-200 px-4 py-3"><option value="green">Verde</option><option value="red">Vermelho</option><option value="blue">Azul</option><option value="orange">Laranja</option><option value="purple">Roxo</option></select></label>
                @else
                    <label class="text-sm font-semibold text-gray-700 sm:col-span-2">Tipo associado<select wire:model="form.transaction_type_id" class="mt-2 w-full rounded-xl border-2 border-gray-200 px-4 py-3"><option value="">Disponível para todos</option>@foreach($types as $type)<option value="{{ $type->id }}">{{ $type->name }}</option>@endforeach</select></label>
                @endif
                <label class="text-sm font-semibold text-gray-700 sm:col-span-2">Descrição<textarea wire:model="form.description" rows="3" class="mt-2 w-full rounded-xl border-2 border-gray-200 px-4 py-3"></textarea></label>
                <label class="flex items-center gap-3 text-sm font-semibold text-gray-700"><input type="checkbox" wire:model="form.is_active" class="rounded text-teal-600">Ativo</label>
                @error('form.*')<div class="sm:col-span-2 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $message }}</div>@enderror
            </div>
            <div class="flex justify-end gap-3 bg-gray-50 px-6 py-4"><button type="button" wire:click="closeModal" class="rounded-xl bg-gray-200 px-5 py-2.5 font-semibold">Cancelar</button><button class="rounded-xl bg-teal-600 px-5 py-2.5 font-bold text-white">Guardar</button></div>
        </form>
    </div>
    @endif

    @if($showDeleteModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/60 p-4"><div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl"><h3 class="text-lg font-bold">Eliminar {{ $recordToDelete }}?</h3><p class="mt-2 text-sm text-gray-600">Se já estiver em uso, será apenas desativado para não danificar o histórico.</p><div class="mt-6 flex justify-end gap-3"><button wire:click="closeDeleteModal" class="rounded-xl bg-gray-200 px-4 py-2">Cancelar</button><button wire:click="deleteRecord" class="rounded-xl bg-red-600 px-4 py-2 font-bold text-white">Confirmar</button></div></div></div>
    @endif
</div>
