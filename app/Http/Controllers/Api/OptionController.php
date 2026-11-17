<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Place;
use Illuminate\Http\JsonResponse;

class OptionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [
            'listing_conditions' => ['new', 'used', 'urgent'],
            'place_types' => Place::TYPES,
        ]]);
    }
}