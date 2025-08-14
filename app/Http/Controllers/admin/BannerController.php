<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Banner;
use Illuminate\Support\Facades\File;

class BannerController extends Controller
{
    public function addBanner(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255',
            'banner_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        if ($request->hasFile('banner_image')) {
            $image = $request->file('banner_image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('media/banner');
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            $image->move($destinationPath, $imageName);
            $validatedData['banner_image'] = 'media/banner/' . $imageName;
        }

        $banner = Banner::create($validatedData);
        $banner->banner_image = $banner->banner_image ? asset($banner->banner_image) : null;

        return response()->json([
            'status' => true,
            'message' => 'Banner added successfully',
            'banner' => $banner,
        ]);
    }

    public function showBanner($id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json([
                'status' => false,
                'message' => 'Banner not found',
            ], 404);
        }
        $banner->banner_image = $banner->banner_image ? asset($banner->banner_image) : null;
        return response()->json([
            'status' => true,
            'message' => 'Banner fetched successfully',
            'banner' => $banner,
        ]);
    }

    public function searchBanner(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'required|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);
        $searchTerm = $request->query('search');
        $perPage = $request->query('per_page', 20);
        $banners = Banner::where('name', 'LIKE', '%' . $searchTerm . '%')
            ->select('id', 'name', 'banner_image', 'created_at', 'updated_at')
            ->paginate($perPage);
        foreach ($banners as $banner) {
            $banner->banner_image = $banner->banner_image ? asset($banner->banner_image) : null;
        }
        $response = [
            'status' => true,
            'message' => 'Banners fetched successfully',
            'meta' => [
                'current_page' => $banners->currentPage(),
                'per_page' => $banners->perPage(),
                'total' => $banners->total(),
                'last_page' => $banners->lastPage(),
            ],
            'links' => [
                'next' => $banners->nextPageUrl(),
                'prev' => $banners->previousPageUrl(),
            ],
            'data' => $banners->items(),
        ];
        return response()->json($response);
    }

    public function getAllBanners()
    {
        $banners = Banner::select('id', 'name', 'banner_image', 'created_at', 'updated_at')->orderBy('id', 'desc')->get();
        foreach ($banners as $banner) {
            $banner->banner_image = $banner->banner_image ? asset($banner->banner_image) : null;
        }
        return response()->json([
            'status' => true,
            'message' => 'All banners fetched successfully',
            'banners' => $banners,
        ]);
    }

    public function updateBanner(Request $request, $id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json([
                'status' => false,
                'message' => 'Banner not found',
            ], 404);
        }
        $validatedData = $request->validate([
            'name' => 'nullable|string|max:255',
            'banner_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);
        if ($request->hasFile('banner_image')) {
            if ($banner->banner_image && file_exists(public_path($banner->banner_image))) {
                @unlink(public_path($banner->banner_image));
            }
            $image = $request->file('banner_image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $destinationPath = public_path('media/banner');
            if (!File::exists($destinationPath)) {
                File::makeDirectory($destinationPath, 0755, true);
            }
            $image->move($destinationPath, $imageName);
            $validatedData['banner_image'] = 'media/banner/' . $imageName;
        }
        $banner->update($validatedData);
        $banner->banner_image = $banner->banner_image ? asset($banner->banner_image) : null;
        return response()->json([
            'status' => true,
            'message' => 'Banner updated successfully',
            'banner' => $banner,
        ]);
    }

    public function deleteBanner($id)
    {
        $banner = Banner::find($id);
        if (!$banner) {
            return response()->json([
                'status' => false,
                'message' => 'Banner not found',
            ], 404);
        }
        // Unlink image
        if ($banner->banner_image && file_exists(public_path($banner->banner_image))) {
            @unlink(public_path($banner->banner_image));
        }
        $banner->delete();
        return response()->json([
            'status' => true,
            'message' => 'Banner deleted successfully',
        ]);
    }

}
