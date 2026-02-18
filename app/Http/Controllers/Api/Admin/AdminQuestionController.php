<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Question;
use Illuminate\Support\Facades\Storage;

class AdminQuestionController extends Controller
{
    public function index(Request $request)
    {
        $q = Question::query()
            ->with('category:id,name')
            ->withCount('options')
            ->when($request->category_id, fn($qq) => $qq->where('category_id', $request->category_id))
            ->when($request->search, fn($qq) => $qq->where('question', 'like', "%{$request->search}%"))
            ->orderBy('id','desc')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $q]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'category_id' => ['required','integer','exists:categories,id'],
            'question' => ['required','string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'question_type' => ['required', 'string', 'in:text,image'],
            'explanation' => ['nullable','string'],
        ]);

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('questions', 'public');
        }

        $q = Question::create($data);

        return response()->json(['success' => true, 'data' => $q], 201);
    }

    public function show(Question $question)
    {
        $question->load('category:id,name', 'options:id,question_id,label,text,score_value');

        return response()->json(['success' => true, 'data' => $question]);
    }

    public function update(Request $request, Question $question)
    {
        $data = $request->validate([
            'category_id' => ['sometimes','required','integer','exists:categories,id'],
            'question' => ['sometimes','required','string'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'question_type' => ['sometimes', 'required', 'string', 'in:text,image'],
            'explanation' => ['nullable','string'],
        ]);

        if ($request->hasFile('image')) {
            // delete old image if exists
            if ($question->image) {
                Storage::disk('public')->delete($question->image);
            }
            $data['image'] = $request->file('image')->store('questions', 'public');
        }

        $question->update($data);

        return response()->json(['success' => true, 'data' => $question->fresh()]);
    }

    public function destroy(Question $question)
    {
        $question->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}
