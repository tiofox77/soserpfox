<?php

namespace App\Observers;

use App\Models\Salon\Appointment;

class SalonAppointmentObserver
{
    /**
     * Handle the Appointment "updated" event.
     */
    public function updated(Appointment $appointment): void
    {
        // Verificar se o status mudou para 'completed'
        if ($appointment->isDirty('status') && $appointment->status === 'completed') {
            $this->registerClientVisit($appointment);
        }
    }

    /**
     * Registar visita do cliente
     */
    protected function registerClientVisit(Appointment $appointment): void
    {
        if (!$appointment->client) {
            return;
        }

        // Calcular valor total do agendamento
        $totalAmount = $appointment->total_price ?? 0;

        // Registar visita no cliente
        $appointment->client->registerVisit($totalAmount);
    }
}
