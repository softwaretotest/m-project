<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\DTOs\ShopDTO;
use Illuminate\Http\Request;

class ShopController extends BaseController
{
    public function index(Request $request)
    {
        return $this->responseSuccess(Shop::all());
    }

    public function store(Request $request)
    {
        $request->validate(ShopDTO::rules());
        $dto = ShopDTO::fromArray($request->all());
        return $this->responseSuccess(Shop::create($dto->toArray()));
    }

    public function show($id)
    {
        return $this->responseSuccess(Shop::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $request->validate(ShopDTO::rules());
        $dto = ShopDTO::fromArray($request->all());
        $item = Shop::findOrFail($id);
        $item->update($dto->toArray());
        return $this->responseSuccess($item);
    }

    public function destroy($id)
    {
        return Shop::findOrFail($id)->delete();
    }
}
