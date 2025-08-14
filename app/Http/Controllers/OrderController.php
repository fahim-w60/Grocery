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

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Update status and set timestamp
            $updateData = ['status' => $request->status];
            $currentTime = now();
            
            // Set timestamps based on current status and handle skipped statuses
            switch ($request->status) {
                case 'order_confirmed':
                    $updateData['confirmed_at'] = $currentTime;
                    break;
                case 'order_pickedup':
                    $updateData['confirmed_at'] = $order->confirmed_at ?? $currentTime;
                    $updateData['picked_up_at'] = $currentTime;
                    break;
                case 'out_for_delivery':
                    $updateData['confirmed_at'] = $order->confirmed_at ?? $currentTime;
                    $updateData['picked_up_at'] = $order->picked_up_at ?? $currentTime;
                    $updateData['out_for_delivery_at'] = $currentTime;
                    break;
                case 'order_delivered':
                    $updateData['confirmed_at'] = $order->confirmed_at ?? $currentTime;
                    $updateData['picked_up_at'] = $order->picked_up_at ?? $currentTime;
                    $updateData['out_for_delivery_at'] = $order->out_for_delivery_at ?? $currentTime;
                    $updateData['delivered_at'] = $currentTime;
                    break;
                case 'order_cancelled':
                    // If cancelling, keep existing timestamps but update status
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
}
