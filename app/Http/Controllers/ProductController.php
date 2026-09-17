<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\DTOs\ProductDTO;
use Illuminate\Http\Request;

class ProductController extends BaseController
{
    public function index(Request $request)
    {
        return $this->responseSuccess(Product::all());
    }

    public function store(Request $request)
    {
        $request->validate(ProductDTO::rules());
        $dto = ProductDTO::fromArray($request->all());
        return $this->responseSuccess(Product::create($dto->toArray()));
    }

    public function show($id)
    {
        return $this->responseSuccess(Product::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $request->validate(ProductDTO::rules());
        $dto = ProductDTO::fromArray($request->all());
        $item = Product::findOrFail($id);
        $item->update($dto->toArray());
        return $this->responseSuccess($item);
    }

    public function destroy($id)
    {
        return Product::findOrFail($id)->delete();
    }
}
