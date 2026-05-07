<?php

namespace App\Http\Controllers\Hotel;

use App\Http\Controllers\Controller;
use App\Models\Hotel\Reservation;
use App\Models\Hotel\Room;
use App\Models\Hotel\HotelSettings;
use Illuminate\Http\Request;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class ReservationController extends Controller
{
    /**
     * Express check-in via QR code URL.
     * GET /hotel/reservations/{id}/checkin/{code}
     */
    public function expressCheckIn($id, string $code)
    {
        $reservation = Reservation::with(['guest', 'room', 'roomType'])->findOrFail($id);

        if (!hash_equals((string) $reservation->confirmation_code, $code)) {
            abort(403, 'Código de confirmação inválido.');
        }

        return view('hotel.express-checkin', [
            'reservation' => $reservation,
            'settings' => HotelSettings::getForTenant($reservation->tenant_id),
            'alreadyCheckedIn' => $reservation->status === Reservation::STATUS_CHECKED_IN,
        ]);
    }

    public function confirmExpressCheckIn($id, string $code, Request $request)
    {
        $reservation = Reservation::findOrFail($id);

        if (!hash_equals((string) $reservation->confirmation_code, $code)) {
            abort(403, 'Código inválido.');
        }

        if ($reservation->status !== Reservation::STATUS_CHECKED_IN) {
            $reservation->checkIn();
        }

        return redirect()->route('hotel.express-checkin', [$id, $code])
            ->with('success', 'Check-in efectuado com sucesso!');
    }

    /**
     * Voucher PDF/HTML (print-friendly).
     */
    public function voucher($id)
    {
        $reservation = Reservation::with(['guest', 'client', 'room', 'roomType'])->findOrFail($id);
        $settings = HotelSettings::getForTenant($reservation->tenant_id);

        return view('pdf.hotel.voucher', [
            'reservation' => $reservation,
            'settings' => $settings,
            'qrSvg' => $this->qrSvg($reservation->check_in_qr_url),
        ]);
    }

    /**
     * Folio PDF/HTML (print-friendly).
     */
    public function folio($id)
    {
        $reservation = Reservation::with(['guest', 'client', 'room', 'roomType', 'items'])->findOrFail($id);
        $settings = HotelSettings::getForTenant($reservation->tenant_id);

        return view('pdf.hotel.folio', [
            'reservation' => $reservation,
            'settings' => $settings,
        ]);
    }

    /**
     * SEF registration sheet (police registration).
     */
    public function sef($id)
    {
        $reservation = Reservation::with(['guest', 'client', 'room', 'roomType'])->findOrFail($id);
        $settings = HotelSettings::getForTenant($reservation->tenant_id);

        return view('pdf.hotel.sef', [
            'reservation' => $reservation,
            'settings' => $settings,
        ]);
    }

    /**
     * Generate SVG QR code for a URL.
     */
    public function qr($id)
    {
        $reservation = Reservation::findOrFail($id);
        return response($this->qrSvg($reservation->check_in_qr_url), 200, [
            'Content-Type' => 'image/svg+xml',
        ]);
    }

    protected function qrSvg(string $url, int $size = 220): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        return $writer->writeString($url);
    }
}
