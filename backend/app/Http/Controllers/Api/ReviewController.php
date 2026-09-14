<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index(Request $request, int $organization)
    {
        $organization = $request->user()->organizations()->findOrFail($organization);

        // served straight from the db, not re-parsed on every page click -
        // see README for why we cache the parse result instead of hitting
        // yandex on every pagination click
        $reviews = $organization->reviews()
            ->orderByDesc('published_at')
            ->paginate(config('yandex.reviews_per_page'));

        return ReviewResource::collection($reviews);
    }
}
