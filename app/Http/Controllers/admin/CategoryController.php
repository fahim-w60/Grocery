<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Services\CategoryService;
use Illuminate\Http\Request;
use App\Http\Requests\Admin\SearchRequest;
class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function addCategory(StoreCategoryRequest $request)
    {
        // dd('hello');
        $validatedData = $request->validated();
        
        $result = $this->categoryService->addCategory($validatedData);

        return response()->json([
            'status' => true,
            'message' => 'Category store successfully',
            'category' => $result['category'],
        ]);

    }

    public function showCategory(String $id)
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Category fetched successfully',
            'category' => $category,
        ]);
    }

    public function updateCategory(StoreCategoryRequest $request, String $id)
    {
        $validatedData = $request->validated();

        $result = $this->categoryService->updateCategory($id, $validatedData);

        return response()->json([
            'status' => true,
            'message' => 'Category updated successfully.',
            'category' => $result['category'],
        ]);
    }

    public function searchCategory(SearchRequest $request)
    {
        $validatedData = $request->validated();
        $searchTerm = $validatedData['search'] ?? '';
        $perPage = $request->input('per_page', 20); // Default to 20 if not provided

        $categories = Category::where('name', 'LIKE', '%' . $searchTerm . '%')
            ->select('id', 'name')
            ->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Categories fetched successfully',
            'categories' => $categories,
        ]);
    }

    public function getAllCategories()
    {
        $categories = Category::all();

        return response()->json([
            'status' => true,
            'message' => 'All categories fetched successfully',
            'categories' => $categories,
        ]);
    }

    public function deleteCategory(String $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();

        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully',
        ]);
    }

}
