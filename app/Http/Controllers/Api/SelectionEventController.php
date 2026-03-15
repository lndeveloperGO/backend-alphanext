<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SelectionEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SelectionEventController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $events = SelectionEvent::with('products.category')->get();
        return response()->json([
            'success' => true,
            'data' => $events
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'color' => 'nullable|string',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $event = SelectionEvent::create($request->except('product_ids'));

        if ($request->has('product_ids')) {
            $event->products()->sync($request->product_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event created successfully',
            'data' => $event->load('products.category')
        ], 210);
    }

    /**
     * Display the specified resource.
     */
    public function show(SelectionEvent $selectionEvent)
    {
        return response()->json([
            'success' => true,
            'data' => $selectionEvent->load('products.category')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SelectionEvent $selectionEvent)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'color' => 'nullable|string',
            'product_ids' => 'nullable|array',
            'product_ids.*' => 'exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $selectionEvent->update($request->except('product_ids'));

        if ($request->has('product_ids')) {
            $selectionEvent->products()->sync($request->product_ids);
        }

        return response()->json([
            'success' => true,
            'message' => 'Event updated successfully',
            'data' => $selectionEvent->load('products.category')
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SelectionEvent $selectionEvent)
    {
        $selectionEvent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Event deleted successfully'
        ]);
    }
}
