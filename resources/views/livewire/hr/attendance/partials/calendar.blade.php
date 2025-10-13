{{-- Header --}}
<div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
    <h3 class="text-white font-bold text-lg flex items-center">
        <i class="fas fa-calendar-alt mr-2"></i>
        Calendário de Presenças
    </h3>
</div>

{{-- Calendar Body --}}
<div class="p-6">
    <div id="attendance-calendar" wire:ignore></div>
</div>

{{-- Legend --}}
<div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-gray-700 mb-2">Legenda de Status:</p>
            <div class="flex flex-wrap gap-3">
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-green-500 mr-1"></span>
                    Presente
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-red-500 mr-1"></span>
                    Ausente
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-yellow-500 mr-1"></span>
                    Atrasado
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-blue-500 mr-1"></span>
                    Meio Período
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-purple-500 mr-1"></span>
                    Doente
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-indigo-500 mr-1"></span>
                    Férias
                </span>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.10/locales/pt-br.global.min.js'></script>
<style>
    /* FullCalendar Custom Styles */
    #attendance-calendar {
        font-family: inherit;
    }
    
    .fc .fc-toolbar-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #1f2937 !important;
    }
    
    .fc .fc-button {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%) !important;
        border: none !important;
        border-radius: 0.5rem !important;
        padding: 0.5rem 1rem !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
    }
    
    .fc .fc-button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 20px rgba(5, 150, 105, 0.3) !important;
    }
    
    .fc .fc-button-active {
        background: linear-gradient(135deg, #047857 0%, #059669 100%) !important;
    }
    
    .fc-event {
        border-radius: 0.375rem !important;
        padding: 2px 4px !important;
        font-size: 0.75rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        transition: all 0.2s ease !important;
    }
    
    .fc-event:hover {
        transform: scale(1.05) !important;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15) !important;
    }
    
    .fc-day-today {
        background-color: rgba(5, 150, 105, 0.1) !important;
    }
    
    .fc-day-today .fc-daygrid-day-number {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white !important;
        border-radius: 50%;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
    }
    
    .fc-col-header-cell {
        background: linear-gradient(135deg, rgba(5, 150, 105, 0.1) 0%, rgba(16, 185, 129, 0.1) 100%) !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        font-size: 0.75rem !important;
        color: #047857 !important;
        padding: 1rem !important;
    }
    
    .fc-daygrid-day-number {
        padding: 0.5rem !important;
        font-weight: 600 !important;
    }
</style>

<script>
document.addEventListener('livewire:initialized', () => {
    console.log('🚀 Livewire inicializado - Preparando calendário de presenças');
    let attendanceCalendar;
    
    function initAttendanceCalendar() {
        console.log('📅 Tentando inicializar calendário de presenças...');
        const calendarEl = document.getElementById('attendance-calendar');
        
        if (!calendarEl) {
            console.error('❌ Elemento #attendance-calendar não encontrado!');
            return;
        }
        
        if (typeof FullCalendar === 'undefined') {
            console.error('❌ FullCalendar não está carregado!');
            return;
        }
        
        console.log('✅ Elemento encontrado, inicializando FullCalendar...');

        attendanceCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            locale: 'pt-br',
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                week: 'Semana',
                day: 'Dia'
            },
            height: 'auto',
            editable: false,
            displayEventEnd: true,
            eventTimeFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            slotLabelFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            },
            events: function(info, successCallback, failureCallback) {
                @this.call('getCalendarEvents', info.startStr, info.endStr)
                    .then(events => {
                        successCallback(events);
                    })
                    .catch(error => {
                        console.error('Erro ao carregar eventos:', error);
                        failureCallback(error);
                    });
            },
            eventDidMount: function(info) {
                const props = info.event.extendedProps;
                info.el.title = `${info.event.title}\n` +
                               `🕐 Entrada: ${props.check_in || 'N/A'}\n` +
                               `🕐 Saída: ${props.check_out || 'N/A'}\n` +
                               `⏱️ Horas: ${props.hours_worked || '0'}h\n` +
                               `📊 Status: ${props.status_label}`;
            },
            eventClick: function(info) {
                @this.viewDetails(info.event.id);
            },
            dateClick: function(info) {
                @this.set('date', info.dateStr);
                @this.create();
            }
        });

        try {
            attendanceCalendar.render();
            console.log('✅ Calendário de presenças renderizado com sucesso!');
        } catch (error) {
            console.error('❌ Erro ao renderizar calendário:', error);
        }
    }

    // Verificar se deve inicializar imediatamente (se a view já for calendar)
    setTimeout(() => {
        const calendarEl = document.getElementById('attendance-calendar');
        if (calendarEl && calendarEl.offsetParent !== null) {
            console.log('🎯 View já é calendário, inicializando imediatamente...');
            initAttendanceCalendar();
        } else {
            console.log('⏸️ Aguardando mudança de view para inicializar calendário...');
        }
    }, 300);

    // Listener para refresh de eventos
    Livewire.on('refreshAttendanceCalendar', () => {
        if (attendanceCalendar) {
            attendanceCalendar.refetchEvents();
        }
    });
    
    // Listener para mudança de view
    window.addEventListener('render-attendance-calendar', () => {
        console.log('🔔 Evento render-attendance-calendar recebido!');
        setTimeout(() => {
            const calendarEl = document.getElementById('attendance-calendar');
            console.log('📍 Elemento encontrado:', !!calendarEl);
            console.log('👁️ Elemento visível:', calendarEl ? calendarEl.offsetParent !== null : false);
            
            if (calendarEl && calendarEl.offsetParent !== null) {
                if (attendanceCalendar) {
                    try {
                        attendanceCalendar.render();
                        console.log('✅ Calendário de presenças re-renderizado!');
                    } catch (error) {
                        console.error('❌ Erro ao re-renderizar:', error);
                    }
                } else {
                    console.log('🔄 Calendário não existe, inicializando...');
                    initAttendanceCalendar();
                }
            } else {
                console.warn('⚠️ Elemento não está visível ou não existe!');
            }
        }, 100);
    });
});
console.log('✅ Script de calendário de presenças carregado!');
</script>
@endpush
