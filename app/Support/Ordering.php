<?php

namespace App\Support;

use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use Illuminate\Support\Facades\DB;

/** Business rules for the shared table cart, orders and service requests. */
class Ordering
{
    /**
     * Add a dish to the table's shared cart. Identical dish + options merge into one line.
     * Returns false when the dish is unavailable or the option selection is invalid.
     */
    public static function addToCart(TableSession $session, MenuItem $item, array $selection = [], int $qty = 1, ?string $origin = null): bool
    {
        if ($session->closed_at || ! $item->isOrderable() || $qty < 1) {
            return false;
        }

        $selection = $item->normaliseSelection($selection);
        if ($selection === null) {
            return false;
        }

        $key = $selection ? json_encode($selection) : '';

        DB::transaction(function () use ($session, $item, $selection, $key, $qty) {
            $line = CartItem::where('table_session_id', $session->id)
                ->where('menu_item_id', $item->id)
                ->where('selection_key', $key)
                ->lockForUpdate()
                ->first();

            if ($line) {
                $line->increment('quantity', $qty);
            } else {
                CartItem::create([
                    'table_session_id' => $session->id,
                    'menu_item_id' => $item->id,
                    'selection' => $selection ?: null,
                    'selection_key' => $key,
                    'quantity' => $qty,
                ]);
            }
        });

        $session->touchActivity();
        Realtime::table($session->token, 'cart', $origin);

        return true;
    }

    public static function changeQuantity(TableSession $session, int $lineId, int $delta, ?string $origin = null): void
    {
        $line = $session->closed_at ? null : CartItem::where('table_session_id', $session->id)->find($lineId);
        if (! $line) {
            return;
        }

        $quantity = $line->quantity + $delta;
        $quantity > 0 ? $line->update(['quantity' => $quantity]) : $line->delete();

        $session->touchActivity();
        Realtime::table($session->token, 'cart', $origin);
    }

    /** Turn the shared cart into a kitchen order. Returns null when there is nothing to send. */
    public static function placeOrder(TableSession $session, ?string $notes = null, ?string $origin = null): ?Order
    {
        if ($session->closed_at) {
            return null;
        }

        $order = DB::transaction(function () use ($session, $notes) {
            $lines = CartItem::with('menuItem')
                ->where('table_session_id', $session->id)
                ->lockForUpdate()
                ->get()
                ->filter(fn (CartItem $l) => $l->menuItem->isOrderable());

            if ($lines->isEmpty()) {
                return null;
            }

            $order = Order::create([
                'dining_table_id' => $session->dining_table_id,
                'table_session_id' => $session->id,
                'notes' => $notes ?: null,
                'total_cents' => $lines->sum(fn (CartItem $l) => $l->lineTotalCents()),
            ]);

            foreach ($lines as $line) {
                $order->items()->create([
                    'menu_item_id' => $line->menu_item_id,
                    'name' => $line->menuItem->name,
                    'options_text' => $line->optionsText(),
                    'price_cents' => $line->unitPriceCents(),
                    'quantity' => $line->quantity,
                ]);
            }

            CartItem::where('table_session_id', $session->id)->delete();

            return $order;
        });

        if ($order) {
            $session->touchActivity();
            Realtime::table($session->token, 'order.placed', $origin);
        }

        return $order;
    }

    public static function requestService(TableSession $session, string $type, ?string $origin = null): ServiceRequest
    {
        $request = ServiceRequest::firstOrCreate([
            'table_session_id' => $session->id,
            'dining_table_id' => $session->dining_table_id,
            'type' => $type,
            'resolved_at' => null,
        ]);

        $session->touchActivity();
        Realtime::table($session->token, 'service', $origin);

        return $request;
    }
}
