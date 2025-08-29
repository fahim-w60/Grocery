<?php

namespace App\Http\Controllers;

use App\Models\Aboutus;
use Illuminate\Http\Request;

class AboutUsController extends Controller
{

    public function setAboutUs(Request $request)
    {
        $validated = $request->validate([
            'content' => 'required|string',
        ]);

        $aboutUs = Aboutus::updateOrCreate(
            ['id' => 1], 
            ['content' => $validated['content']]
        );

        return response()->json([
            'status' => true,
            'message' => 'About us content set successfully',
            'data' => $aboutUs
        ]);
    }


    public function getAboutUs()
    {
        $aboutUs = Aboutus::first();

        return response()->json([
            'status' => true,
            'message' => 'About us content retrieved successfully',
            'data' => $aboutUs ?? ['content' => '']
        ]);
    }

    public function getAboutApp()
    {
        $aboutUs = Aboutus::first();

        return response()->json([
            'status' => true,
            'message' => 'About us content retrieved successfully',
            'data' => $aboutUs ?? ['content' => '']
        ]);
    }
}
