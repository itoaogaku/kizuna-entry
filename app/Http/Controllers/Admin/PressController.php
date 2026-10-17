<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Meet;
use Illuminate\View\View;

class PressController extends Controller
{
    public function index(Meet $meet): View
    {
        return view('admin.press.index', ['meet' => $meet, 'requests' => $meet->pressRequests()->get()]);
    }
}
