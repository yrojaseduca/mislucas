<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace
            && $workspace->members()->where('user_id', $this->user()?->id)->exists();
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('workspace_id', $this->route('workspace')->id)->where('kind', $this->input('type')))],
            'type' => ['required', 'in:income,expense'],
            'name' => ['required', 'string', 'max:255'],
            'month' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'integer', 'min:1'],
            'rollover_policy' => ['required', 'in:expires,carry'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
