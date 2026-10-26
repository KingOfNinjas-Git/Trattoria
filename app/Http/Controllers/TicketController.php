<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Contracts\View\View;

class TicketController extends Controller
{
    /** A narrow printable kitchen ticket for one order. */
    public function show(Order $order): View
    {
        return view('ticket', ['order' => $order->load(['table', 'items'])]);
    }
}
