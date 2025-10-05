<div class="p-6">
    {{-- Header --}}
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-3xl font-bold text-gray-800 flex items-center">
                    <i class="fas fa-chart-line mr-3 text-blue-600"></i>
                    Dashboard de Faturação
                </h2>
                <p class="text-gray-600 mt-1">Visão geral do módulo de faturação - {{ \Carbon\Carbon::now()->format('F Y') }}</p>
            </div>
            <div>
                <select wire:model.live="selectedPeriod" class="rounded-lg border-gray-300 shadow-sm">
                    <option value="week">Esta Semana</option>
                    <option value="month">Este Mês</option>
                    <option value="year">Este Ano</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Cards de Estatísticas Principais --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        {{-- Faturação do Mês --}}
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-blue-200 text-sm font-medium uppercase">Faturação do Mês</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-file-invoice-dollar text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-bold">{{ number_format($stats['total_invoiced'], 2) }}</p>
                <p class="text-blue-200 text-sm mt-1">AOA</p>
                @if($stats['growth'] > 0)
                    <p class="text-green-200 text-xs mt-2">
                        <i class="fas fa-arrow-up mr-1"></i>{{ number_format($stats['growth'], 1) }}% vs mês anterior
                    </p>
                @elseif($stats['growth'] < 0)
                    <p class="text-red-200 text-xs mt-2">
                        <i class="fas fa-arrow-down mr-1"></i>{{ number_format(abs($stats['growth']), 1) }}% vs mês anterior
                    </p>
                @else
                    <p class="text-blue-200 text-xs mt-2">
                        <i class="fas fa-minus mr-1"></i>Sem alteração
                    </p>
                @endif
            </div>
        </div>

        {{-- Recebimentos --}}
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-green-200 text-sm font-medium uppercase">Recebimentos</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-money-bill-wave text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-bold">{{ number_format($stats['total_received'], 2) }}</p>
                <p class="text-green-200 text-sm mt-1">AOA</p>
                <p class="text-green-200 text-xs mt-2">
                    <i class="fas fa-check-circle mr-1"></i>Pagamentos recebidos
                </p>
            </div>
        </div>

        {{-- Valores Pendentes --}}
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-yellow-200 text-sm font-medium uppercase">Valores Pendentes</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-hourglass-half text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-bold">{{ number_format($stats['total_pending'], 2) }}</p>
                <p class="text-yellow-200 text-sm mt-1">AOA</p>
                <p class="text-yellow-200 text-xs mt-2">
                    <i class="fas fa-clock mr-1"></i>Aguardando pagamento
                </p>
            </div>
        </div>

        {{-- Valores Vencidos --}}
        <div class="bg-gradient-to-br from-red-500 to-red-600 rounded-xl shadow-lg p-6 text-white transform hover:scale-105 transition">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <p class="text-red-200 text-sm font-medium uppercase">Valores Vencidos</p>
                </div>
                <div class="bg-white/20 p-3 rounded-full">
                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                </div>
            </div>
            <div>
                <p class="text-3xl font-bold">{{ number_format($stats['total_overdue'], 2) }}</p>
                <p class="text-red-200 text-sm mt-1">AOA</p>
                <p class="text-red-200 text-xs mt-2">
                    <i class="fas fa-bell mr-1"></i>Requer atenção
                </p>
            </div>
        </div>
    </div>

    {{-- Gráfico de Vendas --}}
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold text-gray-800 flex items-center">
                <i class="fas fa-chart-area mr-2 text-blue-600"></i>
                Evolução de Vendas - {{ ucfirst($selectedPeriod == 'week' ? 'Esta Semana' : ($selectedPeriod == 'year' ? 'Este Ano' : 'Este Mês')) }}
            </h3>
            <div class="flex gap-2">
                <button onclick="exportToPDF()" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition text-sm">
                    <i class="fas fa-file-pdf mr-2"></i>Exportar PDF
                </button>
                <button onclick="exportToExcel()" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                    <i class="fas fa-file-excel mr-2"></i>Excel
                </button>
            </div>
        </div>
        <canvas id="salesChart" height="80"></canvas>
    </div>

    {{-- Documentos e Gráfico --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        {{-- Documentos por Tipo --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-file-alt mr-2 text-blue-600"></i>
                Documentos Este Mês
            </h3>
            
            <div class="space-y-4">
                <div class="flex items-center justify-between p-3 bg-blue-50 rounded-lg hover:bg-blue-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Faturas</p>
                            <p class="text-xs text-gray-500">Vendas emitidas</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-blue-600">{{ $documents['invoices'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg hover:bg-green-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-green-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-receipt"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Recibos</p>
                            <p class="text-xs text-gray-500">Pagamentos</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-green-600">{{ $documents['receipts'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-emerald-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-minus-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Notas Crédito</p>
                            <p class="text-xs text-gray-500">Devoluções</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-emerald-600">{{ $documents['credit_notes'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg hover:bg-red-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-red-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Notas Débito</p>
                            <p class="text-xs text-gray-500">Cobranças extras</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-red-600">{{ $documents['debit_notes'] }}</span>
                </div>

                <div class="flex items-center justify-between p-3 bg-yellow-50 rounded-lg hover:bg-yellow-100 transition">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-yellow-600 rounded-full flex items-center justify-center text-white mr-3">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-700">Adiantamentos</p>
                            <p class="text-xs text-gray-500">Valores antecipados</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-yellow-600">{{ $documents['advances'] }}</span>
                </div>
            </div>
        </div>

        {{-- Status de Faturas --}}
        <div class="bg-white rounded-xl shadow-lg p-6 lg:col-span-2">
            <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
                <i class="fas fa-chart-pie mr-2 text-blue-600"></i>
                Status das Faturas
            </h3>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="text-center p-6 bg-gradient-to-br from-green-50 to-green-100 rounded-xl border-2 border-green-200">
                    <div class="text-5xl font-bold text-green-600 mb-2">{{ $invoiceStatus['paid'] }}</div>
                    <p class="text-sm font-medium text-green-700">Pagas</p>
                    <i class="fas fa-check-circle text-green-600 text-2xl mt-2"></i>
                </div>

                <div class="text-center p-6 bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-xl border-2 border-yellow-200">
                    <div class="text-5xl font-bold text-yellow-600 mb-2">{{ $invoiceStatus['pending'] }}</div>
                    <p class="text-sm font-medium text-yellow-700">Pendentes</p>
                    <i class="fas fa-clock text-yellow-600 text-2xl mt-2"></i>
                </div>

                <div class="text-center p-6 bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl border-2 border-blue-200">
                    <div class="text-5xl font-bold text-blue-600 mb-2">{{ $invoiceStatus['partially_paid'] }}</div>
                    <p class="text-sm font-medium text-blue-700">Parc. Pagas</p>
                    <i class="fas fa-coins text-blue-600 text-2xl mt-2"></i>
                </div>

                <div class="text-center p-6 bg-gradient-to-br from-red-50 to-red-100 rounded-xl border-2 border-red-200">
                    <div class="text-5xl font-bold text-red-600 mb-2">{{ $invoiceStatus['overdue'] }}</div>
                    <p class="text-sm font-medium text-red-700">Vencidas</p>
                    <i class="fas fa-exclamation-triangle text-red-600 text-2xl mt-2"></i>
                </div>
            </div>
        </div>
    </div>

    {{-- Comparação Ano a Ano --}}
    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-chart-bar mr-2 text-purple-600"></i>
            Comparação Ano a Ano
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg border-2 border-blue-200">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-blue-700">Faturação {{ now()->year }}</p>
                    <i class="fas fa-calendar-check text-blue-600"></i>
                </div>
                <p class="text-2xl font-bold text-blue-900">{{ number_format($stats['total_invoiced'], 2) }}</p>
                <p class="text-xs text-blue-600 mt-1">AOA</p>
            </div>

            <div class="p-4 bg-gradient-to-br from-gray-50 to-gray-100 rounded-lg border-2 border-gray-200">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-gray-700">Faturação {{ now()->year - 1 }}</p>
                    <i class="fas fa-calendar text-gray-600"></i>
                </div>
                <p class="text-2xl font-bold text-gray-900">{{ number_format($stats['total_invoiced_last_month'] * 12, 2) }}</p>
                <p class="text-xs text-gray-600 mt-1">AOA (estimado)</p>
            </div>

            <div class="p-4 bg-gradient-to-br from-{{ $stats['growth'] > 0 ? 'green' : 'red' }}-50 to-{{ $stats['growth'] > 0 ? 'green' : 'red' }}-100 rounded-lg border-2 border-{{ $stats['growth'] > 0 ? 'green' : 'red' }}-200">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-sm font-medium text-{{ $stats['growth'] > 0 ? 'green' : 'red' }}-700">Crescimento</p>
                    <i class="fas fa-{{ $stats['growth'] > 0 ? 'arrow-up' : 'arrow-down' }} text-{{ $stats['growth'] > 0 ? 'green' : 'red' }}-600"></i>
                </div>
                <p class="text-2xl font-bold text-{{ $stats['growth'] > 0 ? 'green' : 'red' }}-900">{{ number_format(abs($stats['growth']), 1) }}%</p>
                <p class="text-xs text-{{ $stats['growth'] > 0 ? 'green' : 'red' }}-600 mt-1">vs mês anterior</p>
            </div>
        </div>
    </div>

    {{-- Faturas Pendentes e Top Clientes --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        {{-- Faturas Pendentes --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-clock mr-2 text-yellow-600"></i>
                    Faturas Pendentes
                </h3>
                <a href="{{ route('invoicing.sales.invoices') }}" class="text-sm text-blue-600 hover:text-blue-800">
                    Ver todas <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                @forelse($pendingInvoices as $invoice)
                <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">{{ $invoice->invoice_number }}</p>
                        <p class="text-xs text-gray-600">{{ $invoice->client->name }}</p>
                        <p class="text-xs text-gray-500">
                            <i class="fas fa-calendar mr-1"></i>
                            Venc: {{ $invoice->due_date->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-800">{{ number_format($invoice->total, 2) }} AOA</p>
                        @if($invoice->due_date->isPast())
                            <span class="text-xs px-2 py-1 bg-red-100 text-red-700 rounded-full">Vencida</span>
                        @else
                            <span class="text-xs px-2 py-1 bg-yellow-100 text-yellow-700 rounded-full">Pendente</span>
                        @endif
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-check-circle text-5xl mb-3"></i>
                    <p>Nenhuma fatura pendente</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Top 5 Clientes --}}
        <div class="bg-white rounded-xl shadow-lg p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold text-gray-800 flex items-center">
                    <i class="fas fa-trophy mr-2 text-yellow-600"></i>
                    Top 5 Clientes
                </h3>
                <span class="text-xs text-gray-500">Por faturação</span>
            </div>

            <div class="space-y-3">
                @forelse($topClients as $index => $topClient)
                <div class="flex items-center p-3 bg-gradient-to-r from-blue-50 to-transparent rounded-lg">
                    <div class="w-10 h-10 bg-gradient-to-br from-blue-600 to-blue-700 rounded-full flex items-center justify-center text-white font-bold mr-3">
                        {{ $index + 1 }}
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-bold text-gray-800">{{ $topClient->client->name }}</p>
                        <p class="text-xs text-gray-600">{{ $topClient->invoice_count }} faturas</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-blue-600">{{ number_format($topClient->total_amount, 2) }}</p>
                        <p class="text-xs text-gray-500">AOA</p>
                    </div>
                </div>
                @empty
                <div class="text-center py-8 text-gray-400">
                    <i class="fas fa-users text-5xl mb-3"></i>
                    <p>Sem dados de clientes</p>
                </div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Atividades Recentes --}}
    <div class="bg-white rounded-xl shadow-lg p-6">
        <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center">
            <i class="fas fa-history mr-2 text-blue-600"></i>
            Atividades Recentes
        </h3>

        <div class="space-y-2 max-h-96 overflow-y-auto">
            @forelse($recentActivities as $activity)
            <div class="flex items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center text-blue-600 mr-3">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium text-gray-800">
                        Fatura <strong>{{ $activity->invoice_number }}</strong> criada
                    </p>
                    <p class="text-xs text-gray-600">
                        Cliente: {{ $activity->client->name }} • 
                        {{ $activity->created_at->diffForHumans() }}
                    </p>
                </div>
                <div class="text-right">
                    <span class="inline-flex items-center px-2 py-1 bg-{{ $activity->status_color }}-100 text-{{ $activity->status_color }}-700 text-xs rounded-full">
                        {{ $activity->status_label }}
                    </span>
                </div>
            </div>
            @empty
            <div class="text-center py-8 text-gray-400">
                <i class="fas fa-inbox text-5xl mb-3"></i>
                <p>Sem atividades recentes</p>
            </div>
            @endforelse
        </div>
    </div>
</div>

{{-- Scripts --}}
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dados do gráfico
    const chartData = @json($chartData);
    
    // Preparar dados para o Chart.js
    const labels = chartData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('pt-PT', { day: '2-digit', month: 'short' });
    });
    
    const data = chartData.map(item => parseFloat(item.total));
    
    // Criar gráfico
    const ctx = document.getElementById('salesChart');
    const salesChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Vendas (AOA)',
                data: data,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgb(59, 130, 246)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += new Intl.NumberFormat('pt-PT', {
                                    style: 'decimal',
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                }).format(context.parsed.y) + ' AOA';
                            }
                            return label;
                        }
                    },
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 12,
                    borderColor: 'rgba(59, 130, 246, 0.8)',
                    borderWidth: 2,
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return new Intl.NumberFormat('pt-PT', {
                                notation: 'compact',
                                compactDisplay: 'short'
                            }).format(value) + ' AOA';
                        }
                    },
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)',
                    }
                },
                x: {
                    grid: {
                        display: false,
                    }
                }
            }
        }
    });
});

