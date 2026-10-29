<?php

namespace App\Support;

use RuntimeException;

/** A payment that could not go through; the message is safe to show to the guest. */
class PaymentException extends RuntimeException {}
