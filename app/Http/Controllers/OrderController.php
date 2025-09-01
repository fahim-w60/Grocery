<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Order;
use App\Models\OrderItem;
use Exception;
use Carbon\Carbon;
use App\Models\Notification;

class OrderController extends Controller
{
    public function getOrders()
    {
        try {
            $orders = Order::where('user_id', Auth::id())
                ->with(['orderItems.product', 'payments'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $orders->map(function($order) {
                    $statusTimeline = [
                        'order_cancelled' => [
                            'label' => 'Order Cancelled',
                            'completed' => $order->cancelled_at !== null,
                            'timestamp' => $order->cancelled_at ? Carbon::parse($order->cancelled_at)->format('Y-m-d\TH:i:s') : null
                        ],
                        'order_placed' => [
                            'label' => 'Order Placed',
                            'completed' => true,
                            'timestamp' => Carbon::parse($order->created_at)->format('Y-m-d\TH:i:s')
                        ],
                        'order_confirmed' => [
                            'label' => 'Order Confirmed',
                            'completed' => $order->confirmed_at !== null,
                            'timestamp' => $order->confirmed_at ? Carbon::parse($order->confirmed_at)->format('Y-m-d\TH:i:s') : null
                        ],
                        'order_pickedup' => [
                            'label' => 'Order Picked Up',
                            'completed' => $order->picked_up_at !== null,
                            'timestamp' => $order->picked_up_at ? Carbon::parse($order->picked_up_at)->format('Y-m-d\TH:i:s') : null
                        ],
                        'out_for_delivery' => [
                            'label' => 'Out for Delivery',
                            'completed' => $order->out_for_delivery_at !== null,
                            'timestamp' => $order->out_for_delivery_at ? Carbon::parse($order->out_for_delivery_at)->format('Y-m-d\TH:i:s') : null
                        ],
                        'order_delivered' => [
                            'label' => 'Delivered',
                            'completed' => $order->delivered_at !== null,
                            'timestamp' => $order->delivered_at ? Carbon::parse($order->delivered_at)->format('Y-m-d\TH:i:s') : null
                        ]
                    ];

                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'status' => $order->status,
                        'price' => $order->total,
                        'tax' => $order->tax,
                        'delivery_charges' => $order->delivery_charges,
                        'delivery_date' => $order->delivery_date,
                        'delivery_time' => $order->delivery_time,
                        'shopper_id' => $order->shopper_id,
                        'created_at' => $order->created_at,
                        'items' => $order->orderItems->count(),
                        'payment_status' => $order->payments->first()->payment_status ?? 'pending',
                        'status_timeline' => $statusTimeline
                    ];
                })
            ]);

        } catch (Exception $e) {
            Log::error('Get orders error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve orders'
            ], 500);
        }
    }

    public function getOrderDetails($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['orderItems.product', 'payments'])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'price' => $order->total,
                    'tax' => $order->tax,
                    'delivery_charges' => $order->delivery_charges,
                    'delivery_date' => $order->delivery_date,
                    'delivery_time' => $order->delivery_time,
                    'delivery_notes' => $order->delivery_notes,
                    'shopper_id' => $order->shopper_id,
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at,
                    'items' => $order->orderItems->map(function($item) {
                        return [
                            'id' => $item->id,
                            'product_id' => $item->product_id,
                            'product_name' => $item->product_name,
                            'unit_price' => $item->unit_price,
                            'quantity' => $item->quantity,
                            'total_price' => $item->total_price,
                            'product_notes' => $item->product_notes,
                            'product' => $item->product ? [
                                'id' => $item->product->id,
                                'name' => $item->product->name,
                                'image' => $item->product->image
                            ] : null
                        ];
                    }),
                    'payment' => $order->payments->first() ? [
                        'id' => $order->payments->first()->id,
                        'payment_status' => $order->payments->first()->payment_status,
                        'payment_method' => $order->payments->first()->payment_method,
                        'transaction_id' => $order->payments->first()->transaction_id,
                        'amount' => $order->payments->first()->amount
                    ] : null
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get order details error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order details'
            ], 500);
        }
    }

    public function updateOrderStatus(Request $request, $id)
    {
        try {
            $request->validate([
                'status' => 'required|string|in:order_placed,order_confirmed,order_pickedup,out_for_delivery,order_delivered,order_cancelled'
            ]);

            $order = Order::find($id);
            //dd($order);
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Update status and set timestamp
            $updateData = ['status' => $request->status];
            $currentTime = now();

            if ($request->status == 'order_cancelled') {
                $updateData['cancelled_at'] = $currentTime;
            }
            
            // Set timestamps based on current status and handle skipped statuses
            switch ($request->status) {
                case 'order_confirmed':
                    $updateData['confirmed_at'] = $currentTime;
                    Notification::create([
                        'user_id' => $order->user_id,
                        'title' => 'Your order has been confirmed',
                        'message' => 'Your order has been confirmed. Total $' . number_format($order->total, 2),
                        'image' => null,
                        'type' => 'normal notification',
                        'order_id' => $order->id,
                    ]);
                    break;
                case 'order_pickedup':
                    // $updateData['confirmed_at'] = $order->confirmed_at ?? $currentTime;
                    $updateData['picked_up_at'] = $currentTime;
                    Notification::create([
                        'user_id' => $order->user_id,
                        'title' => 'Your order has been picked up',
                        'message' => 'Your order has been picked up. Total $' . number_format($order->total, 2),
                        'image' => null,
                        'type' => 'order pickedup',
                        'order_id' => $order->id,
                        'shopper_id' => $order->shopper_id,
                    ]);
                    break;
                case 'out_for_delivery':
                    $updateData['confirmed_at'] = $order->confirmed_at ?? $currentTime;
                    $updateData['picked_up_at'] = $order->picked_up_at ?? $currentTime;
                    $updateData['out_for_delivery_at'] = $currentTime;
                    break;
                case 'order_delivered':
                    $updateData['delivered_at'] = $currentTime;
                    
                    // Update shopper's total_delivery count
                    if ($order->shopper_id) {
                        $shopper = \App\Models\User::find($order->shopper_id);
                        if ($shopper) {
                            $shopper->increment('total_delivery');
                        }
                    }
                    
                    // Create delivery notification for specific users (1 and 30)
                    $userIds = [1, 30];
                    foreach ($userIds as $userId) {
                        Notification::create([
                            'user_id' => $userId,
                            'title' => 'Order Delivered',
                            'message' => 'order #' . $order->order_number . ' has been delivered successfully!',
                            'image' => null,
                            'type' => 'order_delivered',
                            'order_id' => $order->id,
                            'shopper_id' => $order->shopper_id,
                        ]);
                    }
                    break;
                case 'order_placed':
                    // If reverting to placed, clear all timestamps
                    $updateData['confirmed_at'] = null;
                    $updateData['picked_up_at'] = null;
                    $updateData['out_for_delivery_at'] = null;
                    $updateData['delivered_at'] = null;
                    break;
            }
            
            $order->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Order status updated successfully',
                'data' => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'status' => $order->status,
                    'updated_at' => $order->updated_at
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Update order status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update order status'
            ], 500);
        }
    }

    public function trackOrder($id)
    {
        try {
            $order = Order::where('id', $id)
                ->where('user_id', Auth::id())
                ->with(['payments', 'orderItems'])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $statusTimeline = [
                'order_cancelled' => [
                    'label' => 'Order Cancelled',
                    'completed' => $order->cancelled_at !== null,
                    'timestamp' => $order->cancelled_at ? Carbon::parse($order->cancelled_at)->format('Y-m-d\TH:i:s') : null
                ],
                'order_placed' => [
                    'label' => 'Order Placed',
                    'completed' => true,
                    'timestamp' => Carbon::parse($order->created_at)->format('Y-m-d\TH:i:s')
                ],
                'order_confirmed' => [
                    'label' => 'Order Confirmed',
                    'completed' => $order->confirmed_at !== null,
                    'timestamp' => $order->confirmed_at ? Carbon::parse($order->confirmed_at)->format('Y-m-d\TH:i:s') : null
                ],
                'order_pickedup' => [
                    'label' => 'Order Picked Up',
                    'completed' => $order->picked_up_at !== null,
                    'timestamp' => $order->picked_up_at ? Carbon::parse($order->picked_up_at)->format('Y-m-d\TH:i:s') : null
                ],
                'out_for_delivery' => [
                    'label' => 'Out for Delivery',
                    'completed' => $order->out_for_delivery_at !== null,
                    'timestamp' => $order->out_for_delivery_at ? Carbon::parse($order->out_for_delivery_at)->format('Y-m-d\TH:i:s') : null
                ],
                'order_delivered' => [
                    'label' => 'Delivered',
                    'completed' => $order->delivered_at !== null,
                    'timestamp' => $order->delivered_at ? Carbon::parse($order->delivered_at)->format('Y-m-d\TH:i:s') : null
                ]
            ];

            $estimatedDelivery = null;
            if ($order->delivery_date && $order->delivery_time) {
                $estimatedDelivery = $order->delivery_date . ' ' . $order->delivery_time;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'current_status' => $order->status,
                    'status_label' => ucfirst(str_replace('_', ' ', $order->status)),
                    'estimated_delivery' => $estimatedDelivery,
                    'delivery_date' => $order->delivery_date,
                    'delivery_time' => $order->delivery_time,
                    'shopper_id' => $order->shopper_id,
                    'total' => $order->total,
                    'payment_status' => $order->payments->first()->payment_status ?? 'pending',
                    'timeline' => $statusTimeline,
                    'is_cancelled' => $order->status === 'order_cancelled',
                    'items' => $order->orderItems->count(),
                    'created_at' => $order->created_at,
                    'updated_at' => $order->updated_at
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get order status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve order status'
            ], 500);
        }
    }

    public function newOrder(Request $request)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $newOrder = Order::where('status', 'order_placed')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $newOrder->items(),
        ]);
    }

    public function pendingOrder(Request $request)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]); 

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $newOrder = Order::where('status', 'order_confirmed')->orWhere('status','order_pickedup')->orWhere('status','out_for_delivery')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $newOrder->items(),
        ]);
    }

    public function completeOrder(Request $request)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $newOrder = Order::where('status', 'order_delivered')->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $newOrder->items(),
        ]);
    }

    public function orderDetailsForAdmin($id)
    {
        $order = Order::where('id', $id)->with(['orderItems.product', 'payments'])->first();

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
    }

    public function deleteOrder($id)
    {
        $order = Order::where('id', $id)->delete();
        return response()->json([
            'success' => true,
            'message' => 'Order deleted successfully',
        ]);
    }

    public function allOrders(Request $request)
    {
        $validatedData = $request->validate([
            'status' => 'sometimes|string|in:new,pending,complete,cancel',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $query = Order::with([
            'user:id,name,photo',
            'orderItems' => function($q) {
                $q->select('id','order_id','product_id','product_name','unit_price','quantity','total_price','product_notes','storeName','productId','images');
            }
        ]);

        if ($request->filled('status')) {
            switch ($request->status) {
                case 'new':
                    $query->where('status', 'order_placed');
                    break;
                case 'pending':
                    $query->whereIn('status', ['order_confirmed', 'order_pickedup', 'out_for_delivery']);
                    break;
                case 'complete':
                    $query->where('status', 'order_delivered');
                    break;
                case 'cancel':
                    $query->where('status', 'order_cancelled');
                    break;
            }
        }

        $orders = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

        $data = $orders->getCollection()->map(function($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'price' => $order->total,
                'tax' => $order->tax,
                'delivery_charges' => $order->delivery_charges,
                'created_at' => $order->created_at,
                'shopper_id' => $order->shopper_id,
                'shopper_name' => $order->shopper ? $order->shopper->name : null,
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'photo' => asset($order->user->photo ?: 'uploads/profiles/no_image.jpeg'),
                ] : null,
                'order_items' => $order->orderItems->map(function($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'product_name' => $item->product_name,
                        'unit_price' => (float)$item->unit_price,
                        'quantity' => (int)$item->quantity,
                        'total_price' => (float)$item->total_price,
                        'product_notes' => $item->product_notes,
                        'store_name' => $item->storeName ?? null,
                        'product_external_id' => $item->productId ?? null,
                        'images' => $item->images ?? asset('uploads/profiles/no_image.jpeg'),
                    ];
                })
            ];
        });

        return response()->json([
            'success' => true,
            'total' => $orders->total(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'per_page' => $orders->perPage(),
                'last_page' => $orders->lastPage(),
            ],
            'data' => $data->values(),
        ]);
    }

