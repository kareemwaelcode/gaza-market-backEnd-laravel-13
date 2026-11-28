<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Category;
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

    public function home (Request $request) {
        $regions = Regions::orderBy('created_at', 'desc')->get();
        $areas = Area::orderBy('created_at', 'desc')->get();
        $categories = Category::where('category_is_active', true)->ordered()->get();
        return response()->view('gazamarket.home' , compact('regions', 'areas', 'categories'));
    }

    public function market (Request $request) {
        $regions = Regions::orderBy('created_at', 'desc')->get();
        $areas = Area::orderBy('created_at', 'desc')->get();
        return response()->view('gazamarket.market' , compact('regions', 'areas'));
    }

    public function marketDetails (Request $request) {
        $regions = Regions::orderBy('created_at', 'desc')->get();
        $areas = Area::orderBy('created_at', 'desc')->get();
        return response()->view('gazamarket.market-details' , compact('regions', 'areas'));
    }

    public function profile (Request $request) {
        $regions = Regions::orderBy('created_at', 'desc')->get();
        $areas = Area::orderBy('created_at', 'desc')->get();
        return response()->view('gazamarket.profile' , compact('regions', 'areas'));
    }
}