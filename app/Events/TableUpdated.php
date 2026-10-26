<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/** Something changed at a table (cart, order, service request). Customers and the kitchen refresh. */
class TableUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(public string $sessionToken, public string $kind, public ?string $origin = null) {}

    public function broadcastOn(): array
    {
        return [new Channel('table.'.$this->sessionToken), new PrivateChannel('kitchen')];
    }

    public function broadcastAs(): string
    {
        return 'updated';
    }

    public function broadcastWith(): array
    {
        return ['kind' => $this->kind, 'origin' => $this->origin];
    }
}
