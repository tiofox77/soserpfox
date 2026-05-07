<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Check-in Expresso - {{ $reservation->reservation_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="min-h-screen bg-gradient-to-br from-purple-600 via-indigo-600 to-blue-700 flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl shadow-2xl max-w-lg w-full p-8">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-purple-500 to-indigo-600 rounded-full mb-4">
                <i class="fas fa-hotel text-white text-3xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-gray-800">{{ $settings->hotel_name ?? 'Hotel' }}</h1>
            <p class="text-gray-500 text-sm mt-1">Check-in Expresso</p>
        </div>

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-xl mb-4">
                <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
            </div>
        @endif

        <div class="bg-gray-50 rounded-2xl p-5 mb-6 space-y-3">
            <div class="flex justify-between">
                <span class="text-gray-500 text-sm">Reserva</span>
                <span class="font-bold text-gray-800">{{ $reservation->reservation_number }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 text-sm">Hóspede</span>
                <span class="font-bold text-gray-800">{{ $reservation->guest?->name ?? $reservation->client?->name ?? '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 text-sm">Check-in</span>
                <span class="font-bold text-gray-800">{{ \Carbon\Carbon::parse($reservation->check_in_date)->format('d/m/Y') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 text-sm">Check-out</span>
                <span class="font-bold text-gray-800">{{ \Carbon\Carbon::parse($reservation->check_out_date)->format('d/m/Y') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500 text-sm">Quarto</span>
                <span class="font-bold text-gray-800">{{ $reservation->room?->room_number ? 'Nº ' . $reservation->room->room_number : 'A atribuir' }} · {{ $reservation->roomType?->name ?? '' }}</span>
            </div>
        </div>

        @if($alreadyCheckedIn)
            <div class="bg-emerald-100 border-2 border-emerald-400 text-emerald-800 p-4 rounded-2xl text-center">
                <i class="fas fa-check-circle text-4xl mb-2"></i>
                <h3 class="font-bold text-lg">Check-in já efectuado!</h3>
                <p class="text-sm mt-1">Desejamos-lhe uma óptima estadia.</p>
            </div>
        @else
            <form method="POST" action="{{ url('/hotel/reservations/' . $reservation->id . '/checkin/' . $reservation->confirmation_code . '/confirm') }}">
                @csrf
                <button type="submit"
                        class="w-full py-4 bg-gradient-to-r from-purple-600 to-indigo-600 hover:from-purple-700 hover:to-indigo-700 text-white font-bold rounded-2xl text-lg shadow-lg transition">
                    <i class="fas fa-check mr-2"></i>Confirmar Check-in
                </button>
            </form>
            <p class="text-center text-xs text-gray-400 mt-4">
                Ao confirmar, a sua reserva ficará com estado Check-in.
            </p>
        @endif

        @if($settings->hotel_phone)
            <div class="mt-6 pt-6 border-t border-gray-200 text-center text-sm text-gray-500">
                <i class="fas fa-phone mr-1"></i>{{ $settings->hotel_phone }}
                @if($settings->hotel_email)
                    <span class="mx-2">·</span><i class="fas fa-envelope mr-1"></i>{{ $settings->hotel_email }}
                @endif
            </div>
        @endif
    </div>
</body>
</html>
