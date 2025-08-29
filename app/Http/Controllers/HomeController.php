<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Http\Request;
use App\Models\Banner;
use App\Models\User;
use App\Models\Card;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\UserLocation;
use App\Models\Product;

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
    
        $products = Product::select('id','name','images','regular_price','promo_price','storeName','categories')
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

    public function getFaceId(Request $request)
    {
        $validatedData = $request->validate([
            'biometric' => 'required|string|max:255',
        ]);


        $user = auth()->user();
        if($user->biometric !== $validatedData['biometric']) {
            return response()->json([
                'status' => false,
                'message' => 'Biometric data does not match',
            ], 400);
        }


        $token = JWTAuth::fromUser($user);

        // Create response data
        $response = [
            'status' => true,
            'message' => 'Face ID fetched successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'google_id' => $user->google_id,
                'biometric' => $user->biometric,
                'photo' => $user->photo ? asset($user->photo) : null,
                'address' => $user->address,
                'shopper_id' => $user->shopper_id,
                'total_delivery' => $user->total_delivery,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]
        ];
        
        return response()->json($response);
    }

    public function addFingerId(Request $request)
    {
        $validatedData = $request->validate([
            'fingerId' => 'required|string|max:255',
        ]);

        $user = auth()->user();
        $user->fingerId = $validatedData['fingerId'];
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Finger ID added successfully',
            'user' => $user,
        ]);
    }

    public function getFingerId(Request $request)
    {

        $validatedData = $request->validate([
            'fingerId' => 'required|string|max:255',
        ]);

        $user = auth()->user();

        if($user->fingerId !== $validatedData['fingerId']) {
            return response()->json([
                'status' => false,
                'message' => 'Finger ID does not match',
            ], 400);
        }
        $token = JWTAuth::fromUser($user);
        
        $response = [
            'status' => true,
            'message' => 'Finger ID fetched successfully',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'email_verified_at' => $user->email_verified_at,
                'google_id' => $user->google_id,
                'biometric' => $user->biometric,
                'photo' => $user->photo ? asset($user->photo) : null,
                'address' => $user->address,
                'shopper_id' => $user->shopper_id,
                'total_delivery' => $user->total_delivery,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at
            ]
        ];
        
        return response()->json($response);
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
            'stores' => $allStores,
            'totalstore' => count($allStores),
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
                'photo' => $user->photo ? asset($user->photo) : null,
                'biometric' => $user->biometric,
                'role' => $user->role,
                'total_delivery' => $user->total_delivery,
            ]
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user();
        
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => [
                'sometimes',
                'string',
                'max:20',
            ],
            'address' => 'sometimes|string|max:255',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
    
        $user->name = $request->name;
        $user->phone = $request->phone;
        $user->address = $request->address;
    
        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo && file_exists(public_path($user->photo))) {
                unlink(public_path($user->photo));
            }
            
            $image = $request->file('photo');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('uploads/profiles');
            
            // Create directory if it doesn't exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            
            $image->move($destinationPath, $imageName);
            $user->photo = 'uploads/profiles/' . $imageName;
        }
    
        $user->save();
    
        return response()->json([
            'status' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'name' => $user->name,
                'phone' => $user->phone,
                'address' => $user->address,
                'photo' => $user->photo ? asset($user->photo) : null,
            ]
        ]);
    }

    public function updateAdminPhoto(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo && file_exists(public_path($user->photo))) {
                unlink(public_path($user->photo));
            }
            
            $image = $request->file('photo');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('uploads/profiles');
            
            // Create directory if it doesn't exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            
            $image->move($destinationPath, $imageName);
            $user->photo = 'uploads/profiles/' . $imageName;
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Photo updated successfully',
            'data' => [
                'photo' => $user->photo ? asset($user->photo) : null,
            ]
        ]);
    }

    public function getAllShopper(Request $request)
    {
        $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);
    
        $user = auth()->user();
        $userLocation = $user->userlocations;
    
        if (!$userLocation) {
            return response()->json([
                'status' => false,
                'message' => 'User location not found',
            ], 404);
        }
    
        $perPage = $request->input('per_page', 10); 
        $page = $request->input('page', 1); 
        $radius = 10; // 10 km radius
    
            $shoppers = User::with(['userlocations' => function($query) use ($userLocation, $radius) {
                $query->select('user_id', 'latitude', 'longitude')
                    ->selectRaw("(6371 * acos(cos(radians(?)) 
                                * cos(radians(latitude)) 
                                * cos(radians(longitude) - radians(?)) 
                                + sin(radians(?)) 
                                * sin(radians(latitude)))) AS distance", 
                                [$userLocation->latitude, $userLocation->longitude, $userLocation->latitude])
                    ->whereNull('deleted_at')
                    ->having('distance', '<=', $radius)
                    ->orderBy('distance');
            }])
            ->where('role', 'shopper')
            ->whereHas('userlocations', function($query) use ($userLocation, $radius) {
                $query->selectRaw("1")
                    ->selectRaw("(6371 * acos(cos(radians(?)) 
                                * cos(radians(latitude)) 
                                * cos(radians(longitude) - radians(?)) 
                                + sin(radians(?)) 
                                * sin(radians(latitude)))) AS distance", 
                                [$userLocation->latitude, $userLocation->longitude, $userLocation->latitude])
                    ->whereNull('deleted_at')
                    ->having('distance', '<=', $radius);
            })
            ->paginate($perPage, ['*'], 'page', $page);
        
        // Note: top-level distance removed. Read distance from `userlocations.distance` on the client.
        
        return response()->json([
            'status' => true,
            'message' => 'Shoppers retrieved successfully',
            'user_location' => [
                'latitude' => $userLocation->latitude,
                'longitude' => $userLocation->longitude
            ],
            'data' => $shoppers->items(),
            'pagination' => [
                'total' => $shoppers->total(),
                'per_page' => $shoppers->perPage(),
                'current_page' => $shoppers->currentPage(),
                'last_page' => $shoppers->lastPage(),
            ]
        ]);
    }

    public function personalShopper(Request $request)
    {
        $user = auth()->user();

        $userLocation = $user->userlocations;
        if (!$userLocation || $userLocation->latitude === null || $userLocation->longitude === null) {
            return response()->json([
                'status' => false,
                'message' => 'User location not set',
                'shopper' => null,
            ], 400);
        }

        $latitude = (float) $userLocation->latitude;
        $longitude = (float) $userLocation->longitude;
        $radiusKm = 10; // 10 km radius

        $shoppers = User::query()
            ->where('role', 'shopper')
            ->join('userlocations', 'users.id', '=', 'userlocations.user_id')
            ->whereNull('userlocations.deleted_at')
            ->select(
                'users.id',
                'users.name',
                'users.photo',
                'userlocations.latitude',
                'userlocations.longitude',
                \DB::raw("(
                    6371 * acos(
                        cos(radians($latitude)) * cos(radians(userlocations.latitude)) *
                        cos(radians(userlocations.longitude) - radians($longitude)) +
                        sin(radians($latitude)) * sin(radians(userlocations.latitude))
                    )
                ) as distance_km")
            )
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km', 'asc')
            ->get();
        
        return response()->json([
            'status' => true,
            'message' => 'Personal shopper retrieved successfully',
            'shopper' => $shoppers,
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
            'email' => 'sometimes|string|max:255',
        ]);
        $user->name = $request->name;
        if ($request->has('email') && $request->email !== null) {
            $user->email = $request->email;
        }
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

    public function setUserLocation(Request $request)
    {
        $user = auth()->user();
        $request->validate([
            'latitude' => 'required|string',
            'longitude' => 'required|string',
        ]);
        $userLocation = UserLocation::where('user_id', $user->id)->first();
        if($userLocation) {
            $userLocation->latitude = $request->latitude;
            $userLocation->longitude = $request->longitude;
            $userLocation->save();
        }
        else {
            $userLocation = new UserLocation();
            $userLocation->user_id = $user->id;
            $userLocation->latitude = $request->latitude;
            $userLocation->longitude = $request->longitude;
            $userLocation->save();
        }

        return response()->json([
            'status' => true,
            'message' => 'Location added successfully',
            'data' => [
                'user_id' => $user->id,
                'latitude' => $userLocation->latitude,
                'longitude' => $userLocation->longitude,
            ]
        ]);
    }

    public function getUserLocation(Request $request)
    {
        $user = auth()->user();
        $userLocation = UserLocation::where('user_id', $user->id)->first();
        return response()->json([
            'status' => true,
            'message' => 'Location retrieved successfully',
            'data' => [
                'user_id' => $user->id,
                'latitude' => $userLocation->latitude,
                'longitude' => $userLocation->longitude,
            ]
        ]);
    }

    public function recommendationProduct(Request $request)
    {
        // Get 10 random active products
        $products = Product::inRandomOrder()
            ->take(10)
            ->get()
            ->map(function ($product) {
                // Parse the images string into an array
                $images = $product->images ? json_decode($product->images, true) : [];
                $images = is_array($images) ? $images : [];
                
                // Format the product data
                return [
                    'id' => $product->id,
                    'productId' => $product->productId,
                    'name' => $product->name,
                    'images' => $product->images,
                    'regular_price' => $product->regular_price,
                    'promo_price' => $product->promo_price,
                    'brand' => $product->brand,
                    'categories' => $product->categories,
                    'size' => $product->size,
                    'sold_by' => $product->soldBy,
                    'store_name' => $product->storeName,
                    'stock_level' => $product->stockLevel,
                    'country_origin' => $product->countryOrigin,
                    'created_at' => $product->created_at,
                    'updated_at' => $product->updated_at
                ];
            });

        return response()->json([
            'status' => true,
            'message' => 'Recommended products retrieved successfully',
            'data' => $products
        ]);
    }

    public function totalUser(Request $request)
    {
        $totalUser = User::where('role', 'user')->count();
        return response()->json([
            'status' => true,
            'message' => 'Total user retrieved successfully',
            'data' => $totalUser
        ]);
    }

    public function shopperDetails(string $id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Check if user is actually a shopper
            if ($user->role !== 'shopper') {
                return response()->json([
                    'status' => false,
                    'message' => 'User is not a shopper'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Shopper details retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'photo' => $user->photo ? asset($user->photo) : asset('uploads/profiles/no_image.jpeg'),
                    'address' => $user->address,
                    'total_delivery' => $user->total_delivery,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Shopper not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve shopper details'
            ], 500);
        }
    }

    // public function userDetails(string $id)
    // {
    //      try {
    //         $user = User::findOrFail($id);
            
    //         // Check if user is actually a shopper
    //         if ($user->role !== 'shopper') {
    //             return response()->json([
    //                 'status' => false,
    //                 'message' => 'User is not a shopper'
    //             ], 404);
    //         }

    //         return response()->json([
    //             'status' => true,
    //             'message' => 'User details retrieved successfully',
    //             'data' => [
    //                 'id' => $user->id,
    //                 'name' => $user->name,
    //                 'email' => $user->email,
    //                 'phone' => $user->phone,
    //                 'role' => $user->role,
    //                 'photo' => $user->photo ? asset($user->photo) : asset('uploads/profiles/no_image.jpeg'),
    //                 'address' => $user->address,
    //                 'total_delivery' => $user->total_delivery,
    //                 'created_at' => $user->created_at,
    //                 'updated_at' => $user->updated_at
    //             ]
    //         ]);

    //     } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Shopper not found'
    //         ], 404);
    //     } catch (Exception $e) {
    //         return response()->json([
    //             'status' => false,
    //             'message' => 'Failed to retrieve shopper details'
    //         ], 500);
    //     }
    // }

    public function dashboard(Request $request)
    {
        $validatedData = $request->validate([
            'filter' => 'sometimes|string|in:yearly,monthly,weekly,daily',
        ]);

        $filter = $request->input('filter', 'weekly');
        
        $totalEarnings = Payment::where('payment_status', 'completed')
            ->selectRaw('SUM(amount - IFNULL(shopper_amount, 0)) as net_earnings')
            ->value('net_earnings') ?? 0;
        $totalUser = User::where('role', 'user')->count();
        $allStores = Product::query()
            ->whereNotNull('storeName')
            ->where('storeName', '!=', '')
            ->pluck('storeName')
            ->unique()
            ->values()
            ->toArray();

        // Get chart data based on filter
        $chartData = $this->getChartData($filter);
        
        // Transform chart_data to array of { day, total }
        $labels = $chartData['labels'] ?? [];
        $values = $chartData['data'] ?? [];
        $chartPoints = [];
        foreach ($labels as $index => $label) {
            $chartPoints[] = [
                'day' => is_numeric($label) ? (int) $label : (string) $label,
                'total' => isset($values[$index]) ? (float) $values[$index] : 0.0,
            ];
        }
        
        return response()->json([
            'status' => true,
            'message' => 'Data retreived successfully',
            'data' => [
                'total_earnings' => $totalEarnings,
                'total_user' => $totalUser,
                'all_stores' => count($allStores),
                'chart_data' => $chartPoints,
            ]
        ]);
    }

    private function getChartData($filter)
    {
        $now = now();
        
        switch ($filter) {
            case 'yearly':
                return $this->getYearlyData($now);
            case 'monthly':
                return $this->getMonthlyData($now);
            case 'daily':
                return $this->getDailyData($now);
            case 'weekly':
            default:
                return $this->getWeeklyData($now);
        }
    }

    private function getYearlyData($now)
    {
        $data = [];
        $labels = [];
        
        for ($i = 4; $i >= 0; $i--) {
            $year = $now->year - $i;
            $earnings = Payment::where('payment_status', 'completed')
                ->whereYear('created_at', $year)
                ->sum('amount');
            
            $data[] = round($earnings, 2);
            $labels[] = $year;
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
            'filter' => 'yearly'
        ];
    }

    private function getMonthlyData($now)
    {
        $data = [];
        $labels = [];
        
        for ($i = 11; $i >= 0; $i--) {
            $month = $now->copy()->subMonths($i);
            $earnings = Payment::where('payment_status', 'completed')
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->sum('amount');
            
            $data[] = round($earnings, 2);
            $labels[] = $month->format('M');
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
            'filter' => 'monthly'
        ];
    }

    private function getWeeklyData($now)
    {
        $data = [];
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        $startOfWeek = $now->copy()->startOfWeek();
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $earnings = Payment::where('payment_status', 'completed')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->sum('amount');
            
            $data[] = round($earnings, 2);
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
            'filter' => 'weekly'
        ];
    }

    private function getDailyData($now)
    {
        // Daily behaves like weekly: current week's days Sun-Sat
        $data = [];
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        $startOfWeek = $now->copy()->startOfWeek();
        
        for ($i = 0; $i < 7; $i++) {
            $date = $startOfWeek->copy()->addDays($i);
            $earnings = Payment::where('payment_status', 'completed')
                ->whereDate('created_at', $date->format('Y-m-d'))
                ->sum('amount');
            
            $data[] = round($earnings, 2);
        }
        
        return [
            'labels' => $labels,
            'data' => $data,
            'filter' => 'daily'
        ];
    }

    public function allProductsForAdmin(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'nullable|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);
    
        $search = $request->input('search');
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
    
        $query = Product::query();
    
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->whereRaw("MATCH(name) AGAINST (? IN BOOLEAN MODE)", [$search]);
                //   ->orWhere('name', 'LIKE', '%' . $search . '%');
            });
        }
    
        $products = $query->orderBy('created_at', 'desc')
                         ->paginate($perPage, ['*'], 'page', $page);
    
        return response()->json([
            'status' => true,
            'message' => 'Products fetched successfully',
            'data' => $products // Return the entire paginator object
        ]);
    }

    public function allStoresForAdmin(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'nullable|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);
    
        $search = $request->input('search');
        $perPage = $request->input('per_page', 20);
        $page = $request->input('page', 1);
    
        // Query the locations table directly for complete store information
        $query = \App\Models\Location::select([
            'locationId',
            'storeName',
            'addressLine1',
            'city',
            'state',
            'zipCode',
            'latLng',
            'storeNumber'
        ]);
    
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('storeName', 'like', "%{$search}%")
                  ->orWhere('addressLine1', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }
    
        $stores = $query->orderBy('storeName', 'asc')
                       ->paginate($perPage, ['*'], 'page', $page);
    
        // Transform the data to split latLng into separate fields
        $transformedStores = $stores->getCollection()->map(function ($store) {
            $latLng = explode(',', $store->latLng);
            return [
                'locationId' => $store->locationId,
                'storeName' => $store->storeName,
                'addressLine1' => $store->addressLine1,
                'city' => $store->city,
                'state' => $store->state,
                'zipCode' => $store->zipCode,
                'latitude' => $latLng[0] ?? null,
                'longitude' => $latLng[1] ?? null,
                'storeNumber' => $store->storeNumber
            ];
        });
    
        // Replace the collection with our transformed data
        $stores->setCollection($transformedStores);
    
        return response()->json([
            'status' => true,
            'message' => 'Stores fetched successfully',
            'data' => $stores,
        ]);
    }

    public function allTransactionsForAdmin(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'search' => 'nullable|string|max:255',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1',
            ]);
        
            $search = $request->input('search');
            $perPage = $request->input('per_page', 20);
            $page = $request->input('page', 1);
        
            $query = Order::with(['payments', 'user' => function($q) {
                $q->select('id', 'name', 'email', 'phone', 'photo');
            }])
            ->whereNotNull('order_number')
            ->where('order_number', '!=', '');
        
            // Apply search filter
            if (!empty($search)) {
                $query->where(function($q) use ($search) {
                    $q->where('order_number', 'like', "%{$search}%")
                      ->orWhereHas('payments', function($q) use ($search) {
                          $q->where('transaction_id', 'like', "%{$search}%");
                      });
                });
            }
        
            $orders = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage, ['*'], 'page', $page);
            
            $orders->getCollection()->transform(function($order) {
                $payment = $order->payments->first();
                
                // Add photo URL to user object
                if ($order->user) {
                    $order->user->photo = $order->user->photo 
                        ? asset($order->user->photo) 
                        : asset('uploads/profiles/no_image.jpeg');
                }
                
                if ($payment) {
                    $order->total_amount = $payment->amount;
                    $order->payment_method = $payment->payment_method;
                    $order->payment_status = $payment->payment_status;
                    $order->transaction_id = $payment->transaction_id;
                    $order->payment_date = $payment->created_at;
                    $order->return_amount = $payment->shopper_amount;
                }
                unset($order->payments);
                return $order;
            });
        
            return response()->json([
                'status' => true,
                'message' => 'Transactions fetched successfully',
                'data' => $orders,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching transactions: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch transactions',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function transactionDetails($id)
    {
        try {
            $order = Order::with([
                'payments', 
                'user' => function($q) {
                    $q->select('id', 'name', 'email', 'phone', 'photo');
                },
                'orderItems' => function($q) {
                    $q->select('id', 'order_id', 'product_name', 'quantity', 'unit_price', 'total_price');
                }
            ])
            ->whereNotNull('order_number')
            ->where('order_number', '!=', '')
            ->where('id', $id)
            ->first();
        
            if (!$order) {
                return response()->json([
                    'status' => false,
                    'message' => 'Order not found',
                ], 404);
            }
        
            $payment = $order->payments->first();
            $orderData = [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => $order->total,
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'payment' => $payment ? [
                    'amount' => $payment->amount,
                    'payment_method' => $payment->payment_method,
                    'payment_status' => $payment->payment_status,
                    'transaction_id' => $payment->transaction_id,
                    'payment_date' => $payment->created_at,
                    'return_amount' => $payment->shopper_amount,
                    'delivery_charges' => $order->delivery_charges ?? null,
                    'tax' => $order->tax ?? null,
                    
                ] : null,
                'user' => $order->user ? [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'phone' => $order->user->phone,
                    'photo' => $order->user->photo 
                        ? asset($order->user->photo)
                        : asset('uploads/profiles/no_image.jpeg')
                ] : null,
                'items' => $order->orderItems->map(function($item) {
                    return [
                        'id' => $item->id,
                        'product_name' => $item->product_name,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total_price' => $item->total_price
                    ];
                })
            ];
        
            return response()->json([
                'status' => true,
                'message' => 'Transaction details fetched successfully',
                'data' => $orderData,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching transaction details: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch transaction details',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred',
            ], 500);
        }
    }

    public function updateUserPhoto(Request $request)
    {
          $user = auth()->user();
        $request->validate([
            'photo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        // Handle photo upload
        if ($request->hasFile('photo')) {
            // Delete old photo if exists
            if ($user->photo && file_exists(public_path($user->photo))) {
                unlink(public_path($user->photo));
            }
            
            $image = $request->file('photo');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('uploads/profiles');
            
            // Create directory if it doesn't exist
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            
            $image->move($destinationPath, $imageName);
            $user->photo = 'uploads/profiles/' . $imageName;
        }

        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Photo updated successfully',
            'data' => [
                'photo' => $user->photo ? asset($user->photo) : null,
            ]
        ]);
    }
}
