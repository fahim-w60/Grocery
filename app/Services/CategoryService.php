<?php 

namespace App\Services;

use App\Models\Category;

class CategoryService
{
    public function addCategory($data)
    {
        $category = new Category();
        $category->name = $data['name'] ?? '';
        $category->save();

        return [
            'category' => $category,
        ];
    }

    public function updateCategory($id, $data)
    {
        $category = Category::findOrFail($id);
        $category->name = $data['name'] ?? '';
        $category->save();
        
        return [
            'category' => $category,
        ];
    }
}
