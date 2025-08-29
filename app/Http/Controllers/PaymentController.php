<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\User;
use App\Models\Product;
use App\Models\Notification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class PaymentController extends Controller
{
    /**
     * Normalize stablecoin symbols to NOWPayments supported tickers
     */
    private function normalizePayCurrency(string $cryptoCurrency): string
    {
        $upper = strtoupper($cryptoCurrency);
        // Default pass-through for BTC, ETH
        if (in_array($upper, ['BTC', 'ETH'])) {
            return $upper;
        }
        // Prefer TRC20 for USDT by default; adjust if you need ERC20
        if ($upper === 'USDT') {
            return 'USDTTRC20';
        }
        // Prefer ERC20 for USDC by default
        if ($upper === 'USDC') {
            return 'USDTERC20';
        }
        return $upper;
    }
    /**
     * Create a new crypto payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createCryptoPayment(Request $request)
    {
        DB::beginTransaction();
        
        try {
            $user = Auth::user();
            
            // Validate request
            $request->validate([
                'shopper_id' => 'required|integer|min:1',
                'crypto_currency' => 'required|string|in:BTC,ETH,USDT,USDC',
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
                    continue;
                }
                $itemPrice = ($item->product->promo_price == 0)
                    ? $item->product->regular_price
                    : $item->product->promo_price;
                $subtotal += round($itemPrice * $item->quantity, 2);
            }

            // Calculate tax and delivery charges
            $taxRate = 0.08; // 8% tax (same as Stripe)
            $tax = $subtotal * $taxRate;
            $deliveryCharges = round(5.00, 2); // Same as Stripe
            $total = $subtotal + $tax + $deliveryCharges;

            // Generate unique order number
            $orderNumber = 'CRYPT-' . strtoupper(uniqid());

            // Create order record
            $orderData = [
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'shopper_id' => (int) $request->shopper_id,
                'tax' => $tax,
                'delivery_charges' => $deliveryCharges,
                'total' => $total
            ];
            
            Log::info('Creating crypto order with data:', $orderData);
            
            $order = Order::create($orderData);

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
                    'product_notes' => null,
                   'storeName' => $item->product->storeName ?? null,
                    'productId' => $item->product->productId ?? null,
                    'images' => $item->product->images ?? null
                ]);
            }

            

            // Prepare NOWPayments API request
            $apiKey = config('services.nowpayments.api_key');
            //dd($apiKey);
            $baseUrl = config('services.nowpayments.test_mode') 
                ? 'https://api-sandbox.nowpayments.io/v1/'
                : 'https://api.nowpayments.io/v1/';

            $client = new Client([
                'base_uri' => $baseUrl,
                'headers' => [
                    'x-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ]
            ]);

            // Add IPN secret to webhook URL if in production
            $webhookUrl = route('api.payments.crypto.webhook');
            if (!config('services.nowpayments.test_mode')) {
                $webhookUrl .= '?secret=' . urlencode(config('services.nowpayments.ipn_secret'));
            }

            // Normalize pay currency for NOWPayments (stablecoins require network-specific tickers)
            $payCurrency = $this->normalizePayCurrency($request->crypto_currency);

            $paymentData = [
                'price_amount' => $total,
                'price_currency' => 'USD',
                'order_id' => $order->id,
                'order_description' => "Order #{$order->id} payment",
                'ipn_callback_url' => $webhookUrl,
                'success_url' => route('api.payments.crypto.success', ['order_id' => $order->id]),
                'cancel_url' => route('api.payments.crypto.cancel', ['order_id' => $order->id]),
            ];

            // Make the API call to NOWPayments (invoice for hosted checkout URL)
            $response = $client->post('invoice', ['json' => $paymentData]);
            $responseData = json_decode($response->getBody()->getContents(), true);

            // Create payment record with pending status
            $payment = Payment::create([
                'order_id' => $order->id,
                'payment_method' => 'crypto',
                'amount' => $total,
                'currency' => 'USD',
                'payment_status' => 'pending',
                'transaction_id' => $responseData['id'] ?? ($responseData['invoice_id'] ?? ($responseData['payment_id'] ?? null)),
                'payment_date' => now(),
                'crypto_currency' => $payCurrency,
                'wallet_address' => $responseData['pay_address'] ?? null,
                'crypto_amount' => $responseData['pay_amount'] ?? null,
                'metadata' => [
                    'nowpayments_response' => $responseData,
                    'breakdown' => [
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'delivery_charges' => $deliveryCharges,
                        'total' => $total
                    ]
                ]
            ]);

            // Don't clear cart here - will be cleared after payment confirmation

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crypto payment initiated successfully',
                'data' => [
                    'payment_url' => $responseData['invoice_url']  ?? ($responseData['url'] ?? ($responseData['payment_url']  ?? ($responseData['checkout_url'] ?? null))),
                    'payment_id' => $payment->id,
                    'order_id' => $order->id,
                    'order_number' => $orderNumber,
                    'payment_status' => $payment->payment_status,
                    'crypto_amount' => $responseData['pay_amount'] ?? null,
                    'crypto_currency' => $payCurrency,
                    'payment_address' => $responseData['pay_address'] ?? null,
                    'expires_at' => $responseData['expired_time'] ?? ($responseData['expiration_estimate_date'] ?? ($responseData['valid_until'] ?? null)),
                    'breakdown' => [
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'delivery_charges' => $deliveryCharges,
                        'total' => $total
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Crypto payment error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to process crypto payment',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Handle NOWPayments webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    /**
     * Handle successful crypto payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cryptoPaymentSuccess(Request $request)
    {
        $orderId = $request->query('order_id');
        // Here you can add any additional logic for successful payments
        $order = Order::where('id', $orderId)->first();
        $order->status = 'order_placed';
        $order->save();
        // For example, send a notification to the user
        return response()->json([
            'success' => true,
            'message' => 'Payment successful',
            'order_id' => $orderId,
            'status' => 'order_placed'
        ]);
    }

    /**
     * Handle cancelled crypto payment
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function cryptoPaymentCancel(Request $request)
    {
        $orderId = $request->query('order_id');
        
        // Here you can add any additional logic for cancelled payments
        // For example, update the order status to cancelled
        
        return response()->json([
            'success' => false,
            'message' => 'Payment was cancelled',
            'order_id' => $orderId,
            'status' => 'cancelled'
        ], 400);
    }

    /**
     * Handle NOWPayments webhook
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleCryptoWebhook(Request $request)
    {
        Log::info('NOWPayments Webhook Received:', $request->all());

        try {
            $data = $request->all();

            dd(config('services.nowpayments.api_key'));
            
            // Verify IPN secret if in production
            if (!config('services.nowpayments.test_mode')) {
                $receivedSecret = $request->query('secret');
                $expectedSecret = config('services.nowpayments.ipn_secret');
                
                if ($receivedSecret !== $expectedSecret) {
                    Log::error('Invalid IPN secret', [
                        'received_secret' => $receivedSecret,
                        'expected_secret' => $expectedSecret
                    ]);
                    return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 401);
                }
                
                // Also verify the signature if available
                $signature = $request->header('x-nowpayments-sig');
                if ($signature) {
                    $payload = $request->getContent();
                    $expectedSignature = hash_hmac('sha512', $payload, $expectedSecret);
                    
                    if (!hash_equals($expectedSignature, $signature)) {
                        Log::error('Invalid webhook signature', [
                            'expected' => $expectedSignature,
                            'received' => $signature,
                            'payload' => $payload
                        ]);
                        return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
                    }
                }
            }
            
            // Verify the payment exists
            $payment = Payment::where('transaction_id', $data['payment_id'])->firstOrFail();

            // Update payment status
            $updateData = [
                'payment_status' => $data['payment_status'],
                'metadata' => array_merge($payment->metadata ?? [], [
                    'webhook_data' => $data,
                    'updated_at' => now()->toDateTimeString()
                ])
            ];

            // If payment is completed, update order status
            if ($data['payment_status'] === 'finished') {
                $payment->order->update(['status' => 'confirmed']);
                $updateData['paid_at'] = now();
                $updateData['payment_status'] = 'completed';
            }

            $payment->update($updateData);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook processing error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function createPaymentIntent(Request $request)
    {
        try {
            // Get authenticated user
            $user = Auth::user();
            
            // Validate request
            $request->validate([
                'shopper_id' => 'required|integer',
            ]);

            // Get user's cart items
            $cartItems = Cart::where('user_id', $user->id)
                ->with('product')
                ->get();
            //dd($cartItems);

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
                    continue;
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
            $orderData = [
                'user_id' => $user->id,
                'order_number' => $orderNumber,
                'delivery_date' => $request->delivery_date,
                'delivery_time' => $request->delivery_time,
                'shopper_id' => (int) $request->shopper_id,
                'tax' => $tax,
                'delivery_charges' => $deliveryCharges,
                'total' => $total,
                'status' =>'order_placed',
            ];
            
            Log::info('Creating order with data:', $orderData);
            
            $order = Order::create($orderData);

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
                    'product_notes' => null,
                    'storeName' => $item->product->storeName ?? null,
                    'productId' => $item->product->productId ?? null,
                    'images' => $item->product->images ?? null
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

            // // Create notifications to specific user IDs (e.g., admins)
            // foreach ([1, 30] as $notifyUserId) {
            //     Notification::create([
            //         'user_id' => $notifyUserId,
            //         'title' => 'New order created',
            //         'message' => 'Order ' . $orderNumber . ' has been created. Total $' . number_format($total, 2),
            //         'image' => null,
            //         'type' => 'normal notification',
            //         'order_id' => $order->id,
            //     ]);
            // }

            // Notification::create([
            //     'user_id' => $,
            //     'title' => 'New order created',
            //     'message' => 'Order ' . $orderNumber . ' has been created. Total $' . number_format($total, 2),
            //     'image' => null,
            //     'type' => 'normal notification',
            //     'order_id' => $order->id,
            // ]);

            

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
            Log::error('Request data: ' . json_encode($request->all()));
            Log::error('User ID: ' . $user->id);
            Log::error('Shopper ID: ' . $request->shopper_id . ' (type: ' . gettype($request->shopper_id) . ')');
            Log::error('Exception class: ' . get_class($e));
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payment intent. Please ensure shopper_id is provided.',
                'debug' => config('app.debug') ? $e->getMessage() : null,
                'required_fields' => ['shopper_id']
            ], 500);
        }
    }

    public function reorder(Request $request, $order_id)
    {
        DB::beginTransaction();
        try {
            $user = Auth::user();
            
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
    
            $results = [
                'added' => 0,
                'not_found' => [],
                'errors' => []
            ];
    
            
            foreach ($originalOrder->orderItems as $orderItem) {
                try {
                    if (!$orderItem->product) {
                        $results['not_found'][] = 'Product not found';
                        continue;
                    }
                    $product = Product::where('name', $orderItem->product->name)
                        ->first();
    
                    if ($product) {
                        $existingCart = Cart::where('user_id', $user->id)
                            ->where('product_id', $product->id)
                            ->first();
    
                        if ($existingCart) {
                            $existingCart->quantity += $orderItem->quantity;
                            $existingCart->save();
                        } else {
                            Cart::create([
                                'user_id' => $user->id,
                                'product_id' => $product->id,
                                'quantity' => $orderItem->quantity,
                            ]);
                        }
                        $results['added']++;
                    } else {
                        $results['not_found'][] = $orderItem->product->name;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Error processing product: " . ($orderItem->product->name ?? 'Unknown');
                }
            }

            if ($results['added'] === 0 && !empty($results['not_found'])) {
                DB::rollback();
                return response()->json([
                    'success' => false,
                    'message' => 'None of the products from your order are currently available',
                    'details' => $results
                ], 400);
            }

            $response = [
                'success' => true,
                'message' => 'Items added to cart successfully',
                'data' => $results
            ];
            if (!empty($results['not_found'])) {
                $response['message'] = 'Some items were not found';
                $response['warning'] = 'Some products from your order are no longer available';
            }
    
            DB::commit();
            return response()->json($response);
    
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Reorder error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process reorder',
                'error' => config('app.debug') ? $e->getMessage() : null
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
            //dd($payment);
            
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
                'status' => 'order_placed'
            ]);

            // Clear user's cart after successful payment
            Cart::where('user_id', Auth::id())->delete();

            // Create notifications to specific user IDs (e.g., admins)
            foreach ([1, 30] as $notifyUserId) {
                Notification::create([
                    'user_id' => $notifyUserId,
                    'title' => 'New order created',
                    'message' => 'Order ' . $payment->order->order_number . ' has been created. Total $' . number_format($payment->order->total, 2),
                    'image' => null,
                    'type' => 'normal notification',
                    'order_id' => $payment->order->id,
                ]);
            }

            Notification::create([
                'user_id' => $payment->order->shopper_id,
                'title' => 'New order created',
                'message' => 'Order ' . $payment->order->order_number . ' has been created. Total $' . number_format($payment->order->total, 2),
                'image' => null,
                'type' => 'normal notification',
                'order_id' => $payment->order->id,
            ]);


            // Note: shopper_id is not a user_id, so we can't create notifications for shoppers
            // If you need to notify shoppers, you'll need a different mechanism
            // For now, we'll skip shopper notifications to avoid foreign key errors


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

    public function confirmCryptoPayment(Request $request)
    {
        try {
            $request->validate([
                'payment_id' => 'required|integer|exists:payments,id'
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

            // Check if crypto payment is already completed
            if ($payment->payment_status !== 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Crypto payment is not completed yet. Please wait for payment confirmation.',
                    'payment_status' => $payment->payment_status
                ], 400);
            }

            DB::beginTransaction();

            // Update order status to order_placed after payment confirmation
            $payment->order->update([
                'status' => 'order_placed'
            ]);

            // Clear user's cart after successful payment (same as Stripe)
            Cart::where('user_id', Auth::id())->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Crypto payment confirmed successfully',
                'data' => [
                    'order_id' => $payment->order->id,
                    'order_number' => $payment->order->order_number,
                    'payment_status' => $payment->payment_status,
                    'order_status' => $payment->order->status
                ]
            ]);

        } catch (Exception $e) {
            DB::rollback();
            Log::error('Crypto payment confirmation error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to confirm crypto payment'
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
            $user = Auth::user();
            $transactions = Payment::with(['order' => function($query) {
                $query->select('id', 'order_number', 'status', 'total', 'created_at', 'user_id');
            }])
            ->whereHas('order', function($query) {
                $query->where('user_id', Auth::id());
            })
            ->where('payment_status', 'completed')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($payment) use ($user) {
                return [
                    'id' => $payment->id,
                    'order_number' => $payment->order->order_number,
                    'total_amount' => (float)$payment->amount,
                    'user_photo' => $user->photo ? asset($user->photo) : null,
                    'date' => $payment->created_at->format('Y-m-d'),
                    'time' => $payment->created_at->format('H:i:s'),
                    'created_at' => $payment->created_at->toIso8601String(),
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'order_status' => $payment->order->status
                ];
            });

            if ($transactions->isEmpty()) {
                return response()->json([
                    'status' => true,
                    'message' => 'No transactions found',
                    'data' => []
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions
            ]);

        } catch (Exception $e) {
            Log::error('Get all transactions error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve transactions. Please try again later.'
            ], 500);
        }
    }
    
}
