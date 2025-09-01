<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\admin\StoreController;
use App\Http\Controllers\admin\CategoryController;
use App\Http\Controllers\admin\TermController;
use App\Http\Controllers\admin\KrogerController;
use App\Http\Controllers\admin\LocationController;
use App\Http\Controllers\admin\BannerController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\AddToCartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\AboutUsController;
use App\Http\Controllers\admin\FAQcontroller;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\shopper\ShopperController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


//Auth
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('verify', [AuthController::class, 'verify']);
    Route::post('forget_password', [AuthController::class, 'forgetPassword']);
    Route::post('resend_otp', [AuthController::class, 'resendOTP']);
    Route::post('verify-token', [AuthController::class, 'verifyToken']);

    Route::middleware('jwt.auth')->group(function () {
        //Auth Routes
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('profile', [AuthController::class, 'profile']);
        Route::post('change_password', [AuthController::class, 'chnagePassword']);

    });
});

Route::prefix('app')->middleware('jwt.auth')->group(function(){
    // Messages
    Route::prefix('messages')->group(function() {
        Route::post('/', [MessageController::class, 'store']);
        Route::get('/sentMessages', [MessageController::class, 'sentMessages']);
        Route::get('/receivedMessages', [MessageController::class, 'receivedMessages']);
        Route::get('/unreadCount', [MessageController::class, 'unreadCount']);
        Route::get('message/{id}', [MessageController::class, 'message']);
    });

    Route::get('getAboutApp', [AboutUsController::class, 'getAboutApp']);
    Route::get('getAllFaqApp', [FAQcontroller::class, 'getAllFaqApp']);

    //Home
    Route::get('getAllHomeBanners', [HomeController::class, 'getAllHomeBanners']);
    Route::get('searchForPriceComparison', [HomeController::class, 'searchForPriceComparison']);
    Route::get('SearchProductWithFilter', [HomeController::class, 'SearchProductWithFilter']);

    //Kroger products section
    Route::get('kroger/products/categories', [HomeController::class, 'fetchKrogerCategories']);
    Route::post('kroger/products/categories/{category}', [HomeController::class, 'fetchKrogerProductByCategory']);
    Route::get('kroger/products/search', [HomeController::class, 'searchKrogerProducts']);
    Route::get('kroger/stores', [HomeController::class, 'fetchKrogerStores']);
    Route::post('kroger/products/stores/{store}', [HomeController::class, 'searchProductByStore']);
    Route::post('kroger/products/{id}', [HomeController::class, 'krogerProductById']);


    //Face and Finger id
    Route::post('addFaceId', [HomeController::class, 'addFaceId']);
    Route::post('addFingerId', [HomeController::class, 'addFingerId']);
    Route::post('getFaceId', [HomeController::class, 'getFaceId']);
    Route::post('getFingerId', [HomeController::class, 'getFingerId']);

    //set user location
    Route::post('setUserLocation', [HomeController::class, 'setUserLocation']);
    Route::get('getUserLocation', [HomeController::class, 'getUserLocation']);

    //Card
    Route::post('addCard', [HomeController::class, 'addCard']);
    Route::get('getCards', [HomeController::class, 'getCards']);
    Route::delete('removeCard/{id}', [HomeController::class, 'removeCard']);



    //Notifications
    Route::post('createNotification', [NotificationController::class, 'createNotification']);
    Route::get('getNotifications', [NotificationController::class, 'getNotifications']);
    Route::get('getNotification/{id}', [NotificationController::class, 'getNotification']);
    Route::post('readNotification/{id}', [NotificationController::class, 'readNotification']);
    Route::get('totalNotification', [NotificationController::class, 'totalNotification']);


    //Add to cart
    Route::post('addToCart', [AddToCartController::class, 'addToCart']);
    Route::get('getCart', [AddToCartController::class, 'getCart']);
    Route::get('getCartById/{id}', [AddToCartController::class, 'getCartById']);
    Route::delete('removeCart/{id}', [AddToCartController::class, 'removeCart']);
    Route::post('updateCart/{id}', [AddToCartController::class, 'updateCart']);
    Route::delete('deleteAllCart', [AddToCartController::class, 'deleteAllCart']);

    //Wishlist
    Route::post('addToWishlist', [WishlistController::class, 'addToWishlist']);
    Route::get('getWishlist', [WishlistController::class, 'getWishlist']);
    Route::get('getWishlistById/{id}', [WishlistController::class, 'getWishlistById']);
    Route::delete('removeWishlist/{id}', [WishlistController::class, 'removeWishlist']);
    Route::delete('deleteAllWishlist', [WishlistController::class, 'deleteAllWishlist']);

    //Profile
    Route::get('getProfile', [HomeController::class, 'getProfile']);
    Route::post('updateProfile', [HomeController::class, 'updateProfile']);


    //Shopper
    Route::get('getAllShopper', [HomeController::class, 'getAllShopper']);
    Route::get('personalShopper', [HomeController::class, 'personalShopper']);
    Route::post('makeShopper', [HomeController::class, 'makeShopper']);
    Route::delete('removeShopper', [HomeController::class, 'removeShopper']);
    Route::get('shopperDetails/{id}', [HomeController::class, 'shopperDetails']);
    Route::post('updateUserPhoto', [HomeController::class, 'updateUserPhoto']);

    //All Notification
    Route::get('getAllNotifications', [HomeController::class, 'getAllNotifications']);

    //All Transactions
    // Route::get('getAllTransactions', [HomeController::class, 'getAllTransactions']);

    //recommendation Product
    Route::get('recommendationProduct', [HomeController::class, 'recommendationProduct']);

    //About App
    Route::get('aboutApp', [HomeController::class, 'aboutApp']);

    //All Faq
    Route::get('allFaq', [HomeController::class, 'allFaq']);

    //Payment
    Route::prefix('payment')->middleware('jwt.auth')->group(function() {
        // Card payments
        Route::post('create-intent', [PaymentController::class, 'createPaymentIntent']);
        Route::post('confirm', [PaymentController::class, 'confirmPayment']); 
        Route::get('status/{payment_id}', [PaymentController::class, 'getPaymentStatus']);
        Route::post('reorder/{order_id}', [PaymentController::class, 'reorder']);
        Route::get('getAllTransactions', [PaymentController::class, 'getAllTransactions']);
        
        Route::post('create-crypto', [PaymentController::class, 'createCryptoPayment']);
        Route::post('confirm-crypto', [PaymentController::class, 'confirmCryptoPayment']);
        Route::post('crypto/webhook', [PaymentController::class, 'handleCryptoWebhook'])
            ->name('api.payments.crypto.webhook')
            ->withoutMiddleware(['jwt.auth']); // Webhook needs to be public
            
        // Crypto payment callbacks
        Route::get('crypto/success', [PaymentController::class, 'cryptoPaymentSuccess'])
            ->name('api.payments.crypto.success')
            ->withoutMiddleware(['jwt.auth']);
            
        Route::get('crypto/cancel', [PaymentController::class, 'cryptoPaymentCancel'])
            ->name('api.payments.crypto.cancel')
            ->withoutMiddleware(['jwt.auth']);
    });

    //Orders
    Route::prefix('orders')->middleware('jwt.auth')->group(function() {
        Route::get('/', [OrderController::class, 'getOrders']);           
        Route::get('/{id}', [OrderController::class, 'getOrderDetails']); 
        Route::post('/{id}/status', [OrderController::class, 'updateOrderStatus']);
        Route::get('/{id}/track', [OrderController::class, 'trackOrder']);
        Route::post('pickedUpItems', [OrderController::class, 'pickedUpItems']);
    });

});


