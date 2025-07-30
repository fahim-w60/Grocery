<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ShopperController extends Controller
{
    /**
     * Get all shoppers
     */
    public function getAllShoppers()
    {
        try {
            $shoppers = User::where('role', 'shopper')
                ->select('id', 'name', 'email', 'phone', 'address', 'shopper_id', 'created_at')
                ->get();

            return response()->json([
                'status' => true,
                'message' => 'Shoppers retrieved successfully',
                'data' => $shoppers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve shoppers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific shopper by ID
     */
    public function getShopper($id)
    {
        try {
            $shopper = User::where('role', 'shopper')
                ->where('id', $id)
                ->select('id', 'name', 'email', 'phone', 'address', 'shopper_id', 'created_at')
                ->first();

            if (!$shopper) {
                return response()->json([
                    'status' => false,
                    'message' => 'Shopper not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'message' => 'Shopper retrieved successfully',
                'data' => $shopper
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve shopper',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new shopper
     */
    public function makeShopper(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8',
                'phone' => 'required|string|max:20',
                'address' => 'nullable|string|max:255',
                'shopper_id' => 'nullable|string|max:100|unique:users'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $shopper = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
                'address' => $request->address,
                'shopper_id' => $request->shopper_id,
                'role' => 'shopper'
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Shopper created successfully',
                'data' => [
                    'id' => $shopper->id,
                    'name' => $shopper->name,
                    'email' => $shopper->email,
                    'phone' => $shopper->phone,
                    'address' => $shopper->address,
                    'shopper_id' => $shopper->shopper_id,
                    'role' => $shopper->role,
                    'created_at' => $shopper->created_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create shopper',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}