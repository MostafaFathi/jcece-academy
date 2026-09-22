<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Category::class);

        return CategoryResource::collection(
            Category::query()->with('parent')->orderBy('sort_order')->orderBy('id')->paginate(25),
        );
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $category = Category::create($request->validated());

        return (new CategoryResource($category))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Category $category): CategoryResource
    {
        Gate::authorize('view', $category);

        return new CategoryResource($category->load('children'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        Gate::authorize('update', $category);

        $category->update($request->validated());

        return new CategoryResource($category->refresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Category $category): JsonResponse|Response
    {
        Gate::authorize('delete', $category);

        if ($category->children()->exists() || $category->courses()->exists()) {
            return response()->json([
                'message' => 'A category with child categories or courses cannot be deleted.',
            ], 422);
        }

        $category->delete();

        return response()->noContent();
    }
}
