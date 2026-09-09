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
    /**
     * O CHECK-IN PELO QR — uma página PÚBLICA.
     *
     * Quem autoriza aqui é o `confirmation_code` do URL, e não a sessão: o
     * hóspede lê o código do email no telemóvel e não tem conta nenhuma. Por
     * isso a reserva procura-se SEM o escopo de empresa — se o deixássemos
     * entrar, um recepcionista com a sua própria empresa aberta noutro
     * separador abria o link do hóspede e via um 404.
     */
    public function expressCheckIn($id, string $code)
    {
        $reservation = Reservation::withoutGlobalScopes()
            ->with(['guest', 'room', 'roomType'])
            ->findOrFail($id);

        if (!hash_equals((string) $reservation->confirmation_code, $code)) {
            abort(403, 'Código de confirmação inválido.');
        }

        return view('hotel.express-checkin', [
            'reservation' => $reservation,
            'settings' => HotelSettings::getForTenant($reservation->tenant_id),
            'alreadyCheckedIn' => $reservation->status === Reservation::STATUS_CHECKED_IN,
        ]);
    }

    /** A confirmação do mesmo check-in público — e pela mesma razão, sem escopo. */
    public function confirmExpressCheckIn($id, string $code, Request $request)
    {
        $reservation = Reservation::withoutGlobalScopes()->findOrFail($id);

        if (!hash_equals((string) $reservation->confirmation_code, $code)) {
            abort(403, 'Código inválido.');
        }

        // Já está lá dentro: nada a fazer, mas também não é erro.
        if ($reservation->status === Reservation::STATUS_CHECKED_IN) {
            return redirect()->route('hotel.express-checkin', [$id, $code])
                ->with('success', 'O check-in já tinha sido efectuado.');
        }

        // Só se entra a partir de uma reserva por confirmar ou confirmada.
        //
        // A condição anterior era "se ainda não está em checked_in, faz
        // check-in" — o que deixava passar TODOS os outros estados. Como esta
        // rota é pública (basta o QR, que fica impresso no voucher), bastava
        // reler o código para RESSUSCITAR uma reserva cancelada ou reabrir uma
        // estadia já fechada e facturada, voltando a ocupar o quarto.
        $permitidos = [Reservation::STATUS_PENDING, Reservation::STATUS_CONFIRMED];

        if (!in_array($reservation->status, $permitidos, true)) {
            return redirect()->route('hotel.express-checkin', [$id, $code])
                ->with('error', 'Esta reserva não permite check-in (estado: ' . $reservation->status . '). Dirija-se à recepção.');
        }

        $reservation->checkIn();

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