public function pickedUpItems(Request $request)
{
    $validatedData = $request->validate([
        'order_id' => 'required|exists:orders,id',
    ]);

    try {
        $order = Order::findOrFail($validatedData['order_id']);

        // Get all picked up item records for this order
        $pickedUpRecords = \DB::table('picked_up_items')
            ->where('order_id', $order->id)
            ->get();

        // Collect all picked up item IDs (flattened)
        $pickedUpItemIds = $pickedUpRecords->flatMap(function($record) {
            return collect(explode(',', $record->order_item_id))
                ->map(fn($id) => (int) trim($id))
                ->filter(fn($id) => $id > 0);
        })->unique()->values();

        // Fetch item details
        $pickedUpItems = OrderItem::whereIn('id', $pickedUpItemIds)->get();

        // Build the response data array
        $data = $pickedUpItems->map(function($item) use ($order) {
            return [
                'id' => $item->id,
                'product_name' => $item->product_name,
                'product_image' => $item->images,
                'unit_price' => (float) $item->unit_price,
                'quantity' => (int) $item->quantity,
                'total' => (float) $item->total_price,
                'picked_at' => $order->picked_up_at ? Carbon::parse($order->picked_up_at)->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Picked up items retrieved successfully',
            'data' => $data,
        ]);

    } catch (Exception $e) {
        Log::error('Error fetching picked up items: ' . $e->getMessage());
        return response()->json(['success' => false, 'message' => 'Failed to fetch picked up items.'], 500);
    }
}
public function allShopperOrders(Request $request)
{
    $validatedData = $request->validate([
        'status' => 'sometimes|string|in:new,pending,complete,cancel',
        'per_page' => 'sometimes|integer|min:1|max:100',
        'page' => 'sometimes|integer|min:1',
    ]);

    $perPage = $request->input('per_page', 20);
    $page = $request->input('page', 1);
    $shopperId = auth()->id();

    $query = Order::where('shopper_id', $shopperId)->with([
        'user:id,name,email'
    ]);

    if ($request->filled('status')) {
        switch ($request->status) {
            case 'new':
                $query->where('status', 'order_placed');
                break;
            case 'pending':
                $query->whereIn('status', ['order_confirmed', 'order_pickedup', 'out_for_delivery']);
                break;
            case 'complete':
                $query->where('status', 'order_delivered');
                break;
            case 'cancel':
                $query->where('status', 'order_cancelled');
                break;
        }
    }

    $orders = $query->orderBy('id', 'desc')->paginate($perPage, ['*'], 'page', $page);

    $data = $orders->getCollection()->map(function($order) {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
            'total' => $order->total,
            'user' => $order->user ? [
                'user_id' => $order->user->id,
                'name' => $order->user->name,
                'email' => $order->user->email,
            ] : null,
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $data->values(),
    ]);
}
}
