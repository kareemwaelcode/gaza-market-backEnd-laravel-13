<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Regions;
use App\Models\Area;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index (Request $request) {
        $regions = Regions::orderBy('created_at', 'desc')->get();
        $areas = Area::orderBy('created_at', 'desc')->get();
        return response()->view('gazamarket.index' , compact('regions', 'areas'));
    }
}
