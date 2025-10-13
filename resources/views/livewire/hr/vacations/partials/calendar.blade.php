{{-- Header --}}
<div class="bg-gradient-to-r from-purple-600 to-pink-600 px-6 py-4">
    <h3 class="text-white font-bold text-lg flex items-center">
        <i class="fas fa-calendar-alt mr-2"></i>
        Calendário de Férias
    </h3>
</div>

{{-- Calendar Body --}}
<div class="p-6">
    <div id="vacation-calendar" wire:ignore></div>
</div>

{{-- Legend --}}
<div class="px-6 py-4 bg-gray-50 border-t border-gray-200">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-sm font-bold text-gray-700 mb-2">Legenda de Status:</p>
            <div class="flex flex-wrap gap-3">
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-yellow-500 mr-1"></span>
                    Pendente
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-blue-500 mr-1"></span>
                    Aprovada
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-green-500 mr-1"></span>
                    Em Andamento
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-gray-500 mr-1"></span>
                    Concluída
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-red-500 mr-1"></span>
                    Rejeitada
                </span>
                <span class="inline-flex items-center text-xs">
                    <span class="w-3 h-3 rounded-full bg-gray-400 mr-1"></span>
                    Cancelada
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
    #vacation-calendar {
        font-family: inherit;
    }
    
    .fc .fc-toolbar-title {
        font-size: 1.5rem !important;
        font-weight: 700 !important;
        color: #1f2937 !important;
    }
    
    .fc .fc-button {
        background: linear-gradient(135deg, #9333ea 0%, #ec4899 100%) !important;
        border: none !important;
        border-radius: 0.5rem !important;
        padding: 0.5rem 1rem !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
    }
    
    .fc .fc-button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 10px 20px rgba(147, 51, 234, 0.3) !important;
    }
    
    .fc .fc-button-active {
        background: linear-gradient(135deg, #7e22ce 0%, #db2777 100%) !important;
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
        background-color: rgba(147, 51, 234, 0.1) !important;
    }
    
    .fc-day-today .fc-daygrid-day-number {
        background: linear-gradient(135deg, #9333ea 0%, #ec4899 100%);
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
        background: linear-gradient(135deg, rgba(147, 51, 234, 0.1) 0%, rgba(236, 72, 153, 0.1) 100%) !important;
        font-weight: 700 !important;
        text-transform: uppercase !important;
        font-size: 0.75rem !important;
        color: #7e22ce !important;
        padding: 1rem !important;
    }
    
    .fc-daygrid-day-number {
        padding: 0.5rem !important;
        font-weight: 600 !important;
    }
</style>

<script>
document.addEventListener('livewire:initialized', () => {
    console.log('🚀 Livewire inicializado - Preparando calendário de férias');
    let vacationCalendar;
    
    function initVacationCalendar() {
        console.log('📅 Tentando inicializar calendário de férias...');
        const calendarEl = document.getElementById('vacation-calendar');
        
        if (!calendarEl) {
            console.error('❌ Elemento #vacation-calendar não encontrado!');
            return;
        }
        
        if (typeof FullCalendar === 'undefined') {
            console.error('❌ FullCalendar não está carregado!');
            return;
        }
        
        console.log('✅ Elemento encontrado, inicializando FullCalendar...');

        vacationCalendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,listMonth'
            },
            locale: 'pt-br',
            buttonText: {
                today: 'Hoje',
                month: 'Mês',
                list: 'Lista'
            },
            height: 'auto',
            editable: false,
            displayEventEnd: true,
            events: function(info, successCallback, failureCallback) {
                @this.call('getCalendarEvents', info.startStr, info.endStr)
                    .then(events => {
                        const formattedEvents = events.map(vacation => {
                            let color = '#9333ea'; // purple default
                            
                            if (vacation.status === 'pending') {
                                color = '#f59e0b'; // amber
                            } else if (vacation.status === 'approved') {
                                color = '#3b82f6'; // blue
                            } else if (vacation.status === 'in_progress') {
                                color = '#10b981'; // green
                            } else if (vacation.status === 'completed') {
                                color = '#6b7280'; // gray
                            } else if (vacation.status === 'rejected') {
                                color = '#ef4444'; // red
                            }
                            
                            return {
                                id: vacation.id,
                                title: vacation.employee_name,
                                start: vacation.start_date,
                                end: vacation.end_date_plus_one,
                                backgroundColor: color,
                                borderColor: color,
                                textColor: '#ffffff',
                                extendedProps: {
                                    vacation_number: vacation.vacation_number,
                                    employee: vacation.employee_name,
                                    days: vacation.working_days,
                                    status: vacation.status,
                                    status_label: vacation.status_label
                                }
                            };
                        });
                        successCallback(formattedEvents);
                    })
                    .catch(error => {
                        console.error('Erro ao carregar férias:', error);
                        failureCallback(error);
                    });
            },
            eventDidMount: function(info) {
                const props = info.event.extendedProps;
                info.el.title = `${props.vacation_number}\n` +
                               `👤 ${props.employee}\n` +
                               `📅 ${props.days} dias úteis\n` +
                               `📊 Status: ${props.status_label}`;
            },
            eventClick: function(info) {
                @this.viewDetails(info.event.id);
            }
        });

        try {
            vacationCalendar.render();
            console.log('✅ Calendário de férias renderizado com sucesso!');
        } catch (error) {
            console.error('❌ Erro ao renderizar calendário:', error);
        }
    }

    // Verificar se deve inicializar imediatamente (se a view já for calendar)
    setTimeout(() => {
        const calendarEl = document.getElementById('vacation-calendar');
        if (calendarEl && calendarEl.offsetParent !== null) {
            console.log('🎯 View já é calendário, inicializando imediatamente...');
            initVacationCalendar();
        } else {
            console.log('⏸️ Aguardando mudança de view para inicializar calendário...');
        }
    }, 300);

    // Listener para refresh de eventos
    Livewire.on('refreshVacationCalendar', () => {
        if (vacationCalendar) {
            vacationCalendar.refetchEvents();
        }
    });
    
    // Listener para mudança de view
    window.addEventListener('render-vacation-calendar', () => {
        console.log('🔔 Evento render-vacation-calendar recebido!');
        setTimeout(() => {
            const calendarEl = document.getElementById('vacation-calendar');
            console.log('📍 Elemento encontrado:', !!calendarEl);
            console.log('👁️ Elemento visível:', calendarEl ? calendarEl.offsetParent !== null : false);
            
            if (calendarEl && calendarEl.offsetParent !== null) {
                if (vacationCalendar) {
                    try {
                        vacationCalendar.render();
                        console.log('✅ Calendário de férias re-renderizado!');
                    } catch (error) {
                        console.error('❌ Erro ao re-renderizar:', error);
                    }
                } else {
                    console.log('🔄 Calendário não existe, inicializando...');
                    initVacationCalendar();
                }
            } else {
                console.warn('⚠️ Elemento não está visível ou não existe!');
            }
        }, 100);
    });
});
console.log('✅ Script de calendário de férias carregado!');
</script>
@endpush
