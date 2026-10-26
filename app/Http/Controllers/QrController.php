<?php

namespace App\Http\Controllers;

use App\Models\DiningTable;
use Illuminate\Contracts\View\View;

class QrController extends Controller
{
    public function all(): View
    {
        return view('qr', ['tables' => DiningTable::orderBy('number')->get()]);
    }

    public function show(DiningTable $table): View
    {
        return view('qr', ['tables' => collect([$table])]);
    }
}