Route::prefix('shopper')->middleware('jwt.auth')->group(function(){
    Route::get('recentOrders', [ShopperController::class, 'recentOrders']);
    Route::get('recentOrderDetails/{id}', [ShopperController::class, 'recentOrderDetails']);
    Route::get('pendingOrders', [ShopperController::class, 'pendingOrders']);
    Route::get('pendingOrderDetails/{id}', [ShopperController::class, 'pendingOrderDetails']);
    Route::get('newOrders', [ShopperController::class, 'newOrders']);
    Route::get('newOrderDetails/{id}', [ShopperController::class, 'newOrderDetails']);
    Route::post('orderPickedUp', [ShopperController::class, 'orderPickedUp']);
    Route::post('sendDeliveryRequest', [ShopperController::class, 'sendDeliveryRequest']);
    Route::post('getUserAndShopperlatLong', [ShopperController::class, 'getUserAndShopperlatLong']);
    Route::post('activeInactiveShopper', [ShopperController::class, 'activeInactiveShopper']);
    Route::get('allShopperOrders', [OrderController::class, 'allShopperOrders']);
    Route::get('getShopperStatus', [ShopperController::class, 'getShopperStatus']);
});


//Social login 
Route::prefix('social')->group(function () {
    Route::get('login', [AuthController::class, 'socialLogin']);
    Route::post('google/callback', [AuthController::class, 'googleCallback']);
});

