<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\DTOs\OrderDTO;
use Illuminate\Http\Request;

class OrderController extends BaseController
{
    public function index(Request $request)
    {
        return $this->responseSuccess(Order::all());
    }

    public function store(Request $request)
    {
        $request->validate(OrderDTO::rules());
        $dto = OrderDTO::fromArray($request->all());
        return $this->responseSuccess(Order::create($dto->toArray()));
    }

    public function show($id)
    {
        return $this->responseSuccess(Order::findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $request->validate(OrderDTO::rules());
        $dto = OrderDTO::fromArray($request->all());
        $item = Order::findOrFail($id);
        $item->update($dto->toArray());
        return $this->responseSuccess($item);
    }

    public function destroy($id)
    {
        return Order::findOrFail($id)->delete();
    }
}
