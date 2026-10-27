{{-- Portfolio demo notice, shared by the customer pages, the kitchen board and the admin panel.
     Styled on its own so it looks the same everywhere (the admin panel has its own stylesheet). --}}
<style>
    .demo-bar{background:#1c1917;color:rgba(255,255,255,.9);font:600 12px/1.5 system-ui,sans-serif;text-align:center;padding:8px 14px}
    .demo-bar p{margin:0}
    .demo-bar a{color:#fff}
    .demo-bar .demo-links{display:flex;flex-wrap:wrap;justify-content:center;gap:6px;margin:6px 0 4px}
    .demo-bar .demo-links a{border-radius:999px;background:rgba(255,255,255,.15);padding:5px 14px;font-weight:700;text-decoration:none}
    .demo-bar .demo-links a:hover,.demo-bar .demo-links a:focus-visible{background:rgba(255,255,255,.3)}
    .demo-bar .demo-note{opacity:.75;font-weight:500}
</style>
<div class="demo-bar" role="note">
    <p>Portfolio demo by <a href="{{ config('trattoria.portfolio_url') }}" style="text-decoration:underline">Francisco</a>: orders are cleared regularly.</p>
    <div class="demo-links">
        <a href="{{ url('/') }}">Customer menu</a>
        <a href="{{ url('/demo/kitchen') }}">Kitchen board</a>
        <a href="{{ url('/demo/admin') }}">Admin panel</a>
    </div>
    <p class="demo-note">The kitchen board and the admin panel are open to everyone because this is a live demo, so you can try the full experience. On a real website they would be secured behind a login.</p>
</div>
