<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class AdminCategoryController extends Controller
{
    public function index(Request $request)
    {
        $q = Category::query()
            ->when($request->search, fn($qq) => $qq->where('name', 'like', "%{$request->search}%"))
            ->orderBy('id','desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required','string','max:150'],
            'type' => ['sometimes','string','in:tryout,bimbel'],
            'parent_id' => ['nullable','integer','exists:categories,id'],
        ]);

        $cat = Category::create($data);

        return response()->json(['success' => true, 'data' => $cat], 201);
    }

    public function show(Category $category)
    {
        return response()->json(['success' => true, 'data' => $category]);
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['sometimes','required','string','max:150'],
            'type' => ['sometimes','string','in:tryout,bimbel'],
            'parent_id' => ['nullable','integer','exists:categories,id'],
        ]);

        $category->update($data);

        return response()->json(['success' => true, 'data' => $category->fresh()]);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