// Função para exportar para PDF
async function exportToPDF() {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    
    // Título
    doc.setFontSize(18);
    doc.setTextColor(59, 130, 246);
    doc.text('Dashboard de Faturação', 15, 20);
    
    // Data
    doc.setFontSize(10);
    doc.setTextColor(100);
    const today = new Date().toLocaleDateString('pt-PT');
    doc.text(`Gerado em: ${today}`, 15, 28);
    
    // Capturar estatísticas
    const stats = {
        faturado: '{{ number_format($stats["total_invoiced"], 2) }} AOA',
        recebido: '{{ number_format($stats["total_received"], 2) }} AOA',
        pendente: '{{ number_format($stats["total_pending"], 2) }} AOA',
        vencido: '{{ number_format($stats["total_overdue"], 2) }} AOA'
    };
    
    doc.setFontSize(12);
    doc.setTextColor(0);
    let y = 40;
    
    doc.text(`Faturação do Mês: ${stats.faturado}`, 15, y);
    y += 8;
    doc.text(`Recebimentos: ${stats.recebido}`, 15, y);
    y += 8;
    doc.text(`Valores Pendentes: ${stats.pendente}`, 15, y);
    y += 8;
    doc.text(`Valores Vencidos: ${stats.vencido}`, 15, y);
    y += 15;
    
    // Capturar o gráfico
    const canvas = document.getElementById('salesChart');
    const imgData = canvas.toDataURL('image/png');
    doc.addImage(imgData, 'PNG', 15, y, 180, 90);
    
    // Salvar
    doc.save('dashboard-faturacao.pdf');
    
    // Notificação
    alert('✅ Relatório PDF gerado com sucesso!');
}

// Função para exportar para Excel (CSV)
function exportToExcel() {
    const chartData = @json($chartData);
    
    // Criar CSV
    let csv = 'Data,Valor (AOA)\n';
    chartData.forEach(item => {
        const date = new Date(item.date).toLocaleDateString('pt-PT');
        csv += `${date},${item.total}\n`;
    });
    
    // Adicionar estatísticas
    csv += '\nEstatísticas\n';
    csv += 'Faturação do Mês,{{ number_format($stats["total_invoiced"], 2) }}\n';
    csv += 'Recebimentos,{{ number_format($stats["total_received"], 2) }}\n';
    csv += 'Valores Pendentes,{{ number_format($stats["total_pending"], 2) }}\n';
    csv += 'Valores Vencidos,{{ number_format($stats["total_overdue"], 2) }}\n';
    
    // Download
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', 'dashboard-faturacao.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Notificação
    alert('✅ Relatório Excel exportado com sucesso!');
}

// Listener do Livewire para atualizar gráfico
document.addEventListener('livewire:initialized', () => {
    Livewire.on('chartUpdated', () => {
        location.reload();
    });
});
</script>
@endpush
