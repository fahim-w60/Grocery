<?php

namespace App\Http\Controllers\shopper;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Auth;
use App\Models\User;
use App\Models\Order;
use App\Models\Location;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use App\Models\Notification;

class ShopperController extends Controller
{
    private function buildImageUrl($photo)
    {
        if (!empty($photo) && !filter_var($photo, FILTER_VALIDATE_URL)) {
            return asset($photo);
        }
        if (!empty($photo)) {
            return $photo;
        }
        return asset('uploads/profiles/no_image.jpeg');
    }

    public function recentOrders(Request $request)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $auth = auth()->user();

        $recentOrders = Order::where('shopper_id', $auth->id)
            ->where('status', 'order_delivered')
            ->with(['orderItems', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Recent orders retrieved successfully',
            'data' => $recentOrders->map(function($order) {
                return [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_name' => $order->user->name,
                    'status' => $order->status,
                    'total_price' => $order->total
                ];
            }),
        ]);
    }

    public function recentOrderDetails(string $id)
    {
        try {
            $auth = auth()->user();
            
            $order = Order::where('id', $id)
                ->where('shopper_id', $auth->id)
                ->with(['orderItems', 'user', 'payments'])
                ->first();

                //dd($order);

            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found'
                ], 404);
            }

           
            $nearestStore = $this->nearestStoreForOrder($order, $auth);

           
            $authLat = optional($auth->userlocations)->latitude;
            $authLng = optional($auth->userlocations)->longitude;
            $custLat = optional($order->user->userlocations)->latitude;
            $custLng = optional($order->user->userlocations)->longitude;
            $distanceKm = function($lat1, $lon1, $lat2, $lon2) {
                $earthRadius = 6371; // km
                $dLat = deg2rad($lat2 - $lat1);
                $dLon = deg2rad($lon2 - $lon1);
                $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                return $earthRadius * $c;
            };
            $dropOffDistance = (!is_null($authLat) && !is_null($authLng) && !is_null($custLat) && !is_null($custLng))
                ? round($distanceKm((float)$authLat, (float)$authLng, (float)$custLat, (float)$custLng), 2)
                : null;

            return response()->json([
                'status' => true,
                'message' => 'Order details retrieved successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_price' => $order->total,
                    'tax' => $order->tax,
                    'delivery_charges' => $order->delivery_charges,
                    'delivery_date' => $order->delivery_date,
                    'delivery_time' => $order->delivery_time,
                    'delivery_notes' => $order->delivery_notes,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,

                    'user' => [
                        'id' => $auth->id,
                        'address' => $auth->address,
                        'latitude' => $auth->userlocations->latitude ?? null,
                        'longitude' => $auth->userlocations->longitude ?? null,
                    ],
                    'pick_up_location' => [
                        'name' => $nearestStore['name'] ?? null,
                        'address' => $nearestStore['address'] ?? null,
                        'latitude' => $nearestStore['address']['latitude'] ?? null,
                        'longitude' => $nearestStore['address']['longitude'] ?? null,
                        'distance_km' => $nearestStore['distance_km'] ?? null,
                        'eta_minutes' => isset($nearestStore['distance_km']) ? (int) round($nearestStore['distance_km'] * 20) : null,
                    ],

                    'drop_off_location' => [
                        'id' => $order->user->id,
                        'address' => $order->user->address,
                        'latitude' => $custLat,
                        'longitude' => $custLng,
                        'distance_km' => $dropOffDistance,
                        'eta_minutes' => isset($dropOffDistance) ? (int) round($dropOffDistance * 20) : null,
                    ],
                    
                    // Customer information
                    'customer' => [
                        'id' => $order->user->id,
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'phone' => $order->user->phone,
                        'address' => $order->user->address,
                        'photo' => $order->user->photo ? asset($order->user->photo) : null,
                    ],
                    
                   // Store information
                    'stores' => $order->orderItems->map(function($item) use ($order) {
                        $store = Location::where('storeName', $item->storeName)->first();
                        return [
                            'name' => $item->storeName ?? null,
                            'address' => $store ? $store->addressLine1 . ', ' . $store->city . ', ' . $store->state . ' ' . $store->zipCode : null
                        ];
                    })->unique('name')->values(),
                    // nearest_store removed in recentOrderDetails per request
                    
                    // Order items
                    'items' => $order->orderItems->map(function($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'unit_price' => $item->unit_price,
                            'quantity' => $item->quantity,
                            'total_price' => $item->total_price,
                            'product_notes' => $item->product_notes,
                            'store_name' => $item->storeName ?? null,
                            'product_image' => $item->images ?? null,
                        ];
                    }),
                    
                    // Payment information
                    'payment' => $order->payments->first() ? [
                        'id' => $order->payments->first()->id,
                        'payment_method' => $order->payments->first()->payment_method,
                        'payment_status' => $order->payments->first()->payment_status,
                        'amount' => $order->payments->first()->amount,
                        'transaction_id' => $order->payments->first()->transaction_id,
                        'created_at' => $order->payments->first()->created_at,
                    ] : null,
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve order details'
            ], 500);
        }
    }

    public function newOrders(Request $request)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $auth = auth()->user();

        $newOrders = Order::where('shopper_id', $auth->id)
            ->where('status', 'order_placed')
            ->with(['orderItems.product', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'New orders retrieved successfully',
            'data' => $newOrders->map(function($order) {
                return [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_name' => $order->user->name,
                    'status' => $order->status,
                    'total_price' => $order->total
                ];
            }),
        ]);
    }

