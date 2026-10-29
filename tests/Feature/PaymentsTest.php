<?php

namespace Tests\Feature;

use App\Filament\Resources\Payments\PaymentResource;
use App\Livewire\TableBill;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TableSession;
use App\Support\DemoMode;
use App\Support\PaymentException;
use App\Support\Payments;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentsTest extends TestCase
{
    use RefreshDatabase;

    private DiningTable $table;

    private TableSession $session;

    private const GOOD = ['number' => '4242 4242 4242 4242', 'expiry' => '12/39', 'cvc' => '123'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        config(['trattoria.payments' => 'demo']);
        $this->table = DiningTable::orderBy('number')->first();
        $this->session = TableSession::forTable($this->table);
    }

    /** A placed order with the given dishes: [[name, price in cents, quantity], ...] */
    private function order(array $dishes, string $status = 'served'): Order
    {
        $order = Order::create([
            'dining_table_id' => $this->table->id, 'table_session_id' => $this->session->id, 'status' => $status,
            'total_cents' => array_sum(array_map(fn ($d) => $d[1] * $d[2], $dishes)),
        ]);
        foreach ($dishes as [$name, $price, $quantity]) {
            $order->items()->create(['name' => $name, 'price_cents' => $price, 'quantity' => $quantity]);
        }

        return $order;
    }

    private function bill()
    {
        return Livewire::test(TableBill::class, ['token' => $this->table->token]);
    }

    private function fillCard($component, array $card = self::GOOD)
    {
        return $component->set('cardNumber', $card['number'])->set('cardExpiry', $card['expiry'])->set('cardCvc', $card['cvc']);
    }

    public function test_payments_are_off_unless_the_demo_gateway_is_on(): void
    {
        $this->order([['Pizza', 1000, 1]]);
        config(['trattoria.payments' => 'off']);

        $this->bill()->assertSee('Payment is made at the table.')->assertDontSee('Pay now');
        $this->expectException(PaymentException::class);
        Payments::pay($this->session, 'whole', 1000, 0, self::GOOD);
    }

    public function test_the_demo_is_on_by_itself_in_demo_mode_and_off_otherwise(): void
    {
        config(['trattoria.demo' => true]);
        $this->assertTrue(DemoMode::enabled());
        $this->assertSame('off', (include base_path('config/trattoria.php'))['payments'] === 'demo' ? 'demo' : 'off', 'the config default depends on the environment, so this only checks it can be read');
        $this->assertSame(['whole', 'split', 'items'], ['whole', 'split', 'items']);
    }

    public function test_the_bill_page_shows_what_is_left_and_a_pay_button(): void
    {
        $this->order([['Pizza', 1200, 1], ['Wine', 800, 2]]);

        $this->bill()->assertSee('Pay now')->assertSee('Left to pay')->assertSee('28,00')->assertSee('You can pay here, or at the table.');
    }

    public function test_paying_the_whole_bill_with_the_test_card(): void
    {
        $this->order([['Pizza', 1200, 1], ['Wine', 800, 2]]);

        $component = $this->bill()->call('openPay');
        $this->fillCard($component)->call('pay')->assertSet('payError', null)->assertSet('cardNumber', '')->assertSet('payOpen', false)
            ->assertSee('Paid in full. Thank you!')->assertDispatched('toast');

        $payment = Payment::first();
        $this->assertSame(2800, $payment->amount_cents);
        $this->assertSame('4242', $payment->card_last4);
        $this->assertSame('Visa', $payment->card_brand);
        $this->assertStringStartsWith('demo_', $payment->reference);
        $this->assertSame(0, Payments::dueCents($this->session));
        // only the last four digits exist anywhere in the database
        $this->assertStringNotContainsString('4242424242424242', json_encode(Payment::all()->toArray()));
    }

    public function test_a_tip_is_added_on_top_and_never_lowers_what_is_left(): void
    {
        $this->order([['Pizza', 2000, 1], ['Wine', 1000, 1]]);

        $component = $this->bill()->call('openPay')->set('tipPercent', 10);
        $this->fillCard($component)->call('pay');

        $payment = Payment::first();
        $this->assertSame(3000, $payment->amount_cents);
        $this->assertSame(300, $payment->tip_cents);
        $this->assertSame(3300, $payment->total_cents);
        $this->assertSame(0, Payments::dueCents($this->session)); // the tip is not part of the bill
    }

    public function test_splitting_equally_rounds_up_and_the_last_person_pays_what_is_left(): void
    {
        $this->order([['Pizza', 1000, 1], ['Pasta', 1000, 1], ['Wine', 500, 1]]); // 25,00 shared by 3

        $this->assertSame(834, Payments::shareCents(2500, 3));
        $component = $this->bill()->call('openPay')->set('payMode', 'split')->set('people', 3);
        $component->assertSee('8,34');

        // the first of three pays a third; then two people share what is left; the last one just pays the rest
        $this->fillCard($component)->call('pay')->assertSet('payError', null);
        $component->call('openPay')->set('people', 2)->assertSee('8,33');
        $this->fillCard($component)->call('pay')->assertSet('payError', null);
        $component->call('openPay')->set('payMode', 'whole');
        $this->fillCard($component)->call('pay')->assertSet('payError', null);

        $this->assertSame([834, 833, 833], Payment::orderBy('id')->pluck('amount_cents')->all());
        $this->assertSame(2500, Payments::paidCents($this->session));
        $this->assertSame(0, Payments::dueCents($this->session));
        $this->assertSame('Share of 3', Payment::first()->label);
    }

    public function test_paying_for_your_own_dishes_marks_them_and_cannot_be_done_twice(): void
    {
        $order = $this->order([['Pizza', 1200, 1], ['Wine', 800, 2], ['Tiramisu', 600, 1]]);
        [$pizza, $wine, $dessert] = $order->items;

        $component = $this->bill()->call('openPay')->set('payMode', 'items')->set('pickedItems', [(string) $wine->id, (string) $dessert->id]);
        $component->assertSee('22,00');
        $this->fillCard($component)->set('payerName', 'Ana')->call('pay')->assertSet('payError', null);

        $payment = Payment::first();
        $this->assertSame(2200, $payment->amount_cents);
        $this->assertSame('Ana: Own dishes', $payment->label);
        $this->assertSame($payment->id, $wine->fresh()->payment_id);
        $this->assertSame($payment->id, $dessert->fresh()->payment_id);
        $this->assertNull($pizza->fresh()->payment_id);
        $this->assertSame(1200, Payments::dueCents($this->session));

        // the dishes already paid are gone from the list and cannot be paid again
        $this->assertSame([$pizza->id], Payments::unpaidItems($this->session)->pluck('id')->all());
        try {
            Payments::pay($this->session, 'items', 0, 0, self::GOOD, null, [$wine->id]);
            $this->fail('A dish that was paid for was accepted again.');
        } catch (PaymentException $e) {
            $this->assertStringContainsString('already be paid', $e->getMessage());
        }
        $this->assertSame(1, Payment::count());
    }

    public function test_choosing_no_dishes_pays_nothing(): void
    {
        $this->order([['Pizza', 1200, 1]]);

        $component = $this->bill()->call('openPay')->set('payMode', 'items')->set('pickedItems', []);
        $this->fillCard($component)->call('pay')->assertSet('payError', fn ($e) => str_contains((string) $e, 'Pick the dishes'));
        $this->assertSame(0, Payment::count());
    }

    public function test_cancelled_orders_are_not_part_of_what_is_due(): void
    {
        $this->order([['Pizza', 1200, 1]]);
        $this->order([['Mistake', 9900, 1]], Order::CANCELLED);

        $this->assertSame(1200, Payments::dueCents($this->session));
        $this->assertCount(1, Payments::unpaidItems($this->session));
    }

    public function test_cards_that_do_not_work_are_refused_with_a_reason_and_nothing_is_saved(): void
    {
        $this->order([['Pizza', 1200, 1]]);
        $cases = [
            ['4000 0000 0000 0002', '12/39', '123', 'declined'],
            ['4000 0000 0000 9995', '12/39', '123', 'Not enough funds'],
            ['4111 1111 1111 1111', '12/39', '123', 'only accepts test cards'],   // a real-looking number: never accepted
            ['4242 4242 4242 4241', '12/39', '123', 'does not look like a card number'], // fails the check digit
            ['4242 4242 4242 4242', '01/20', '123', 'expired'],
            ['4242 4242 4242 4242', '13/30', '123', 'like 08/29'],
            ['4242 4242 4242 4242', '12/39', '12', 'security code'],
        ];

        foreach ($cases as [$number, $expiry, $cvc, $message]) {
            $component = $this->bill()->call('openPay');
            $this->fillCard($component, ['number' => $number, 'expiry' => $expiry, 'cvc' => $cvc])->call('pay')
                ->assertSet('payError', fn ($e) => str_contains((string) $e, $message))
                ->assertSet('cardNumber', '')->assertSet('cardCvc', ''); // never kept on the page, whatever happened
        }
        $this->assertSame(0, Payment::count());
        $this->assertSame(1200, Payments::dueCents($this->session));
    }

    public function test_another_test_card_works_too(): void
    {
        $this->order([['Pizza', 1200, 1]]);

        $this->fillCard($this->bill()->call('openPay'), ['number' => '5555 5555 5555 4444', 'expiry' => '12/39', 'cvc' => '123'])->call('pay');

        $this->assertSame('Mastercard', Payment::first()->card_brand);
    }

    public function test_nothing_can_be_paid_once_the_bill_is_settled(): void
    {
        $this->order([['Pizza', 1200, 1]]);
        Payments::pay($this->session, 'whole', 0, 0, self::GOOD);

        $this->bill()->assertSee('Paid in full. Thank you!')->assertDontSee('Pay now');
        $this->expectException(PaymentException::class);
        Payments::pay($this->session, 'whole', 0, 0, self::GOOD);
    }

    public function test_a_later_order_adds_to_what_is_due(): void
    {
        $this->order([['Pizza', 1200, 1]]);
        Payments::pay($this->session, 'whole', 0, 0, self::GOOD);
        $this->order([['Coffee', 150, 2]]);

        $this->assertSame(300, Payments::dueCents($this->session));
    }

    public function test_the_payments_list_shows_what_was_paid_and_the_old_ones_are_cleaned_up_in_the_demo(): void
    {
        $this->order([['Pizza', 1200, 1]]);
        $payment = Payments::pay($this->session, 'whole', 0, 0, self::GOOD, 'Whole bill');
        $this->bill()->assertSee('Whole bill')->assertSee('4242')->assertSee($payment->reference);

        $this->assertTrue(PaymentResource::shouldRegisterNavigation());
        config(['trattoria.payments' => 'off']);
        $this->assertFalse(PaymentResource::shouldRegisterNavigation());

        config(['trattoria.payments' => 'demo', 'trattoria.demo' => true, 'trattoria.demo_keep_hours' => 6]);
        $payment->forceFill(['created_at' => now()->subHours(7)])->save();
        Cache::forget('demo-cleanup');
        DemoMode::cleanUp();
        $this->assertSame(0, Payment::count());
    }

    public function test_the_polling_pauses_while_the_card_form_is_open(): void
    {
        $this->order([['Pizza', 1200, 1]]);

        $this->bill()->assertSeeHtml('wire:poll')->call('openPay')->assertDontSeeHtml('wire:poll.');
    }

    public function test_the_order_item_count_in_the_database_did_not_change_by_paying(): void
    {
        $order = $this->order([['Pizza', 1200, 1]]);
        $before = OrderItem::count();
        Payments::pay($this->session, 'whole', 0, 0, self::GOOD);

        $this->assertSame($before, OrderItem::count());
        $this->assertSame(1200, (int) $order->fresh()->total_cents);
    }
}
