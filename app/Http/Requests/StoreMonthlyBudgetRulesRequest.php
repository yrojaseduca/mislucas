<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreMonthlyBudgetRulesRequest extends FormRequest
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
            'rules' => ['present', 'array'],
            'rules.*.category_id' => ['required', 'integer', 'distinct', Rule::exists('categories', 'id')->where('workspace_id', $this->route('workspace')->id)],
            'rules.*.default_amount' => ['required', 'integer', 'min:1'],
            'rules.*.rollover_policy' => ['required', 'in:expires,carry'],
        ];
    }
}
