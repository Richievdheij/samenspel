<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\AddressLookup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressSearchController extends Controller
{
    /** Search or resolve registered Dutch locations for the event creation form. */
    public function __invoke(Request $request, AddressLookup $addresses): JsonResponse
    {
        $input = $request->validate([
            'q' => ['required_without:id', 'string', 'min:3', 'max:120'],
            'id' => ['required_without:q', 'string', 'max:128'],
        ]);

        try {
            if (isset($input['id'])) {
                $location = $addresses->lookup($input['id']);

                return $location === null
                    ? response()->json(['message' => __('Choose a registered location or select location unknown.')], 404)
                    : response()->json(['location' => $location]);
            }

            return response()->json(['results' => $addresses->suggest($input['q'])]);
        } catch (ConnectionException|RequestException) {
            return response()->json(['message' => __('Address search is temporarily unavailable.')], 503);
        }
    }
}
