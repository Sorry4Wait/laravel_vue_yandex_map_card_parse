<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveOrganizationLinkRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Resources\ParseRunResource;
use App\Jobs\ParseOrganizationReviewsJob;
use App\Models\ParseRun;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    /**
     * The one organization the current user has hooked up, if any. Kept
     * singular on purpose - the settings screen only deals with one card at
     * a time, this isn't a multi-org dashboard.
     */
    public function current(Request $request)
    {
        $organization = $request->user()->organizations()->latest()->first();

        if (! $organization) {
            return response()->json(['organization' => null, 'parse_run' => null]);
        }

        return response()->json([
            'organization' => new OrganizationResource($organization),
            'parse_run' => new ParseRunResource($organization->latestParseRun()),
        ]);
    }

    public function store(SaveOrganizationLinkRequest $request)
    {
        $url = $request->validated('yandex_url');

        // saving the same link twice re-triggers a parse instead of erroring,
        // that's the whole point of idempotent upserts here
        $organization = $request->user()->organizations()->firstOrNew(['yandex_url' => $url]);
        $organization->fill(['yandex_url' => $url])->save();

        $run = ParseRun::create([
            'organization_id' => $organization->id,
            'status' => ParseRun::STATUS_QUEUED,
            'reviews_fetched' => 0,
            'reviews_created' => 0,
            'reviews_updated' => 0,
        ]);

        ParseOrganizationReviewsJob::dispatch($organization->id, $run->id);

        return response()->json([
            'organization' => new OrganizationResource($organization),
            'parse_run' => new ParseRunResource($run),
        ], 202);
    }

    public function parseStatus(Request $request, int $organization)
    {
        $organization = $request->user()->organizations()->findOrFail($organization);

        return new ParseRunResource($organization->latestParseRun());
    }
}
