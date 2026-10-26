@include('errors.layout', [
    'code' => '419',
    'heading' => __('This page has expired'),
    'message' => __('You were away for a while. Reload the page to carry on; your order is safe.'),
    'retry' => true,
])
