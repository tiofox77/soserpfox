{{-- Toast Notifications --}}
<div x-data="{ 
        show: false, 
        message: '', 
        type: 'success',
        init() {
            Livewire.on('notify', (data) => {
                this.show = true;
                this.message = data.message || data[0]?.message;
                this.type = data.type || data[0]?.type || 'success';
                setTimeout(() => this.show = false, 4000);
            });
        }
     }"
     x-show="show"
     x-transition:enter="transform ease-out duration-300"
     x-transition:enter-start="translate-y-2 opacity-0"
     x-transition:enter-end="translate-y-0 opacity-100"
     x-transition:leave="transform ease-in duration-200"
     x-transition:leave-start="translate-y-0 opacity-100"
     x-transition:leave-end="translate-y-2 opacity-0"
     x-cloak
     class="fixed bottom-6 right-6 z-50">
    <div :class="{
            'bg-green-500': type === 'success',
            'bg-red-500': type === 'error',
            'bg-yellow-500': type === 'warning',
            'bg-blue-500': type === 'info'
         }"
         class="px-6 py-4 rounded-xl shadow-2xl text-white flex items-center gap-3 min-w-[300px]">
        <div class="w-8 h-8 bg-white/20 rounded-full flex items-center justify-center">
            <template x-if="type === 'success'"><i class="fas fa-check"></i></template>
            <template x-if="type === 'error'"><i class="fas fa-times"></i></template>
            <template x-if="type === 'warning'"><i class="fas fa-exclamation"></i></template>
            <template x-if="type === 'info'"><i class="fas fa-info"></i></template>
        </div>
        <span x-text="message" class="font-medium"></span>
        <button @click="show = false" class="ml-auto hover:bg-white/20 rounded-lg p-1 transition">
            <i class="fas fa-times"></i>
        </button>
    </div>
</div>

{{-- Script para copiar URL --}}
<script>
    document.addEventListener('livewire:init', function () {
        Livewire.on('copyToClipboard', (data) => {
            const url = data.url || data[0]?.url;
            if (url) {
                navigator.clipboard.writeText(url);
            }
        });
    });
</script>
