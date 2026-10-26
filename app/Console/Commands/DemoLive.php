<?php

namespace App\Console\Commands;

use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Support\Ordering;
use App\Support\Realtime;
use Illuminate\Console\Command;

class DemoLive extends Command
{
    protected $signature = 'demo:live {--interval=8 : Seconds between simulated events} {--auto-kitchen : Also advance orders as if a chef were working}';

    protected $description = 'Simulate guests ordering so the kitchen board and customer screens move on their own (Ctrl+C to stop)';

    public function handle(): int
    {
        $tables = DiningTable::all();
        $items = MenuItem::where('is_available', true)->get();

        if ($tables->isEmpty() || $items->isEmpty()) {
            $this->error('Seed the menu and tables first: php artisan db:seed');

            return self::FAILURE;
        }

        $this->info('Simulating guests. Watch /kitchen. Press Ctrl+C to stop.');

        while (true) {
            $roll = random_int(1, 100);

            if ($roll <= 70) {
                $this->placeRandomOrder($tables->random(), $items);
            } elseif ($roll <= 85) {
                $session = TableSession::forTable($tables->random());
                Ordering::requestService($session, ServiceRequest::WAITER);
                $this->line("Table {$session->table->number} calls a waiter");
            }

            if ($this->option('auto-kitchen')) {
                $this->advanceSomeOrders();
            }

            sleep(max(1, (int) $this->option('interval')));
        }
    }

    private function placeRandomOrder(DiningTable $table, $items): void
    {
        $session = TableSession::forTable($table);

        foreach ($items->random(random_int(1, 3)) as $item) {
            $selection = [];
            foreach ($item->options ?? [] as $g => $group) {
                if (! empty($group['required'])) {
                    $selection[$g] = [random_int(0, count($group['choices']) - 1)];
                }
            }
            Ordering::addToCart($session, $item, $selection, random_int(1, 2));
        }

        $order = Ordering::placeOrder($session);
        if ($order) {
            $this->line("Table {$table->number} placed order #{$order->id} (EUR ".$order->total.')');
        }
    }

    private function advanceSomeOrders(): void
    {
        Order::with('session')->where('status', '!=', 'served')->get()->each(function (Order $order) {
            if (random_int(1, 100) <= 45 && $order->advance() && $order->session) {
                Realtime::table($order->session->token, 'order.status');
                $this->line("Order #{$order->id} is now {$order->status}");
            }
        });
    }
}
