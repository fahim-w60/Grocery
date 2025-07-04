<?php 

namespace App\Servuces;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;


class StoreService{

    public function addStore($data)
    {
        DB::beginTransaction();

        try{

            $store = new Store();
            $store->store_name = $data['store_name'] ?? '';
            $store->category = $data['category'] ?? '';
            $store->owner_email = $data['owner_email'] ?? '';
            $store->save();
    
            $user = new User();
            $user->email = $data['owner_email'] ?? '';
            $user->password = Hash::make($data['password']);
            $user->role = 'shopper';
            $user->save();
    
            DB::commit();

            return [
                'store' => $store,
            ];

        } catch(\Exception $e)
        {
            DB::rollBack();
            return response()->json([
                'error' => 'Failed to create store',
                'message' => $e->getMessage(),
            ]);
        }
    }
}