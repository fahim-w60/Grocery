<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Term;

class TermController extends Controller
{
    public function addTerm(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $term = Term::create($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'Term added successfully',
            'term' => $term,
        ]);
    }

    public function showTerm($id)
    {
        $term = Term::find($id);

        if (!$term) {
            return response()->json([
                'status' => false,
                'message' => 'Term not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'message' => 'Term fetched successfully',
            'term' => $term,
        ]);
    }
    
    public function updateTerm(Request $request, $id)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $term = Term::find($id);

        if (!$term) {
            return response()->json([
                'status' => false,
                'message' => 'Term not found',
            ], 404);
        }

        $term->update($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'Term updated successfully',
            'term' => $term,
        ]);
    }

    public function searchTerm(Request $request)
    {
        $validatedData = $request->validate([
            'search' => 'required|string|max:255',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'page' => 'sometimes|integer|min:1',
        ]);

        $searchTerm = $request->query('search');
        $perPage = $request->query('per_page', 20); // Default to 20 if not provided

        $terms = Term::where('name', 'LIKE', '%' . $searchTerm . '%')
            ->select('id', 'name')
            ->paginate($perPage);
            
        $response = [
            'status' => true,
            'message' => 'Terms fetched successfully',
            'meta' => [
                'current_page' => $terms->currentPage(),
                'per_page' => $terms->perPage(),
                'total' => $terms->total(),
                'last_page' => $terms->lastPage(),
            ],
            'links' => [
                'next' => $terms->nextPageUrl(),
                'prev' => $terms->previousPageUrl(),
            ],
            'data' => $terms->items(),
        ];

        return response()->json($response);
    }

    public function getAllTerms()
    {
        $terms = Term::select('id', 'name')->orderBy('name', 'asc')->get();

        return response()->json([
            'status' => true,
            'message' => 'All terms fetched successfully',
            'terms' => $terms,
        ]);
    }

    public function deleteTerm($id)
    {
        $term = Term::find($id);

        if (!$term) {
            return response()->json([
                'status' => false,
                'message' => 'Term not found',
            ], 404);
        }

        $term->delete();

        return response()->json([
            'status' => true,
            'message' => 'Term deleted successfully',
        ]);
    }
}
