@include('errors.layout', [
    'code' => '500',
    'heading' => __('Something went wrong'),
    'message' => __('Sorry, that did not work. Please try again in a moment, or ask a member of staff to take your order.'),
    'retry' => true,
])