//Admin Routes
Route::group(['prefix' => 'admin', 'middleware' => ['jwt.auth', 'admin']], function(){

    //AllProduct
    Route::get('allProducts', [HomeController::class, 'allProductsForAdmin']);

    //AllTransaction
    Route::get('allTransactions', [HomeController::class, 'allTransactionsForAdmin']);
    Route::get('allTransactions/{id}', [HomeController::class, 'transactionDetails']);

    //AllStore
    Route::get('allStores', [HomeController::class, 'allStoresForAdmin']);

    //AllShopper
    Route::get('allShoppersForAdmin', [ShopperController::class, 'allShoppersForAdmin']);
    Route::post('updateShopper', [ShopperController::class, 'updateShopper']);

    Route::controller(StoreController::class)->group(function(){
        Route::post('addStore', 'addStore')->name('admin.addStore');
    });
    Route::controller(CategoryController::class)->group(function(){
        Route::post('addCategory', 'addCategory');
        Route::get('showCategory/{id}', 'showCategory');
        Route::post('updateCategory/{id}', 'updateCategory');
        Route::post('searchCategory', 'searchCategory');
        Route::get('getAllCategories', 'getAllCategories');
        Route::delete('deleteCategory/{id}', 'deleteCategory');
    });

    Route::controller(TermController::class)->group(function(){
        Route::post('addTerm', 'addTerm');
        Route::get('showTerm/{id}', 'showTerm');
        Route::post('updateTerm/{id}', 'updateTerm');
        Route::get('searchTerm', 'searchTerm');
        Route::get('getAllTerms', 'getAllTerms');
        Route::delete('deleteTerm/{id}', 'deleteTerm');
    });

    Route::controller(LocationController::class)->group(function(){
        Route::post('addGeolocation', 'addGeolocation');
        Route::get('showGeolocation/{id}', 'showGeolocation');
        Route::post('updateGeolocation/{id}', 'updateGeolocation');
        Route::get('searchGeolocation', 'searchGeolocation');
        Route::get('getAllGeolocations', 'getAllGeolocations');
        Route::delete('deleteGeolocation/{id}', 'deleteGeolocation');
    });

    Route::controller(BannerController::class)->group(function(){
        Route::post('addBanner', 'addBanner');
        Route::get('showBanner/{id}', 'showBanner');
        Route::post('updateBanner/{id}', 'updateBanner');
        Route::get('searchBanner', 'searchBanner');
        Route::get('getAllBanners', 'getAllBanners');
        Route::delete('deleteBanner/{id}', 'deleteBanner');
    });

    Route::controller(OrderController::class)->group(function(){
        Route::get('newOrder', 'newOrder');
        Route::get('pendingOrder', 'pendingOrder');
        Route::get('pendingOrder', 'pendingOrder');
        Route::get('completeOrder', 'completeOrder');
        Route::get('orderDetailsForAdmin/{id}', 'orderDetailsForAdmin');
        Route::delete('deleteOrder/{id}', 'deleteOrder');
        Route::get('allOrders', 'allOrders');

    });

    //About Us
    Route::post('setAboutUs', [AboutUsController::class, 'setAboutUs']);
    Route::get('getAboutUs', [AboutUsController::class, 'getAboutUs']);

    //FAQ
    Route::post('addFaq', [FAQcontroller::class, 'addFaq']);
    Route::get('getAllFaq', [FAQcontroller::class, 'getAllFaq']);
    Route::get('showFaq/{id}', [FAQcontroller::class, 'showFaq']);
    Route::post('updateFaq/{id}', [FAQcontroller::class, 'updateFaq']);
    Route::delete('deleteFaq/{id}', [FAQcontroller::class, 'deleteFaq']);

    //Update Admin Profile
    Route::post('updateAdminProfile', [HomeController::class, 'updateAdminProfile']);
    Route::post('updateAdminPhoto', [HomeController::class, 'updateAdminPhoto']);

    //Dashboard
    Route::get('dashboard', [HomeController::class, 'dashboard']);

    //total user
    Route::get('totalUser', [HomeController::class, 'totalUser']);

    Route::controller(NotificationController::class)->group(function(){
        Route::post('sendNormalNotifications', 'sendBulkNormalNotification');
    });


    // Route::get('kroger/products', [KrogerController::class, 'fetchKrogerProducts']);
    // Route::get('kroger/products/{id}', [KrogerController::class, 'fetchKrogerProductById']);
    // Route::get('kroger/products/search/{term}', [KrogerController::class, 'searchKrogerProducts']);


    // Route::get('kroger/products/categories', [KrogerController::class, 'fetchKrogerCategories']);
    // Route::get('kroger/products/categories/{category}', [KrogerController::class, 'fetchKrogerProductByCategory']);
    // Route::get('kroger/products/search', [KrogerController::class, 'searchKrogerProducts']);
    // Route::get('kroger/stores', [KrogerController::class, 'fetchKrogerStores']);
    // Route::get('kroger/products/stores/{store}', [KrogerController::class, 'searchProductByStore']);
});


