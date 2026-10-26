<?php

namespace App\Console\Commands;

use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\TableSession;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class DemoHistory extends Command
{
    protected $signature = 'demo:history {days=14 : How many days of past orders to generate} {--fresh : Delete existing orders first}';

    protected $description = 'Fill the database with realistic past orders so the admin dashboard has data to show';

    public function handle(): int
    {
        $tables = DiningTable::all();
        $items = MenuItem::where('is_available', true)->get();

        if ($tables->isEmpty() || $items->isEmpty()) {
            $this->error('Seed the menu and tables first: php artisan db:seed');

            return self::FAILURE;
        }

        if ($this->option('fresh')) {
            Order::query()->delete();
            TableSession::query()->delete();
        }

        // Lunch and dinner rushes; weights make the evening busier.
        $hours = [12 => 3, 13 => 5, 14 => 2, 19 => 4, 20 => 7, 21 => 6, 22 => 2];
        $created = 0;

        for ($d = (int) $this->argument('days'); $d >= 0; $d--) {
            $day = today()->subDays($d);
            $perDay = random_int(25, 55) + (in_array($day->dayOfWeek, [5, 6]) ? 20 : 0);

            for ($n = 0; $n < $perDay; $n++) {
                $at = $day->copy()->setTime($this->weightedHour($hours), random_int(0, 59), random_int(0, 59));
                if ($at->isFuture()) {
                    continue;
                }
                $this->createOrder($tables->random(), $items, $at);
                $created++;
            }
        }

        $this->info("Created {$created} past orders. Open /admin to see the dashboard.");

        return self::SUCCESS;
    }

    private function weightedHour(array $weights): int
    {
        $pick = random_int(1, array_sum($weights));
        foreach ($weights as $hour => $w) {
            if (($pick -= $w) <= 0) {
                return $hour;
            }
        }

        return 20;
    }

    private function createOrder(DiningTable $table, $items, Carbon $at): void
    {
        $session = TableSession::create([
            'dining_table_id' => $table->id,
            'token' => Str::random(24),
            'last_activity_at' => $at,
            'closed_at' => $at->copy()->addMinutes(60),
        ]);

        $total = 0;
        $lines = [];
        foreach ($items->random(random_int(1, 4)) as $item) {
            $selection = $this->defaultSelection($item);
            $qty = random_int(1, 3);
            $price = $item->unitPriceCents($selection);
            $total += $price * $qty;
            $lines[] = [
                'menu_item_id' => $item->id,
                'name' => $item->name,
                'options_text' => $item->optionsText($selection),
                'price_cents' => $price,
                'quantity' => $qty,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        $preparing = $at->copy()->addMinutes(random_int(1, 4));
        $ready = $preparing->copy()->addMinutes(random_int(8, 24));

        $order = Order::create([
            'dining_table_id' => $table->id,
            'table_session_id' => $session->id,
            'status' => 'served',
            'total_cents' => $total,
            'preparing_at' => $preparing,
            'ready_at' => $ready,
            'served_at' => $ready->copy()->addMinutes(random_int(1, 5)),
        ]);
        $order->forceFill(['created_at' => $at, 'updated_at' => $at])->saveQuietly();
        $order->items()->createMany($lines);
    }

    /** First choice of every required option group, so prices and labels stay valid. */
    private function defaultSelection(MenuItem $item): array
    {
        $selection = [];
        foreach ($item->options ?? [] as $g => $group) {
            if (! empty($group['required'])) {
                $selection[$g] = [0];
            }
        }

        return $selection;
    }
}
