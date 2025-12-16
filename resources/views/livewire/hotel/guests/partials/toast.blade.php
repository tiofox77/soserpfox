{{-- Toast Notification --}}
<div x-data="{ show: false, message: '', type: 'success' }"
     x-on:notify.window="show = true; message = $event.detail.message; type = $event.detail.type; setTimeout(() => show = false, 3000)"
     x-show="show"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="opacity-0 translate-y-2"
     x-transition:enter-end="opacity-100 translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 translate-y-0"
     x-transition:leave-end="opacity-0 translate-y-2"
     x-cloak
     class="fixed bottom-4 right-4 z-50">
    <div :class="type === 'success' ? 'bg-green-500' : 'bg-red-500'" class="text-white px-6 py-3 rounded-xl shadow-lg flex items-center gap-3">
        <i :class="type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle'" class="text-xl"></i>
        <span class="font-medium" x-text="message"></span>
    </div>
</div>
