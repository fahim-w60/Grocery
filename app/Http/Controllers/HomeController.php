<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use App\Models\Product;
use App\Models\User;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;


class HomeController extends Controller
{
    public function getAllHomeBanners()
    {
        $banners = Banner::select('id','banner_image')->orderBy('id', 'desc')->get();
        foreach ($banners as $banner) {
            $banner->banner_image = $banner->banner_image ? asset($banner->banner_image) : null;
        }
        return response()->json([
            'status' => true,
            'message' => 'All banners fetched successfully',
            'banners' => $banners,
        ]);
    }

    public function searchForPriceComparison(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'required|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
    
        $search = $request->input('search');
        $perPage = $request->input('per_page', 20);
    
        $products = Product::select('id','name','images','regular_price','promo_price','storeName')
            ->whereRaw("MATCH(name) AGAINST (? IN BOOLEAN MODE)", [$search])
            ->paginate($perPage);
    
        return response()->json([
            'status' => true,
            'message' => 'Products fetched successfully',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    public function addFaceId(Request $request)
    {
        $validatedData = $request->validate([
            'biometric' => 'required|string|max:255',
        ]);
    
        $user = auth()->user();
        $user->biometric = $validatedData['biometric'];
        $user->save();
    
        return response()->json([
            'status' => true,
            'message' => 'Face ID added successfully',
            'user' => $user,
        ]);
    }

    public function getFaceId()
    {
        $user = auth()->user();
        return response()->json([
            'status' => true,
            'message' => 'Face ID fetched successfully',
            'user' => $user,
        ]);
    }

    public function addFingerId(Request $request)
    {
        $validatedData = $request->validate([
            'biometric' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $user->biometric = $validatedData['biometric'];
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Finger ID added successfully',
            'user' => $user,
        ]);
    }

    public function getFingerId()
    {
        $user = auth()->user();
        return response()->json([
            'status' => true,
            'message' => 'Finger ID fetched successfully',
            'user' => $user,
        ]);
    }

    public function SearchProductWithFilter(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'required|string|max:255',
            'storeName' => 'sometimes|string|max:255',
            'categories' => 'sometimes|string|max:255',
            'price' => 'sometimes|numeric',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
    
        $search = $request->input('search');
        $perPage = $request->input('per_page', 20);
        $storeName = $request->input('storeName');
        $categories = $request->input('categories');
        $price = $request->input('price');
    
        $query = Product::select('*')
            ->whereRaw("MATCH(name) AGAINST (? IN BOOLEAN MODE)", [$search]);
    
        if (!empty($storeName)) {
            $storeNames = array_map('trim', explode(',', $storeName));
            $query->where(function($q) use ($storeNames) {
                foreach ($storeNames as $store) {
                    $q->orWhere('storeName', 'like', "%$store%");
                }
            });
        }
    
        if (!empty($categories)) {
            $categoryList = array_map('trim', explode(',', $categories));
            $query->where(function($q) use ($categoryList) {
                foreach ($categoryList as $category) {
                    $q->orWhere('categories', 'like', "%\"$category\"%")
                      ->orWhere('categories', 'like', "%$category%");
                }
            });
        }
    
        if (!empty($price)) {
            $query->where('regular_price', '>=', $price);
        }
    
        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);
    
        return response()->json([
            'status' => true,
            'message' => 'Products fetched successfully',
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    public function fetchKrogerCategories(Request $request)
    {
        $allCategories = Product::query()
            ->whereNotNull('categories')
            ->where('categories', '!=', '')
            ->pluck('categories')
            ->flatMap(function ($categories) {
                return json_decode($categories, true) ?: [];
            })
            ->unique()
            ->values()
            ->toArray();
    
        return response()->json([
            'status' => true,
            'message' => 'Categories fetched successfully',
            'categories' => $allCategories
        ]);
    }

    public function fetchKrogerProductByCategory(Request $request, $category)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $products = Product::select('*')
        ->where('categories', 'like', "%$category%")
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    
    public function searchKrogerProducts(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'required|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);
    
        $search = $request->input('search');
        $perPage = $request->input('per_page', 20);
    
        $products = Product::select([
            'id', 'name', 'images', 'regular_price', 'promo_price', 
            'brand', 'categories', 'storeName', 'stockLevel'
        ])
        ->whereRaw("MATCH(name) AGAINST (? IN BOOLEAN MODE)", [$search])
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);
    
        return response()->json([
            'status' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    
    public function fetchKrogerStores(Request $request)
    {
            $allStores = Product::query()
            ->whereNotNull('storeName')
            ->where('storeName', '!=', '')
            ->pluck('storeName')
            ->unique()
            ->values()
            ->toArray();

        return response()->json([
            'status' => true,
            'message' => 'Stores fetched successfully',
            'stores' => $allStores
        ]);
    }


    public function searchProductByStore(Request $request, $store)
    {
        $validatedData = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);

        $products = Product::select('*')
        ->where('storeName', 'like', "%$store%")
        ->orderBy('created_at', 'desc')
        ->paginate($perPage);

        return response()->json([
            'status' => true,
            'data' => $products->items(),
            'pagination' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ]
        ]);
    }

    public function krogerProductById(Request $request, $id)
    {
        $product = Product::findOrFail($id);
        return response()->json([
            'status' => true,
            'message' => 'Product fetched successfully',
            'product' => $product,
        ]);
    }

    public function addCard(Request $request)
    {
        $request->validate([
            'card_holder_name' => 'required|string',
            'card_number' => 'required|string',
            'expiration_date' => 'required|string',
            'cvv' => 'required|string',
        ]);

        $user = auth()->user();
        $card = new Card();
        $card->card_holder_name = $request->card_holder_name;
        $card->card_number = $request->card_number;
        $card->expiration_date = $request->expiration_date;
        $card->cvv = $request->cvv;
        $card->user_id = $user ? $user->id : null;
        $card->save();

        return response()->json(['status' => true, 'message' => 'Card added successfully', 'card' => $card]);
    }

    public function getCards()
    {
        $user = auth()->user();
        $cards = \App\Models\Card::where('user_id', $user->id)->get();
        return response()->json(['status' => true, 'cards' => $cards]);
    }

    public function removeCard($id)
    {
        $user = auth()->user();
        $card = \App\Models\Card::where('id', $id)->where('user_id', $user->id)->first();
        if (!$card) {
            return response()->json(['status' => false, 'message' => 'Card not found'], 404);
        }
        $card->delete();
        return response()->json(['status' => true, 'message' => 'Card removed successfully']);
    }

    public function getProfile(Request $request)
    {
        $user = auth()->user();
        return response()->json([
            'status' => true,
            'data' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'address' => $user->address,
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'address' => 'required|string|max:255',
        ]);
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->address = $request->address;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'address' => $user->address,
            ]
        ]);
    }


    public function getAllShopper(Request $request)
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $perPage = $request->input('per_page', 10); 
        $page = $request->input('page', 1); 

        $query = User::where('role', 'shopper');

        $shoppers = $query->select('id', 'name', 'email', 'role', 'total_delivery', 'phone', 'address', 'created_at')
                         ->orderBy('created_at', 'desc')
                         ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'status' => true,
            'message' => 'Shoppers retrieved successfully',
            'data' => $shoppers->items(),
            'pagination' => [
                'total' => $shoppers->total(),
                'per_page' => $shoppers->perPage(),
                'current_page' => $shoppers->currentPage(),
            ]
        ]);
    }

    public function personalShopper(Request $request)
    {
        $user = auth()->user();
        $shopper = User::with('personalShopper')->find($user->id);
        return response()->json([
            'status' => true,
            'message' => 'Personal shopper retrieved successfully',
            'shopper' => $shopper->personalShopper,
        ]);
    }

    public function makeShopper(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::where('id', $validated['user_id'])->first();

        if($user->role != 'shopper') {
            return response()->json([
                'status' => false,
                'message' => 'This user is not a shopper',
            ], 400);
        }
         
        $authUser = User::with('personalShopper')->find(auth()->id());

        if($authUser->shopper_id == $validated['user_id']) {
            return response()->json([
                'status' => false,
                'message' => 'This user is already a shopper',
            ], 400);
        }
        else{
            $authUser->shopper_id = $validated['user_id'];
            $authUser->save();
            return response()->json([
                'status' => true,
                'message' => 'User assigned as a shopper successfully',
                'data' => [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ]
            ]);
        }
        
    }


    public function removeShopper(Request $request)
    {
        $authUser = auth()->user();
        
        // $shopper = User::where('shopper_id', $authUser->shopper_id)->first();

        if($authUser->shopper_id == null) {
            return response()->json([
                'status' => false,
                'message' => 'This user doesnt have a shopper',
            ], 400);
        }
        else{
            $authUser->shopper_id = null;
            $authUser->save();
            return response()->json([
                'status' => true,
                'message' => 'Shopper removed successfully',
            ]);
        }
    }

    public function getAllOrders(Request $request)
    {
        $user = auth()->user();
       // $orders = Order::where('user_id', $user->id)->get();
        return response()->json([
            'status' => true,
            'message' => 'Orders retrieved successfully',
            'orders' => 'No orders found',
        ]);
    }

    public function getAllNotifications(Request $request)
    {
        $user = auth()->user();
        //$notifications = Notification::where('user_id', $user->id)->get();
        return response()->json([
            'status' => true,
            'message' => 'Notifications retrieved successfully',
            'notifications' => 'No notifications found',
        ]);
    }

    public function getAllTransactions(Request $request)
    {
        $user = auth()->user();
        //$transactions = Transaction::where('user_id', $user->id)->get();
        return response()->json([
            'status' => true,
            'message' => 'Transactions retrieved successfully',
            'transactions' => 'No transactions found',
        ]);
    }

    public function aboutApp(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'About app retrieved successfully',
            'about_app' => 'No about app found',
        ]);
    }

    public function allFaq(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Faq retrieved successfully',
            'faq' => 'No faq found',
        ]);
    }

    public function updateAdminProfile(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|max:255',
        ]);
        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);
    }
}
