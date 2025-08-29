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

        $searchTerm = trim($request->query('search'));
        $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
        $perPage = $request->query('per_page', 10);

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

    public function getAllTerms(Request $request)
    {
        $hasSearch = $request->filled('search');
        $hasPagination = $request->has('per_page') || $request->has('page');
        if ($hasSearch || $hasPagination) {
            $validatedData = $request->validate([
                'search' => 'nullable|string|max:255',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'page' => 'sometimes|integer|min:1',
            ]);

            $query = Term::select('id', 'name');

            if ($hasSearch) {
                $searchTerm = trim((string) $request->query('search'));
                $searchTerm = preg_replace('/\s+/', ' ', $searchTerm);
                $query->where('name', 'LIKE', '%' . $searchTerm . '%');
            }

            $perPage = $request->query('per_page', 10);
            $terms = $query->orderBy('id', 'desc')->paginate($perPage);

            $response = [
                'status' => true,
                'message' => 'Terms fetched successfully',
                'data' => $terms,
            ];

            return response()->json($response);
        }

        $terms = Term::select('id', 'name')->orderBy('id', 'desc')->get();

        return response()->json([
            'status' => true,
            'message' => 'All terms fetched successfully',
            'total' => $terms->count(),
            'data' => $terms,
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
