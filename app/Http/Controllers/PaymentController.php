<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Payment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\User;

use Exception;

class PaymentController extends Controller
{
    public function createPaymentIntent(Request $request)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            
            // Validate request
            $request->validate([
                'delivery_date' => 'required|date|after:today',
                'delivery_time' => 'required|string',
                'shopper_id' => 'required|string|max:500',
            ]);

            // Get user's cart items
            $cartItems = Cart::where('user_id', $user->id)
                ->with('product')
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your cart is empty'
                ], 400);
            }

            // Calculate totals
            $subtotal = 0;
            foreach ($cartItems as $item) {
                if (!$item->product) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some products in your cart are no longer available'
                    ], 400);
                }
                if($item->product->promo_price == 0)
                {
                    $subtotal += round($item->product->regular_price * $item->quantity, 2);
                }
                else
                {
                    $subtotal += round($item->product->promo_price * $item->quantity, 2);
                }                
            }

            // Calculate tax and delivery charges
            $taxRate = 0.08; // 8% tax (adjust as needed)
            $tax = $subtotal * $taxRate;
            $deliveryCharges = round(5.00, 2); // Fixed delivery charge (adjust as needed)
            $total = $subtotal + $tax + $deliveryCharges;

            // Convert to cents for Stripe (Stripe uses smallest currency unit)
            $amountInCents = intval($total * 100);

            DB::beginTransaction();

            // Generate unique order number
            $orderNumber = 'ORD-' . strtoupper(uniqid());

            // Create order record first
            $order = Order::create([
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'status' => 'order_placed',
                'delivery_date' => $request->delivery_date,
                'delivery_time' => $request->delivery_time,
                'shopper_id' => $request->shopper_id,
                'tax' => $tax,
                'delivery_charges' => $deliveryCharges,
                'total' => $total
            ]);

            // Set Stripe API key
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            // Create Stripe Payment Intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => 'usd', // Change to your currency
                'payment_method_types' => ['card'],
                'metadata' => [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'order_number' => $orderNumber
                ]
            ]);

            // Create order items from cart items
            foreach ($cartItems as $item) {
                $itemPrice = ($item->product->promo_price == 0) 
                    ? $item->product->regular_price 
                    : $item->product->promo_price;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'unit_price' => round($itemPrice, 2),
                    'quantity' => $item->quantity,
                    'total_price' => round($itemPrice * $item->quantity, 2),
                    'product_notes' => null
                ]);
            }

            // Create payment record
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'card',
                'amount' => $total,
                'transaction_id' => $paymentIntent->id,
                'payment_status' => 'completed'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_intent_id' => $paymentIntent->id,
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'payment_status' => $payment->payment_status,
                    'order_number' => $orderNumber,
                    'amount' => $total,
                    'breakdown' => [
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'delivery_charges' => $deliveryCharges,
                        'total' => $total
                    ]
                ]
            ]);

        } catch (\Stripe\Exception\ApiErrorException $e) {
            DB::rollback();
            Log::error('Stripe API error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Payment service error. Please try again.'
            ], 500);
            
        } catch (Exception $e) {
            DB::rollback();
            Log::error('Payment intent creation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent'
            ], 500);
        }
    }

    public function reorder(Request $request, $order_id)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            
            // Validate request
            $request->validate([
                'delivery_date' => 'required|date|after:today',
                'delivery_time' => 'required|string',
                'shopper_id' => 'required|string|max:500',
            ]);

            // Get the original order
            $originalOrder = Order::where('id', $order_id)
                ->where('user_id', $user->id)
                ->with('orderItems.product')
                ->first();

            if (!$originalOrder) {
                return response()->json([
                    'success' => false,
                    'message' => 'Original order not found'
                ], 404);
            }

            // Calculate totals from original order
            $subtotal = 0;
            foreach ($originalOrder->orderItems as $item) {
                if ($item->product->promo_price == 0) {
                    $subtotal += round($item->product->regular_price * $item->quantity, 2);
                } else {
                    $subtotal += round($item->product->promo_price * $item->quantity, 2);
                }
            }

            // Calculate tax and delivery charges
            $taxRate = 0.08; // 8% tax (adjust as needed)
            $tax = $subtotal * $taxRate;
            $deliveryCharges = round(5.00, 2); // Fixed delivery charge (adjust as needed)
            $total = $subtotal + $tax + $deliveryCharges;

            // Convert to cents for Stripe
            $amountInCents = intval($total * 100);

            DB::beginTransaction();

            // Generate new order number
            $orderNumber = 'ORD-' . strtoupper(uniqid());

            // Create new order with same details
            $newOrder = Order::create([
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'status' => 'order_placed',
                'delivery_date' => $request->delivery_date,
                'delivery_time' => $request->delivery_time,
                'shopper_id' => $request->shopper_id,
                'tax' => $tax,
                'delivery_charges' => $deliveryCharges,
                'total' => $total,
                'original_order_id' => $order_id // Store reference to original order
            ]);

            // Set Stripe API key
            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET'));

            // Create Stripe Payment Intent
            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $amountInCents,
                'currency' => 'usd',
                'payment_method_types' => ['card'],
                'metadata' => [
                    'order_id' => $newOrder->id,
                    'user_id' => $user->id,
                    'order_number' => $orderNumber,
                    'original_order_id' => $order_id
                ]
            ]);

            // Clone order items from original order
            foreach ($originalOrder->orderItems as $item) {
                $itemPrice = ($item->product->promo_price == 0) 
                    ? $item->product->regular_price 
                    : $item->product->promo_price;
                
                OrderItem::create([
                    'order_id' => $newOrder->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product->name,
                    'unit_price' => round($itemPrice, 2),
                    'quantity' => $item->quantity,
                    'total_price' => round($itemPrice * $item->quantity, 2),
                    'product_notes' => $item->product_notes
                ]);
            }

            // Create payment record
            $payment = Payment::create([
                'order_id' => $newOrder->id,
                'payment_method' => 'card',
                'amount' => $total,
                'transaction_id' => $paymentIntent->id,
                'payment_status' => 'pending'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment intent created successfully',
                'data' => [
                    'client_secret' => $paymentIntent->client_secret,
                    'payment_id' => $payment->id,
                    'order_id' => $newOrder->id,
                    'order_number' => $orderNumber,
                    'amount' => $total,
                    'breakdown' => [
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'delivery_charges' => $deliveryCharges,
                        'total' => $total
                    ]
                ]
            ]);

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Reorder error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create reorder'
            ], 500);
        }
    }

    public function confirmPayment(Request $request)
    {
        try {
            $request->validate([
                'payment_id' => 'required|integer|exists:payments,id',
                'payment_intent_id' => 'required|string'
            ]);

            $payment = Payment::with('order')->find($request->payment_id);
            
            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Verify payment belongs to authenticated user
            if ($payment->order->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to payment'
                ], 403);
            }

            DB::beginTransaction();

            // Update payment status
            $payment->update([
                'payment_status' => 'completed',
                'transaction_id' => $request->payment_intent_id
            ]);

            // Update order status
            $payment->order->update([
                'status' => 'order_confirmed'
            ]);

            // Clear user's cart after successful payment
            Cart::where('user_id', Auth::id())->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payment confirmed successfully',
                'data' => [
                    'order_id' => $payment->order->id,
                    'order_number' => $payment->order->order_number,
                    'payment_status' => $payment->payment_status,
                    'order_status' => $payment->order->status
                ]
            ]);

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Payment confirmation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm payment'
            ], 500);
        }
    }

    public function getPaymentStatus($payment_id)
    {
        try {
            $payment = Payment::with(['order' => function($query) {
                $query->select('id', 'user_id', 'order_number', 'status', 'total', 'delivery_date', 'delivery_time');
            }])->find($payment_id);

            if (!$payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found'
                ], 404);
            }

            // Verify payment belongs to authenticated user
            if ($payment->order->user_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to payment'
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'payment_id' => $payment->id,
                    'payment_status' => $payment->payment_status,
                    'payment_method' => $payment->payment_method,
                    'amount' => $payment->amount,
                    'transaction_id' => $payment->transaction_id,
                    'created_at' => $payment->created_at,
                    'order' => [
                        'id' => $payment->order->id,
                        'order_number' => $payment->order->order_number,
                        'status' => $payment->order->status,
                        'total' => $payment->order->total,
                        'delivery_date' => $payment->order->delivery_date,
                        'delivery_time' => $payment->order->delivery_time
                    ]
                ]
            ]);

        } catch (Exception $e) {
            Log::error('Get payment status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get payment status'
            ], 500);
        }
    }

    public function getAllTransactions()
    {
        try {
            // Get user's completed payments through order relationship
            $transactions = Payment::with(['order' => function($query) {
                $query->select('id', 'order_number', 'status', 'total', 'created_at');
            }])
            ->whereHas('order', function($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('payment_status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'order_number' => $payment->order->order_number,
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'transaction_id' => $payment->transaction_id,
                    'order_status' => $payment->order->status,
                    'order_total' => $payment->order->total,
                    'created_at' => $payment->created_at,
                    'order_created_at' => $payment->order->created_at
                ];
            });

            if ($transactions->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No transactions found',
                    'data' => []
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $transactions
            ]);

        } catch (Exception $e) {
            Log::error('Get all transactions error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve transactions. Error: ' . $e->getMessage()
            ], 500);
        }
    }
}
