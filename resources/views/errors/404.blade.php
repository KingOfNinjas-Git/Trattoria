@include('errors.layout', [
    'code' => '404',
    'heading' => __('We could not find that page'),
    'message' => __('This link may be old or mistyped. Please scan the QR code on your table again, or ask a member of staff for help.'),
])