public function allShoppersForAdmin(Request $request)
{
    $hasSearch = $request->filled('search');
    $hasPagination = $request->has('per_page') || $request->has('page');
    $selectFields = ['id', 'name', 'address', 'email', 'phone', 'role', 'photo', 'total_delivery', 'status'];

    if ($hasSearch || $hasPagination) {
        $validatedData = $request->validate([
            'search' => 'nullable|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $query = User::where('role', 'shopper')->select($selectFields);

        if ($hasSearch) {
            $searchTerm = trim((string) $request->query('search'));
            $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('address', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%');
            });
        }

        $perPage = $request->query('per_page', 10);
        $shoppers = $query->orderBy('id', 'desc')->paginate($perPage);

        // Transform photo URLs and ensure status is present
        $shoppers->getCollection()->transform(function ($shopper) {
            $shopper->photo = $this->buildImageUrl($shopper->photo);
            $shopper->status = $shopper->status ?? 'inactive';
            return $shopper;
        });

        // Calculate total and total_active_shoppers for paginated results
        $total = $shoppers->total();
        $totalActive = User::where('role', 'shopper')->where('status', 'active');
        if ($hasSearch) {
            $totalActive->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('address', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('email', 'LIKE', '%' . $searchTerm . '%')
                  ->orWhere('phone', 'LIKE', '%' . $searchTerm . '%');
            });
        }
        $total_active_shoppers = $totalActive->count();

        $response = [
            'status' => true,
            'message' => 'All shoppers fetched successfully',
            'total' => $total,
            'total_active_shoppers' => $total_active_shoppers,
            'data' => $shoppers->items(),
        ];

        return response()->json($response);
    }

    $shoppers = User::where('role', 'shopper')
        ->select($selectFields)
        ->orderBy('id', 'desc')
        ->get();

    // Transform photo URLs and ensure status is present
    $shoppers = $shoppers->map(function ($shopper) {
        $shopper->photo = $this->buildImageUrl($shopper->photo);
        $shopper->status = $shopper->status ?? 'inactive';
        return $shopper;
    });

    $total = $shoppers->count();
    $total_active_shoppers = $shoppers->where('status', 'active')->count();

    return response()->json([
        'status' => true,
        'message' => 'All shoppers fetched successfully',
        'total' => $total,
        'total_active_shoppers' => $total_active_shoppers,
        'data' => $shoppers,
    ]);
}

    public function pendingOrders(Request $request)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 10);
        $page = $request->input('page', 1);

        $auth = auth()->user();
        //dd($auth);

        $pendingOrders = Order::where('shopper_id', $auth->id)
            ->where(function($query) {
                $query->where('status', 'order_confirmed')
                      ->orWhere('status', 'order_pickedup');
            })
            ->with(['orderItems', 'payments', 'user'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

            //dd($pendingOrders);

        return response()->json([
            'status' => true,
            'message' => 'Recent orders retrieved successfully',
            'data' => $pendingOrders->map(function($order) {
                return [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'user_name' => $order->user->name,
                    'status' => $order->status,
                    'total_price' => $order->total
                ];
            }),
        ]);
    }

    public function pendingOrderDetails($id)
    {
        try {
            $auth = auth()->user();
            
            $order = Order::where('id', $id)
                ->where('shopper_id', $auth->id)
                ->with(['orderItems', 'user', 'payments'])
                ->first();

            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found'
                ], 404);
            }



            // Compute nearest store to the authenticated user's location
            $nearestStore = $this->nearestStoreForOrder($order, $auth);

            // Prepare coordinates and distances
            $authLat = optional($auth->userlocations)->latitude;
            $authLng = optional($auth->userlocations)->longitude;
            $custLat = optional($order->user->userlocations)->latitude;
            $custLng = optional($order->user->userlocations)->longitude;
            $distanceKm = function($lat1, $lon1, $lat2, $lon2) {
                $earthRadius = 6371; // km
                $dLat = deg2rad($lat2 - $lat1);
                $dLon = deg2rad($lon2 - $lon1);
                $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
                $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                return $earthRadius * $c;
            };
            $dropOffDistance = (!is_null($authLat) && !is_null($authLng) && !is_null($custLat) && !is_null($custLng))
                ? round($distanceKm((float)$authLat, (float)$authLng, (float)$custLat, (float)$custLng), 2)
                : null;

            return response()->json([
                'status' => true,
                'message' => 'Order details retrieved successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'total_price' => $order->total,
                    'tax' => $order->tax,
                    'delivery_charges' => $order->delivery_charges,
                    'delivery_date' => $order->delivery_date,
                    'delivery_time' => $order->delivery_time,
                    'delivery_notes' => $order->delivery_notes,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,

                    'user' => [
                        'id' => $auth->id,
                        'address' => $auth->address,
                        'latitude' => $auth->userlocations->latitude,
                        'longitude' => $auth->userlocations->longitude,
                    ],
                    'pick_up_location' => [
                        'name' => $nearestStore['name'] ?? null,
                        'address' => $nearestStore['address'] ?? null,
                        'latitude' => $nearestStore['address']['latitude'] ?? null,
                        'longitude' => $nearestStore['address']['longitude'] ?? null,
                        'distance_km' => $nearestStore['distance_km'] ?? null,
                        'eta_minutes' => isset($nearestStore['distance_km']) ? (int) round($nearestStore['distance_km'] * 20) : null,
                    ],

                    'drop_off_location' => [
                        'id' => $order->user->id,
                        'address' => $order->user->address,
                        'latitude' => $custLat,
                        'longitude' => $custLng,
                        'distance_km' => $dropOffDistance,
                        'eta_minutes' => isset($dropOffDistance) ? (int) round($dropOffDistance * 20) : null,
                    ],
                    
                    // Customer information
                    'customer' => [
                        'id' => $order->user->id,
                        'name' => $order->user->name,
                        'email' => $order->user->email,
                        'phone' => $order->user->phone,
                        'address' => $order->user->address,
                        'photo' => $order->user->photo ? asset($order->user->photo) : null,
                        'latitude' => $order->user->userlocations->latitude,
                        'longitude' => $order->user->userlocations->longitude,
                    ],
                    
                    // Store information
                    'stores' => $order->orderItems->map(function($item) {
                        return $item->storeName ?? null;
                    })->unique()->values(),
                    'nearest_store' => $nearestStore,
                    
                    // Order items
                    'items' => $order->orderItems->map(function($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'unit_price' => $item->unit_price,
                            'quantity' => $item->quantity,
                            'total_price' => $item->total_price,
                            'product_notes' => $item->product_notes,
                            'store_name' => $item->storeName ?? null,
                            'product_image' => $item->images ?? 'uploads/profiles/no_image.jpeg',
                        ];
                    }),

                    
                    
                    // Payment information
                    'payment' => $order->payments->first() ? [
                        'id' => $order->payments->first()->id,
                        'payment_method' => $order->payments->first()->payment_method,
                        'payment_status' => $order->payments->first()->payment_status,
                        'amount' => $order->payments->first()->amount,
                        'transaction_id' => $order->payments->first()->transaction_id,
                        'created_at' => $order->payments->first()->created_at,
                    ] : null,
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve order details'
            ], 500);
        }
    }

    private function nearestStoreForOrder($order, $authUser)
    {
        $userLat = optional($authUser->userlocations)->latitude;
        $userLng = optional($authUser->userlocations)->longitude;
        if (is_null($userLat) || is_null($userLng)) {
            return null;
        }

        $storeNames = $order->orderItems->pluck('storeName')->filter()->unique()->values();
        if ($storeNames->isEmpty()) {
            return null;
        }

        $locations = Location::whereIn('storeName', $storeNames)->get();
        if ($locations->isEmpty()) {
            return null;
        }

        $distanceKm = function($lat1, $lon1, $lat2, $lon2) {
            $earthRadius = 6371; // km
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            return $earthRadius * $c;
        };

        $nearest = null;
        foreach ($locations as $loc) {
            if (!$loc->latLng) continue;
            $parts = explode(',', $loc->latLng);
            if (count($parts) !== 2) continue;
            $lat = is_numeric(trim($parts[0])) ? (float)trim($parts[0]) : null;
            $lng = is_numeric(trim($parts[1])) ? (float)trim($parts[1]) : null;
            if ($lat === null || $lng === null) continue;

            $dist = $distanceKm((float)$userLat, (float)$userLng, $lat, $lng);
            if ($nearest === null || $dist < $nearest['distance_km']) {
                $nearest = [
                    'name' => $loc->storeName,
                    'distance_km' => round($dist, 2),
                    'address' => [
                        'addressLine1' => $loc->addressLine1,
                        'city' => $loc->city,
                        'state' => $loc->state,
                        'zipCode' => $loc->zipCode,
                        'latitude' => $lat,
                        'longitude' => $lng,
                    ],
                ];
            }
        }

        return $nearest;
    }



    public function orderPickedUp(Request $request)
    {
        $validatedData = $request->validate([
            'order_id' => 'required|exists:orders,id',
            'order_item_id' => 'required|string',
        ]);

        $auth = auth()->user();

        $order = Order::where('id', $validatedData['order_id'])
            ->where('shopper_id', $auth->id)
            ->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found for this shopper'
            ], 404);
        }

        // Normalize order_item_id as array of integers
        $newItemIds = collect(explode(',', (string)$validatedData['order_item_id']))
            ->map(fn($v) => (int) trim($v))
            ->filter(fn($v) => $v > 0)
            ->unique()
            ->values();

        if ($newItemIds->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'order_item_id cannot be empty'
            ], 422);
        }

        // Check that all order_item_ids belong to the given order_id
        $count = \App\Models\OrderItem::where('order_id', $order->id)
            ->whereIn('id', $newItemIds)
            ->count();

        if ($count !== $newItemIds->count()) {
            return response()->json([
                'status' => false,
                'message' => 'One or more order_item_id do not belong to the given order.'
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Delete all previous picked_up_items for this order
            DB::table('picked_up_items')
                ->where('order_id', $order->id)
                ->delete();

            // Insert new record
            DB::table('picked_up_items')->insert([
                'order_id' => $order->id,
                'order_item_id' => $newItemIds->implode(','),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $order->status = 'order_pickedup';
            $order->picked_up_at = now();
            $order->save();

            $order->loadMissing('orderItems');
            $missingAmount = $order->orderItems
                ->reject(function($item) use ($newItemIds) { 
                    return $newItemIds->contains((int)$item->id); 
                })
                ->sum(function($item){ 
                    return (float)$item->total_price; 
                });

            $adjustedTotal = max(0, (float)$order->total - (float)$missingAmount);

            $payment = Payment::where('order_id', $order->id)->first();
            if ($payment) {
                $payment->shopper_amount = $missingAmount;
                $payment->save();
            }

            Notification::create([
                'user_id' => $order->user_id,
                'title' => 'Your order has been picked up',
                'message' => 'Order ' . $order->order_number . ' has been picked up. Total $' . number_format($adjustedTotal, 2),
                'image' => null,
                'type' => 'order pickedup',
                'order_id' => $order->id,
                'shopper_id' => $order->shopper_id,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Order items marked as picked up',
                'data' => [
                    'order_id' => $order->id,
                    'order_item_id' => $newItemIds->implode(','),
                    'status' => $order->status,
                    'picked_up_at' => $order->picked_up_at,
                    'adjusted_total' => (float) number_format($adjustedTotal, 2, '.', ''),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Failed to mark items as picked up'
            ], 500);
        }
    }
    public function getUserAndShopperlatLong(Request $request)
    {
        $validatedData = $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        $auth = auth()->user();

        $order = Order::where('id', $validatedData['order_id'])->first();
        //dd($order);
        $userLat = optional($order->user->userlocations)->latitude;
        $userLng = optional($order->user->userlocations)->longitude;
        $shopperLat = optional($auth->userlocations)->latitude;
        $shopperLng = optional($auth->userlocations)->longitude;

        return response()->json([
            'status' => true,
            'message' => 'User and Shopper location found',
            'data' => [
                'userLat' => $userLat,
                'userLng' => $userLng,
                'shopperLat' => $shopperLat,
                'shopperLng' => $shopperLng,
            ]
        ]);
    }

    public function sendDeliveryRequest(Request $request)
    {
        $validatedData = $request->validate([
            'order_id' => 'required|exists:orders,id',
        ]);

        DB::beginTransaction();
        try {
            $auth = auth()->user();
            $order = Order::where('id', $validatedData['order_id'])
                ->where('shopper_id', $auth->id)
                ->firstOrFail();

            // Verify order is in a valid state for delivery
            if (!in_array($order->status, ['order_pickedup'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order is not ready for delivery'
                ], 400);
            }



            // Get payment details if available
            $payment = Payment::where('order_id', $order->id)->first();
            $message = 'Your order has been arrived. Please collect it';
            
            if ($payment && !is_null($payment->shopper_amount)) {
                $message .= sprintf('. You will get $%.2f', $payment->shopper_amount);
            }

            // Create notification for the customer
            Notification::create([
                'user_id' => $order->user_id,
                'title' => "Your order #{$order->order_number} has been arrive",
                'message' => $message,
                'image' => null,
                'type' => 'shopper arrive',
                'order_id' => 53,
                'shopper_id' => $auth->id,
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Delivery notification sent successfully',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'updated_at' => $order->updated_at
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Order not found or you do not have permission'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delivery notification failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to process delivery notification'
            ], 500);
        }
    }


public function activeInactiveShopper(Request $request)
{
    $validatedData = $request->validate([
        'status' => 'required|string|in:active,inactive',
    ]);

    $user = auth()->user();
    $user->status = $validatedData['status'];
    $user->save();

    return response()->json([
        'success' => true,
        'message' => 'Shopper status updated successfully.',
        'data' => [
            'status' => $user->status,
        ],
    ]);
}

    public function getShopperStatus(Request $request)
    {
        $user = auth()->user();

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $user->status,
            ],
        ]);
    }


public function updateShopper(Request $request)
{
    $validatedData = $request->validate([
        'shopper_id' => 'required|exists:users,id',
        'order_id' => 'required|exists:orders,id',
    ]);

    // Find the order
    $order = Order::find($validatedData['order_id']);
    if (!$order) {
        return response()->json([
            'success' => false,
            'message' => 'Order not found.',
        ], 404);
    }

    // Find the new shopper
    $shopper = User::where('id', $validatedData['shopper_id'])
        ->where('role', 'shopper')
        ->first();

    if (!$shopper) {
        return response()->json([
            'success' => false,
            'message' => 'Shopper not found.',
        ], 404);
    }

    // Assign the new shopper to the order
    $order->shopper_id = $shopper->id;
    $order->save();

    return response()->json([
        'success' => true,
        'message' => 'Shopper assigned to order successfully.',
        'data' => [
            'order_id' => $order->id,
            'shopper' => [
                'id' => $shopper->id,
                'name' => $shopper->name,
                'email' => $shopper->email,
                'phone' => $shopper->phone,
                'address' => $shopper->address,
                'photo' => $this->buildImageUrl($shopper->photo),
            ],
        ],
    ]);
}
}
