<?php
namespace App\Http\Controllers\Restaurant;
use App\Http\Controllers\Controller;use App\Models\Restaurant\KitchenTicket;
class KitchenTicketController extends Controller {public function print(int $ticket){$model=KitchenTicket::withoutGlobalScopes()->where('tenant_id',activeTenantId())->with(['station','order.table','items.orderItem'])->findOrFail($ticket);return view('restaurant.kitchen-ticket-print',['ticket'=>$model,'copy'=>request()->boolean('copy')]);}}
