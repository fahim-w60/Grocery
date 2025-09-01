<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class AddToCartController extends Controller
{
    public function addToCart(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = Auth::user();

        $cart = Cart::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($cart) {
            $cart->quantity += $validated['quantity'];
            $cart->save();
        } else {
            $cart = Cart::create([
                'user_id' => $user->id,
                'product_id' => $validated['product_id'],
                'quantity' => $validated['quantity'],
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Product added to cart',
            'cart' => $cart,
        ]);
    }

    public function getCart(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $cartItems = Cart::with('product')->where('user_id', $user->id)->get();
    
        $formatted = $cartItems->map(function ($item) {
            $product = $item->product;
            
            // Skip items where product doesn't exist
            if (!$product) {
                return null;
            }
    
            $promoPrice = !empty($product->promo_price) && $product->promo_price != '0'
                ? (float)$product->promo_price
                : (float)$product->regular_price;
    
            return [
                'id'            => $item->id,
                'product_id'    => $product->id,
                'product_name'  => $product->name,
                'image'         => $product->images,
                'regular_price' => $product->regular_price,
                'promo_price'   => $product->promo_price,
                'brand'         => $product->brand,
                'categories'    => is_string($product->categories) ? json_decode($product->categories) : $product->categories,
                'term'          => $product->term,
                'size'          => $product->size,
                'soldBy'        => $product->soldBy,
                'locationId'    => $product->locationId,
                'storeName'     => $product->storeName,
                'stockLevel'    => $product->stockLevel,
                'countryOrigin' => $product->countryOrigin,
                'quantity'      => $item->quantity,
                'item_total'    => round($promoPrice * $item->quantity, 2),
                'created_at'    => $item->created_at,
                'updated_at'    => $item->updated_at,
            ];
        })->filter(); 
    
        $totalProducts = $formatted->sum('quantity');
        $totalPrice = $formatted->sum('item_total');
        
        // Calculate delivery charges and tax
        $deliveryCharges = $totalPrice > 0 ? 5.00 : 0.00; // $5 delivery charge if cart has items
        $taxRate = 0.08; // 8% tax rate
        $tax = round($totalPrice * $taxRate, 2);
        $grandTotal = round($totalPrice + $deliveryCharges + $tax, 2);
    
        return response()->json([
            'status'         => true,
            'cart'           => $formatted->values(), // Reset array keys after filtering
            'total_products' => $totalProducts,
            'total_price'    => round($totalPrice, 2),
            'delivery_charges' => $deliveryCharges,
            'tax'            => $tax,
            'grand_total'    => $grandTotal,
        ]);
    }

    public function getCartById($id)
    {
        $user = Auth::user();
        $cartItem = Cart::with('product')->where('user_id', $user->id)->where('id', $id)->first();

        if (!$cartItem) {
            return response()->json([
                'status' => false,
                'message' => 'Cart item not found',
            ], 404);
        }

        $product = $cartItem->product;
        $formatted = [
            'id' => $cartItem->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'image' => $product->images,
            'regular_price' => $product->regular_price,
            'promo_price' => $product->promo_price,
            'brand' => $product->brand,
            'categories' => is_string($product->categories) ? json_decode($product->categories) : $product->categories,
            'term' => $product->term,
            'size' => $product->size,
            'soldBy' => $product->soldBy,
            'locationId' => $product->locationId,
            'storeName' => $product->storeName,
            'stockLevel' => $product->stockLevel,
            'countryOrigin' => $product->countryOrigin,
            'quantity' => $cartItem->quantity,
            'created_at' => $cartItem->created_at,
            'updated_at' => $cartItem->updated_at,
        ];

        return response()->json([
            'status' => true,
            'cart' => $formatted,
        ]);
    }

    public function removeCart($id)
    {
        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->where('id', $id)->first();
        if (!$cart) {
            return response()->json([
                'status' => false,
                'message' => 'Cart item not found',
            ], 404);
        }
        $cart->delete();
        return response()->json([
            'status' => true,
            'message' => 'Cart item removed successfully',
        ]);
    }

    public function updateCart($id, Request $request)
    {
        $validated = $request->validate([
            'quantity' => 'required|integer|min:1',
        ]);
        $user = Auth::user();
        $cart = Cart::where('user_id', $user->id)->where('id', $id)->first();
        if (!$cart) {
            return response()->json([
                'status' => false,
                'message' => 'Cart item not found',
            ], 404);
        }
        $cart->quantity = $validated['quantity'];
        $cart->save();
        return response()->json([
            'status' => true,
            'message' => 'Cart item updated successfully',
        ]);
    }

    public function deleteAllCart()
    {
        $user = Auth::user();
        $today = now()->format('Y-m-d');
        
        $deleted = Cart::where('user_id', $user->id)
            ->whereDate('created_at', '!=', $today)
            ->delete();
            
        return response()->json([
            'status' => true,
            'message' => 'Successfully removed '.$deleted.' old cart item(s) not from today',
            'items_removed' => $deleted
        ]);
    }
}
