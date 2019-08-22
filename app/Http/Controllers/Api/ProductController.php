<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Project;
use App\Product;
use App\Http\Requests\Api\CreateProduct;
use App\Http\Requests\Api\UpdateProduct;
use App\Http\Requests\Api\DeleteProduct;
use App\Http\Requests\Api\ManageProductProject;
use App\Services\Stripe\StripePlansService;

class ProductController extends ApiController
{
    /**
     * Create a Product controller instance.
     *
     * @param StripePlansService $service
     */
    public function __construct(StripePlansService $service)
    {
        $this->service = $service;
    }
    /**
     * Get all user products.
     *
     * @return \Illuminate\Http\JsonResponse
     */

    public function index()
    {
        return $this->respond([
            'products' => user()
                ->products_sold()
                ->withCount(['plans'])
                ->orderBy('created_at', 'desc')
                ->get(),
        ]);
    }

    /**
     * Get product by id.
     *
     * @param  string  $id
     * @return \Illuminate\Http\JsonResponse
     */

    public function show($id)
    {
        $product = Product::with(['projects', 'plans'])
            ->where('id', $id)
            ->firstOrFail();
        if ($product->user_id != auth()->id() && user()->acl < 9) {
            return $this->respondForbidden();
        }

        return $this->respond(compact('product'));
    }

    /**
     * Create a new product and return the product if successful.
     *
     * @param  CreateProduct  $request
     * @return \Illuminate\Http\JsonResponse
     */

    public function store(CreateProduct $request)
    {
        $data = $request->get('product');
        if (user()->connect_id) {
            try {
                $result = $this->service->createProduct($data);
            } catch (\Exception $e) {
                log_error($e);
                return $this->respondError($e->getMessage());
            }
            $data['payment_id'] = $result->id;
            $data['payment_type'] = 'stripe';
        }
        $data['type'] = array_get($data, 'type', 'service');
        $data['slug'] = str_slug_u(array_get($data, 'name'));
        $data['sold'] = true;
        $data['meta'] = [];

        $product = user()
            ->products()
            ->create($data);

        return $this->respond(compact('product'));
    }

    /**
     * Update the product given by its id and return the product if successful.
     *
     * @param  UpdateProduct  $request
     * @param  Product  $product
     * @return \Illuminate\Http\JsonResponse
     */

    public function update(UpdateProduct $request, Product $product)
    {
        if ($product->managed) {
            $product->update([
                'description' => $request->input('product.description'),
            ]);
            if ($product->managed && $product->payment_id && user()->connect_id) {
                $this->service->updateProduct(
                    $request->input('product.description'),
                    $product->payment_id
                );
            }
        } else {
            $result = $product->update([
                'name' => $request->input('product.name'),
                'slug' => str_slug_u($request->input('product.name')),
                'payment_type' => $request->input('product.payment_type'),
                'payment_id' => $request->input('product.payment_id'),
                'description' => $request->input('product.description'),
            ]);
        }

        return $this->respond(compact('product'));
    }

    /**
     * Delete the product given by its id.
     *
     * @param  DeleteProduct $request
     * @param  Product  $product
     * @return \Illuminate\Http\JsonResponse
     */

    public function destroy(DeleteProduct $request, Product $product)
    {
        if ($product->managed && $product->payment_id && user()->connect_id) {
            $this->service->deleteProduct($product->payment_id);
        }
        $product->delete();

        return ok();
    }

    /**
     * Associate project with the product
     *
     * @param ManageProductProject $request
     * @param Product $product
     * @return \Illuminate\Http\JsonResponse
     */
    public function addProject(ManageProductProject $request, Product $product)
    {
        $project = Project::findOrFail($request->input('project.id'));
        if ($project->user_id != auth()->id()) {
            return $this->respondForbidden("wrong project");
        }
        $product->projects()->syncWithoutDetaching([$project->id]);

        return ok();
    }

    /**
     * Remove project association
     *
     * @param Product $product
     * @param Project $project
     * @return \Illuminate\Http\JsonResponse
     */
    public function removeProject(Product $product, Project $project)
    {
        if ($product->user_id != auth()->id() && user()->acl < 9) {
            return $this->respondForbidden("wrong product");
        }
        // if ($project->user_id != auth()->id() && user()->acl < 9) {
        //     return $this->respondForbidden("wrong project");
        // }
        $product->projects()->detach($project->id);

        return ok();
    }

    /**
     * Import products from csv
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $result = Storage::makeDirectory('/csv/products/' . user()->id);
        if (!$result) {
            return $this->respondError(
                Lang::getFromJson(
                    "There has been an error while saving a file. Please try again."
                ),
                500
            );
        }

        $file = $request->file('file');
        // TODO: check file type first. gz,zip,csv

        $path = $file->store('/csv/products/' . user()->id);
        try {
            Product::import(Storage::path($path));
        } catch (\Exception $e) {
            return $this->respondError($e->getMessage(), 400);
        }

        return $this->respondNoContent();
    }
}
