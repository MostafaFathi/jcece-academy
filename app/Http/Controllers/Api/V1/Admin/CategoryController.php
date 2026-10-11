<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCategoryRequest;
use App\Http\Resources\Api\V1\CategoryResource;
use App\Models\Category;
use App\Services\CatalogImageStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Throwable;

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
    public function store(StoreCategoryRequest $request, CatalogImageStorage $images): JsonResponse
    {
        Gate::authorize('create', Category::class);

        $attributes = $request->safe()->except('image_upload');
        $newImage = $request->hasFile('image_upload') ? $images->store($request->file('image_upload'), 'catalog/categories', 'image_upload') : null;
        if ($newImage !== null) {
            $attributes['image'] = $newImage;
        }

        try {
            $category = Category::create($attributes);
        } catch (Throwable $exception) {
            $images->deleteManaged($newImage);
            throw $exception;
        }

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
    public function update(UpdateCategoryRequest $request, Category $category, CatalogImageStorage $images): CategoryResource
    {
        Gate::authorize('update', $category);

        $attributes = $request->safe()->except('image_upload');
        $oldImage = $category->image;
        $newImage = $request->hasFile('image_upload') ? $images->store($request->file('image_upload'), 'catalog/categories', 'image_upload') : null;
        if ($newImage !== null) {
            $attributes['image'] = $newImage;
        }

        try {
            $category->update($attributes);
        } catch (Throwable $exception) {
            $images->deleteManaged($newImage);
            throw $exception;
        }
        if (array_key_exists('image', $attributes) && $oldImage !== $category->image) {
            $images->deleteManaged($oldImage);
        }

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
