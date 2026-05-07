<?php

namespace App\Console\Commands\Hotel;

use Illuminate\Console\Command;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\HotelSettings;
use App\Notifications\Hotel\PreArrivalReminder;

class SendPreArrivalReminders extends Command
{
    protected $signature = 'hotel:send-prearrival {--days=2 : Days before check-in}';
    protected $description = 'Send pre-arrival reminder emails to guests with confirmed reservations';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $target = now()->addDays($days)->toDateString();

        $sent = 0;
        $reservations = Reservation::with('guest')
            ->where('check_in_date', $target)
            ->whereIn('status', [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED])
            ->get();

        foreach ($reservations as $r) {
            $settings = HotelSettings::getForTenant($r->tenant_id);
            if (!($settings->notify_pre_arrival ?? true)) continue;
            if (!$r->guest || !$r->guest->email) continue;

            try {
                $r->guest->notify(new PreArrivalReminder($r));
                $sent++;
                $this->info("Sent to {$r->guest->email} (reservation {$r->reservation_number})");
            } catch (\Throwable $e) {
                $this->error("Failed for {$r->reservation_number}: " . $e->getMessage());
            }
        }

        $this->info("Pre-arrival reminders sent: {$sent}");
        return self::SUCCESS;
    }
}
