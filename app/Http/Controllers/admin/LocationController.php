<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Location;
use App\Models\Geolocation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function addGeolocation(Request $request)
    {
        $validatedData = $request->validate([
            'address'   => 'nullable|string|max:255',
            'city'      => 'nullable|string|max:255',
            'zipCode'   => 'nullable|string|max:255',
            'latitude'  => 'required|string|max:255|unique:geolocations,latitude',
            'longitude' => 'required|string|max:255|unique:geolocations,longitude',
        ]);

        try {
            DB::beginTransaction();

            $geolocation = Geolocation::create($validatedData);

            $tokenResponse = Http::asForm()->withBasicAuth(
                env('KROGER_CLIENT_ID'),
                env('KROGER_CLIENT_SECRET')
            )->post('https://api.kroger.com/v1/connect/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => env('KROGER_SCOPES'),
            ]);
        
            if (!$tokenResponse->ok()) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to authenticate with Kroger. Try again later.',
                ]);
            }
        
            $accessToken = $tokenResponse['access_token'];

            $locationResponse = Http::withToken($accessToken)
                ->get('https://api.kroger.com/v1/locations', [
                    'filter.lat.near' => $validatedData['latitude'],
                    'filter.lon.near' => $validatedData['longitude'],
                    'filter.radiusInMiles' => 1,
                    'filter.limit' => 9999,
                ]);

            $locations = $locationResponse->json();

            if (!empty($locations['data']) && is_array($locations['data'])) {
                foreach ($locations['data'] as $location) {
                    Location::create([
                        'locationId' => $location['locationId'] ?? '',
                        'storeNumber' => $location['storeNumber'] ?? '',
                        'storeName' => $location['name'] ?? '',
                        'addressLine1' => $location['address']['addressLine1'] ?? '',
                        'city' => $location['address']['city'] ?? '',
                        'state' => $location['address']['state'] ?? '',
                        'zipCode' => $location['address']['zipCode'] ?? '',
                        'geolocation_id' => $geolocation->id,
                        'latLng' => $location['geolocation']['latLng'] ?? '',
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while saving geolocation and locations.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Geolocation and locations added successfully',
            'geolocation' => $geolocation,
        ]);
    }

    public function showGeolocation($id)
    {
        $geolocation = Geolocation::find($id);

        if (!$geolocation) {
            return response()->json([
                'status' => false,
                'message' => 'Geolocation not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Geolocation fetched successfully',
            'geolocation' => $geolocation,
        ]);
    }

    public function updateGeolocation(Request $request, $id)
    {
        $validatedData = $request->validate([
            'address'   => 'nullable|string|max:255',
            'city'      => 'nullable|string|max:255',
            'zipCode'   => 'nullable|string|max:255',
            'latitude'  => 'required|string|max:255',
            'longitude' => 'required|string|max:255',
        ]);

        $geolocation = Geolocation::find($id);

        if (!$geolocation) {
            return response()->json([
                'status' => false,
                'message' => 'Geolocation not found',
            ], 404);
        }

        try {
            DB::beginTransaction();

            $geolocation->update($validatedData);

            $tokenResponse = Http::asForm()->withBasicAuth(
                env('KROGER_CLIENT_ID'),
                env('KROGER_CLIENT_SECRET')
            )->post('https://api.kroger.com/v1/connect/oauth2/token', [
                'grant_type' => 'client_credentials',
                'scope' => env('KROGER_SCOPES'),
            ]);

            if (!$tokenResponse->ok()) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Unable to authenticate with Kroger. Try again later.',
                ]);
            }

            $accessToken = $tokenResponse['access_token'];

            $locationResponse = Http::withToken($accessToken)
                ->get('https://api.kroger.com/v1/locations', [
                    'filter.lat.near' => $validatedData['latitude'],
                    'filter.lon.near' => $validatedData['longitude'],
                    'filter.radiusInMiles' => 5,
                    'filter.limit' => 9999,
                ]);

            $locations = $locationResponse->json();
            // return response()->json([
            //     'locations' => $locations,
            // ]);

            // Optionally, delete old related locations before adding new ones
            Location::where('geolocation_id', $geolocation->id)->delete();

            if (!empty($locations['data']) && is_array($locations['data'])) {
                foreach ($locations['data'] as $location) {
                    Location::create([
                        'locationId' => $location['locationId'] ?? '',
                        'storeNumber' => $location['storeNumber'] ?? '',
                        'storeName' => $location['name'] ?? '',
                        'addressLine1' => $location['address']['addressLine1'] ?? '',
                        'city' => $location['address']['city'] ?? '',
                        'state' => $location['address']['state'] ?? '',
                        'zipCode' => $location['address']['zipCode'] ?? '',
                        'geolocation_id' => $geolocation->id,
                        'latLng' => $location['geolocation']['latLng'] ?? '',
                    ]);
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'An error occurred while updating geolocation and locations.',
                'error' => $e->getMessage(),
            ], 500);
        }

        return response()->json([
            'status' => true,
            'message' => 'Geolocation and locations updated successfully',
            'geolocation' => $geolocation,
        ]);
    }

    public function searchGeolocation(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'required|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $searchTerm = $request->query('search');
        $perPage = $request->query('per_page', 20);

        $geolocations = Geolocation::where(function($query) use ($searchTerm) {
                $query->where('address', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('city', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('zipCode', 'LIKE', '%' . $searchTerm . '%');
            })
            ->select('id', 'address', 'city', 'zipCode', 'latitude', 'longitude')
            ->paginate($perPage);

        $response = [
            'status' => true,
            'message' => 'Geolocations fetched successfully',
            'meta' => [
                'current_page' => $geolocations->currentPage(),
                'per_page' => $geolocations->perPage(),
                'total' => $geolocations->total(),
                'last_page' => $geolocations->lastPage(),
            ],
            'links' => [
                'next' => $geolocations->nextPageUrl(),
                'prev' => $geolocations->previousPageUrl(),
            ],
            'data' => $geolocations->items(),
        ];

        return response()->json($response);
    }

    public function getAllGeolocations(Request $request)
    {
        $hasSearch = $request->filled('search');
        $hasPagination = $request->has('per_page') || $request->has('page');
        if ($hasSearch || $hasPagination) {
            $validatedData = $request->validate([
                'search' => 'nullable|string|max:255',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1',
            ]);

            $query = Geolocation::select('id', 'address', 'city', 'zipCode', 'latitude', 'longitude');

            if ($hasSearch) {
                $searchTerm = trim((string) $request->query('search'));
                $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('address', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('city', 'LIKE', '%' . $searchTerm . '%')
                      ->orWhere('zipCode', 'LIKE', '%' . $searchTerm . '%');
                });
            }

            $perPage = $request->query('per_page', 10);
            $geolocations = $query->orderBy('id', 'desc')->paginate($perPage);

            $response = [
                'status' => true,
                'message' => 'Geolocations fetched successfully',
                'data' => $geolocations,
            ];

            return response()->json($response);
        }

        $geolocations = Geolocation::select('id', 'address', 'city', 'zipCode', 'latitude', 'longitude')
            ->orderBy('id', 'desc')
            ->get();

        return response()->json([
            'status' => true,
            'message' => 'All geolocations fetched successfully',
            'total' => $geolocations->count(),
            'data' => $geolocations,
        ]);
    }

    public function deleteGeolocation($id)
    {
        $geolocation = Geolocation::withTrashed()->find($id);

        if (!$geolocation) {
            return response()->json([
                'status' => false,
                'message' => 'Geolocation not found',
            ], 404);
        }
        if ($geolocation->deleted_at === null) {
            $geolocation->delete();
            $msg = 'Geolocation soft deleted successfully';
        } else {
            $msg = 'Geolocation was already soft deleted';
        }

        return response()->json([
            'status' => true,
            'message' => $msg,
        ]);
    }
}
