<?php

use Illuminate\Support\Facades\Broadcast;

// Staff only: the kitchen board listens here.
Broadcast::channel('kitchen', fn ($user) => $user !== null);
