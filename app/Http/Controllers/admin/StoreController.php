<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\Admin\AddStoreRequest;
use App\Servuces\StoreService;
use Exception;


class StoreController extends Controller
{
    protected $storeService;
    public function __construct(StoreService $storeService)
    {
        $this->storeService = $storeService;
    }
    public function addStore(AddStoreRequest $request)
    {

        $validatedData = $request->validated();

        $result =  $this->storeService->addStore($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Store created successfully',
            'store' => $result['store'],
        ]);
       
    }
}
