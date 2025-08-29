<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;

class FAQcontroller extends Controller
{
    public function addFaq(Request $request)
    {
        $validated = $request->validate([
            'question' => 'required|string',
            'answer' => 'required|string',
        ]);

        $faq = Faq::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'FAQ added successfully',
            'data' => $faq
        ]);
    }

    public function getAllFaq()
    {
        $faqs = Faq::all();
        
        return response()->json([
            'status' => true,
            'data' => $faqs
        ]);
    }

    public function showFaq($id)
    {
        $faq = Faq::find($id);
        
        if (!$faq) {
            return response()->json([
                'status' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $faq
        ]);
    }

    public function updateFaq(Request $request, $id)
    {
        $faq = Faq::find($id);
        
        if (!$faq) {
            return response()->json([
                'status' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        $validated = $request->validate([
            'question' => 'sometimes|string',
            'answer' => 'sometimes|string',
        ]);

        $faq->update($validated);

        return response()->json([
            'status' => true,
            'message' => 'FAQ updated successfully',
            'data' => $faq
        ]);
    }

    public function deleteFaq($id)
    {
        $faq = Faq::find($id);
        
        if (!$faq) {
            return response()->json([
                'status' => false,
                'message' => 'FAQ not found'
            ], 404);
        }

        $faq->delete();

        return response()->json([
            'status' => true,
            'message' => 'FAQ deleted successfully'
        ]);
    }

    public function getAllFaqApp()
    {
        $faqs = Faq::all();
        
        return response()->json([
            'status' => true,
            'data' => $faqs
        ]);
    }
}
