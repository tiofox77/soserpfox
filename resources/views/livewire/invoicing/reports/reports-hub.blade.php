<div>
    <!-- Header -->
    <div class="mb-6 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 rounded-2xl shadow-lg p-6 text-white">
        <div class="flex items-center">
            <div class="w-14 h-14 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-4">
                <i class="fas fa-chart-bar text-3xl"></i>
            </div>
            <div>
                <h2 class="text-2xl font-bold">{{ __('Relatórios de Faturação') }}</h2>
                <p class="text-indigo-100 text-sm">{{ __('Análises e mapas operacionais da gestão de faturação') }}</p>
            </div>
        </div>
    </div>
    {{-- As secções vêm do componente: Relatorios\Catalogo::paraAVistaDeSempre(). --}}
    @foreach($sections as $section)
        <div class="mb-8">
            <div class="flex items-center mb-4">
                <div class="w-10 h-10 bg-gradient-to-br from-{{ $section['color'] }}-500 to-{{ $section['color'] }}-700 rounded-xl flex items-center justify-center mr-3 shadow-lg">
                    <i class="fas {{ $section['icon'] }} text-white"></i>
                </div>
                <h3 class="text-xl font-bold text-gray-800">{{ $section['title'] }}</h3>
                <div class="flex-1 ml-4 h-px bg-gradient-to-r from-{{ $section['color'] }}-200 to-transparent"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($section['reports'] as $report)
                    <a href="{{ route($report['route']) }}" class="group bg-white rounded-2xl shadow-md hover:shadow-2xl transition-all duration-300 p-5 border border-gray-100 hover:border-{{ $section['color'] }}-300 hover:-translate-y-1">
                        <div class="flex items-start justify-between mb-3">
                            <div class="w-12 h-12 bg-gradient-to-br from-{{ $section['color'] }}-100 to-{{ $section['color'] }}-200 rounded-xl flex items-center justify-center group-hover:from-{{ $section['color'] }}-500 group-hover:to-{{ $section['color'] }}-700 transition-all">
                                <i class="fas {{ $report['icon'] }} text-{{ $section['color'] }}-600 group-hover:text-white transition"></i>
                            </div>
                            <i class="fas fa-arrow-right text-gray-300 group-hover:text-{{ $section['color'] }}-500 group-hover:translate-x-1 transition-all"></i>
                        </div>
                        <h4 class="font-bold text-gray-900 group-hover:text-{{ $section['color'] }}-700 mb-1">{{ $report['name'] }}</h4>
                        <p class="text-xs text-gray-500 leading-relaxed">{{ $report['desc'] }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
