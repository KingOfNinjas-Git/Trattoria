<?php

namespace App\Support;

use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TableSession;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Paying the bill from the guest's phone: everything at once, equal shares, or only your own dishes, with an
 * optional tip. This is a DEMONSTRATION gateway: it only knows a few test cards and never charges anything.
 * A real restaurant swaps `charge()` for a payment provider's call (for example Stripe) and keeps the rest.
 */
class Payments
{
    /** The test cards the demo accepts (number => [brand, outcome]). */
    public const TEST_CARDS = [
        '4242424242424242' => ['Visa', 'ok'],
        '5555555555554444' => ['Mastercard', 'ok'],
        '4000000000000002' => ['Visa', 'declined'],
        '4000000000009995' => ['Visa', 'funds'],
    ];

    public const MAX_PEOPLE = 12;

    public static function enabled(): bool
    {
        return config('trattoria.payments') === 'demo';
    }

    public static function billCents(TableSession $session): int
    {
        return (int) $session->orders()->notCancelled()->sum('total_cents');
    }

    public static function paidCents(TableSession $session): int
    {
        return (int) $session->payments()->sum('amount_cents');
    }

    public static function dueCents(TableSession $session): int
    {
        return max(0, self::billCents($session) - self::paidCents($session));
    }

    /** One person's share when what is left is split equally (rounded up; the last person never pays more than what is left, see pay()). */
    public static function shareCents(int $dueCents, int $people): int
    {
        return (int) ceil($dueCents / max(1, min(self::MAX_PEOPLE, $people)));
    }

    /** Dishes nobody has paid for yet (from orders that were not cancelled). */
    public static function unpaidItems(TableSession $session): Collection
    {
        return OrderItem::whereNull('payment_id')
            ->whereHas('order', fn ($query) => $query->where('table_session_id', $session->id)->notCancelled())
            ->with('order')
            ->orderBy('order_id')->orderBy('id')
            ->get();
    }

    public static function tipCents(int $amountCents, int $percent): int
    {
        return (int) round($amountCents * max(0, min(25, $percent)) / 100);
    }

    /**
     * Checks the card and "charges" it. Returns the brand and the last four digits (the only things ever kept).
     *
     * @param  array{number?: string, expiry?: string, cvc?: string}  $card
     * @return array{brand: string, last4: string}
     */
    public static function charge(array $card): array
    {
        $digits = preg_replace('/\D+/', '', (string) ($card['number'] ?? ''));
        if (strlen($digits) < 13 || strlen($digits) > 19 || ! self::luhn($digits)) {
            throw new PaymentException(__('That does not look like a card number.'));
        }

        if (! preg_match('/^(0[1-9]|1[0-2])\s*\/\s*(\d{2})$/', trim((string) ($card['expiry'] ?? '')), $m)) {
            throw new PaymentException(__('Type the expiry date like 08/29.'));
        }
        if (now()->startOfMonth()->gt(now()->setDate(2000 + (int) $m[2], (int) $m[1], 1)->startOfMonth())) {
            throw new PaymentException(__('That card has expired.'));
        }
        if (! preg_match('/^\d{3,4}$/', trim((string) ($card['cvc'] ?? '')))) {
            throw new PaymentException(__('The security code has 3 or 4 digits.'));
        }

        [$brand, $outcome] = self::TEST_CARDS[$digits] ?? [null, null];
        if ($brand === null) {
            // A real card number is never accepted (or kept): the demo only works with the test cards.
            throw new PaymentException(__('This demo only accepts test cards. Use 4242 4242 4242 4242.'));
        }
        if ($outcome === 'declined') {
            throw new PaymentException(__('The card was declined (this is the test card for that).'));
        }
        if ($outcome === 'funds') {
            throw new PaymentException(__('Not enough funds (this is the test card for that).'));
        }

        return ['brand' => $brand, 'last4' => substr($digits, -4)];
    }

    /**
     * Pays part of the bill.
     *
     * @param  'whole'|'share'|'items'  $kind
     * @param  int[]  $itemIds  the dishes being paid for (only for "items")
     * @param  array{number?: string, expiry?: string, cvc?: string}  $card
     */
    public static function pay(TableSession $session, string $kind, int $amountCents, int $tipCents, array $card, ?string $label = null, array $itemIds = []): Payment
    {
        if (! self::enabled()) {
            throw new PaymentException(__('Payment is made at the table.'));
        }

        return DB::transaction(function () use ($session, $kind, $amountCents, $tipCents, $card, $label, $itemIds) {
            $due = self::dueCents($session);
            if ($due <= 0) {
                throw new PaymentException(__('There is nothing left to pay.'));
            }

            $items = collect();
            if ($kind === 'items') {
                $ids = array_values(array_unique(array_map('intval', $itemIds)));
                $items = self::unpaidItems($session)->whereIn('id', $ids)->values();
                if ($ids === [] || $items->count() !== count($ids)) {
                    throw new PaymentException(__('Pick the dishes you want to pay for (some may already be paid).'));
                }
                $amountCents = (int) $items->sum(fn (OrderItem $item) => $item->price_cents * $item->quantity);
            } elseif ($kind === 'whole') {
                $amountCents = $due;
            }

            // The last person to pay a share never pays more than what is left
            $amountCents = min($amountCents, $due);
            if ($amountCents <= 0) {
                throw new PaymentException(__('There is nothing left to pay.'));
            }

            $paidWith = self::charge($card);

            $payment = $session->payments()->create([
                'amount_cents' => $amountCents,
                'tip_cents' => max(0, min(20000, $tipCents)),
                'kind' => $kind,
                'label' => $label ? Str::limit($label, 60, '') : null,
                'card_brand' => $paidWith['brand'],
                'card_last4' => $paidWith['last4'],
                'reference' => 'demo_'.Str::lower(Str::random(12)),
                'paid_at' => now(),
            ]);

            if ($items->isNotEmpty()) {
                OrderItem::whereIn('id', $items->pluck('id'))->update(['payment_id' => $payment->id]);
            }
            $session->touchActivity();

            return $payment;
        });
    }

    private static function luhn(string $digits): bool
    {
        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $n = (int) $digit;
            if ($i % 2 === 1) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
        }

        return $sum % 10 === 0;
    }
}
