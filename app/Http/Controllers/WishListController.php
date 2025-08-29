<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;

class WishListController extends Controller
{
    public function addToWishlist(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
        ]);

        $user = Auth::user();

        $wishlist = Wishlist::where('user_id', $user->id)
            ->where('product_id', $validated['product_id'])
            ->first();

        if ($wishlist) {
            return response()->json([
                'status' => false,
                'message' => 'Product already in wishlist',
            ], 409);
        }

        $wishlist = Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $validated['product_id'],
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Product added to wishlist',
            'wishlist' => $wishlist,
        ]);
    }

    public function getWishlist()
    {
        $user = Auth::user();
        $wishlistItems = Wishlist::with('product')
            ->where('user_id', $user->id)
            ->get()
            ->filter(function($item) {
                return $item->product !== null;
            });

        $formatted = $wishlistItems->map(function($item) {
            $product = $item->product;
            return [
                'id' => $item->id,
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
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ];
        })->values();

        return response()->json([
            'status' => true,
            'wishlist' => $formatted,
            'total_items' => $formatted->count()
        ]);
    }

    public function getWishlistById($id)
    {
        $user = Auth::user();
        $wishlistItem = Wishlist::with('product')->where('user_id', $user->id)->where('id', $id)->first();

        if (!$wishlistItem) {
            return response()->json([
                'status' => false,
                'message' => 'Wishlist item not found',
            ], 404);
        }

        $product = $wishlistItem->product;
        $formatted = [
            'id' => $wishlistItem->id,
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
            'created_at' => $wishlistItem->created_at,
            'updated_at' => $wishlistItem->updated_at,
        ];

        return response()->json([
            'status' => true,
            'wishlist' => $formatted,
        ]);
    }

    public function removeWishlist($id)
    {
        $user = Auth::user();
        $wishlist = Wishlist::where('user_id', $user->id)->where('id', $id)->first();
        if (!$wishlist) {
            return response()->json([
                'status' => false,
                'message' => 'Wishlist item not found',
            ], 404);
        }
        $wishlist->delete();
        return response()->json([
            'status' => true,
            'message' => 'Wishlist item removed successfully',
        ]);
    }

    public function deleteAllWishlist()
    {
        $user = Auth::user();
        $today = now()->format('Y-m-d');
        
        $deleted = Wishlist::where('user_id', $user->id)
            ->whereDate('created_at', '!=', $today)
            ->delete();
            
        return response()->json([
            'status' => true,
            'message' => 'Successfully removed '.$deleted.' old wishlist item(s) not from today',
            'items_removed' => $deleted
        ]);
    }
}
