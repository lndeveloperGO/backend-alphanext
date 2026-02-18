<?php

namespace App\Imports;

use App\Models\Question;
use App\Models\QuestionOption;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QuestionsImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows)
    {
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                // Skip if main fields are missing
                if (!isset($row['category_id']) || !isset($row['question'])) {
                    continue;
                }

                $questionData = [
                    'category_id'   => $row['category_id'],
                    'question'      => $row['question'],
                    'question_type' => $row['question_type'] ?? 'text',
                    'explanation'   => $row['explanation'] ?? null,
                ];

                // Handle image from URL if provided
                if (!empty($row['question_image_url'])) {
                    $questionData['image'] = $this->downloadAndStore($row['question_image_url'], 'questions');
                }

                $question = Question::create($questionData);

                // Handle Options A-E
                foreach (['a', 'b', 'c', 'd', 'e'] as $label) {
                    $textKey = "opt_{$label}_text";
                    $scoreKey = "opt_{$label}_score";
                    $imageKey = "opt_{$label}_image_url";

                    if (!empty($row[$textKey])) {
                        $optionData = [
                            'question_id' => $question->id,
                            'label'       => strtoupper($label),
                            'text'        => $row[$textKey],
                            'score_value' => $row[$scoreKey] ?? 0,
                        ];

                        if (!empty($row[$imageKey])) {
                            $optionData['image'] = $this->downloadAndStore($row[$imageKey], 'options');
                        }

                        QuestionOption::create($optionData);
                    }
                }
            }
        });
    }

    protected function downloadAndStore($url, $folder)
    {
        try {
            $response = Http::get($url);
            if ($response->successful()) {
                $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
                $filename = $folder . '/' . Str::random(40) . '.' . $extension;
                Storage::disk('public')->put($filename, $response->body());
                return $filename;
            }
        } catch (\Exception $e) {
            // Log error or ignore
        }
        return null;
    }
}
