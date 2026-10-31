<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', 'integer', Rule::exists('activity_categories', 'id')],
            'location_name' => ['required', 'string', 'max:160'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'timezone' => ['required', 'string', 'timezone'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'preferences' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function activityData(): array
    {
        $data = $this->safe()->only([
            'title',
            'description',
            'category_id',
            'location_name',
            'latitude',
            'longitude',
            'timezone',
            'preferences',
        ]);

        $timezone = (string) $this->input('timezone');

        $data['start_at'] = CarbonImmutable::parse((string) $this->input('start_at'), $timezone);
        $data['end_at'] = CarbonImmutable::parse((string) $this->input('end_at'), $timezone);

        return $data;
    }
}
