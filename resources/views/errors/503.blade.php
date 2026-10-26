@include('errors.layout', [
    'code' => '503',
    'heading' => __('Back in a moment'),
    'message' => __('We are making a quick update. Please try again shortly, or ask a member of staff to take your order.'),
    'retry' => true,
])
