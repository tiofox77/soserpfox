{{-- Toast Notifications --}}
<div x-data="{ show: false, message: '', type: 'success' }"
     x-on:notify.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, 4000)"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 transform translate-y-2"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-y-0"
     x-transition:leave-end="opacity-0 transform translate-y-2"
     class="fixed bottom-4 right-4 z-50">
    <div :class="type === 'error' ? 'bg-red-500' : 'bg-green-500'" class="px-6 py-3 rounded-xl text-white shadow-lg flex items-center gap-2">
        <i :class="type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle'"></i>
        <span x-text="message"></span>
    </div>
</div>
