<?php

namespace App\Http\Controllers\B2B;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HotelSearchController extends Controller
{
    public function index(Request $request)
    {
        return view('b2b.hotels.index', [
            'hotels' => [],
            'searchPerformed' => false,
        ]);
    }
}
