<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AttemptController;
use App\Http\Controllers\Api\PackageController;
use App\Http\Controllers\Api\Admin\AdminCategoryController;
use App\Http\Controllers\Api\Admin\AdminPackageController;
use App\Http\Controllers\Api\Admin\AdminQuestionController;
use App\Http\Controllers\Api\Admin\AdminQuestionOptionController;
use App\Http\Controllers\Api\Admin\AdminPackageQuestionController;
use App\Http\Controllers\Api\Catalog\CategoryCatalogController;
use App\Http\Controllers\Api\Catalog\PackageCatalogController;
use App\Http\Controllers\Api\Admin\QuestionBulkController;
use App\Http\Controllers\Api\Admin\AdminQuestionImportController;
use App\Http\Controllers\Api\Admin\AdminUserController;
use App\Http\Controllers\Api\Admin\PromoCodeController;
use App\Http\Controllers\Api\PromoController;
use App\Http\Controllers\Api\Admin\ProductController;
use App\Http\Controllers\Api\UserDashboardController;
use App\Http\Controllers\Api\PublicProductController;
use App\Http\Controllers\Api\UserStatisticsDashboardController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\DuitkuCallbackController;
use App\Http\Controllers\Api\RankingController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\Admin\AdminOrderController;
use App\Http\Controllers\Api\UserPackageController;
use App\Http\Controllers\Api\UserMaterialController;
use App\Http\Controllers\Api\Admin\AdminMaterialController;
use App\Http\Controllers\Api\Admin\AdminMaterialPartController;
use App\Http\Controllers\Api\Admin\AdminPackageMaterialController;
use App\Http\Controllers\Api\MidtransWebhookController;


/*
|--------------------------------------------------------------------------
| AUTH (public)
|--------------------------------------------------------------------------
*/

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);


    // logout butuh login
    Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| ME & LOGOUT (butuh login)
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::put('/me/profile', [AuthController::class, 'updateProfile']);
    Route::put('/me/password', [AuthController::class, 'changePassword']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

/*
|--------------------------------------------------------------------------
| PACKAGES (public: list + detail)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::get('/products', [PublicProductController::class, 'index']);
    Route::get('/products/{product}', [PublicProductController::class, 'show']);

    Route::get('/packages', [PackageController::class, 'index']);
    Route::get('/packages/{package}', [PackageController::class, 'show']);
});


/*
|--------------------------------------------------------------------------
| USER FEATURES (butuh login)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // payment
     Route::post('/orders/{order}/pay', [OrderPaymentController::class, 'pay']);

    // attempt / tryout
    Route::post('/packages/{package}/attempts', [AttemptController::class, 'start']);
    Route::get('/attempts/{attempt}', [AttemptController::class, 'show']);
    Route::get('/attempts/{attempt}/questions/{no}', [AttemptController::class, 'question']);
    Route::post('/attempts/{attempt}/answers', [AttemptController::class, 'answer']);
    Route::post('/attempts/{attempt}/mark', [AttemptController::class, 'mark']);
    Route::post('/attempts/{attempt}/submit', [AttemptController::class, 'submit']);
    Route::get('/attempts/{attempt}/review', [AttemptController::class, 'review']);

    // history
    Route::get('/user/attempts', [AttemptController::class, 'history']);

    // promo code validation
    Route::post('/promo/validate', [PromoController::class, 'validateCode']);

    // user product dashboard
    Route::get('/user/dashboard', [UserDashboardController::class, 'index']);

    // user statistics
    Route::get('/user/statistics-dashboard', [UserStatisticsDashboardController::class, 'index']);

    // rankings
    Route::get('/packages/{package}/ranking', [RankingController::class, 'perPackage']);

    // order
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // user packages
    Route::get('/user/packages', [UserPackageController::class, 'index']);

    // user materials
    Route::get('/materials', [UserMaterialController::class, 'index']);
    Route::get('/materials/{material}', [UserMaterialController::class, 'show']);
    Route::get('/packages/{package}/materials', [UserMaterialController::class, 'byPackage']);
});

/*
|--------------------------------------------------------------------------
| MidTrans (public)
|--------------------------------------------------------------------------
*/
Route::post('/payments/midtrans/notify', [MidtransWebhookController::class, 'notify']);


/*
|--------------------------------------------------------------------------
| CATALOG (butuh login)
|--------------------------------------------------------------------------
*/
Route::prefix('catalog')->group(function () {
    Route::get('/categories', [CategoryCatalogController::class, 'index']);
    Route::get('/packages', [PackageCatalogController::class, 'index']);
    Route::get('/packages/{package}', [PackageCatalogController::class, 'show']);
});

/*
|--------------------------------------------------------------------------
| ADMIN
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'admin'])
    ->prefix('admin')
    ->group(function () {
        // Users management
        Route::apiResource('users', AdminUserController::class)
            ->only(['index', 'show', 'store', 'update', 'destroy']);

        Route::get('/ping', fn() => response()->json(['ok' => true, 'message' => 'admin ok']));
        Route::apiResource('categories', AdminCategoryController::class);
        Route::apiResource('packages', AdminPackageController::class);
        Route::apiResource('questions', AdminQuestionController::class);
        Route::post('questions/bulk', [QuestionBulkController::class, 'store']);
        Route::post('questions/import', [AdminQuestionImportController::class, 'import']);

        // options
        Route::post('questions/{question}/options', [AdminQuestionOptionController::class, 'store']);
        Route::patch('options/{option}', [AdminQuestionOptionController::class, 'update']);
        Route::delete('options/{option}', [AdminQuestionOptionController::class, 'destroy']);

        Route::get('packages/{package}/questions', [AdminPackageQuestionController::class, 'index']);
        Route::put('packages/{package}/questions', [AdminPackageQuestionController::class, 'sync']);

        // promo codes
        Route::apiResource('promo-codes', PromoCodeController::class);
        Route::get('promo-codes/{promo_code}/assignments', [PromoCodeController::class, 'assignments']);
        Route::put('promo-codes/{promo}/packages', [PromoCodeController::class, 'syncPackages']);
        Route::put('promo-codes/{promo}/products', [PromoCodeController::class, 'syncProducts']);
        // DELETE single assignment
        Route::delete('promo-codes/{promo}/packages/{package}', [PromoCodeController::class, 'detachPackage']);
        Route::delete('promo-codes/{promo}/products/{product}', [PromoCodeController::class, 'detachProduct']);

        // products
        Route::apiResource('products', ProductController::class);

        // order
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::post('/orders/{order}/mark-paid', [AdminOrderController::class, 'markPaid']);
        Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);

        // materials
        Route::apiResource('materials', AdminMaterialController::class);
        Route::get('materials/{material}/parts', [AdminMaterialPartController::class, 'index']);
        Route::post('materials/{material}/parts', [AdminMaterialPartController::class, 'store']);
        Route::patch('materials/{material}/parts/{part}', [AdminMaterialPartController::class, 'update']);
        Route::delete('materials/{material}/parts/{part}', [AdminMaterialPartController::class, 'destroy']);
        // package-materials
        Route::get('packages/{package}/materials', [AdminPackageMaterialController::class, 'index']);
        Route::put('packages/{package}/materials', [AdminPackageMaterialController::class, 'sync']);
        Route::delete('packages/{package}/materials/{material}', [AdminPackageMaterialController::class, 'destroy']);
    });
