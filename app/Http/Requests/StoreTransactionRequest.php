<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreTransactionRequest extends FormRequest
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
            'account_id' => ['nullable', 'integer', Rule::exists('accounts', 'id')->where('workspace_id', $this->route('workspace')->id)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('workspace_id', $this->route('workspace')->id)],
            'paid_by_member_id' => ['nullable', 'integer', Rule::exists('workspace_members', 'id')->where('workspace_id', $this->route('workspace')->id)],
            'type' => ['required', 'in:income,expense'],
            'amount' => ['required', 'integer', 'min:1'],
            'occurred_at' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'splits' => ['present', 'array'],
            'splits.*.member_id' => ['required', 'integer'],
            'splits.*.amount' => ['required', 'integer', 'min:0'],
            'splits.*.percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recurrence' => ['nullable', 'array'],
            'recurrence.frequency' => ['required_with:recurrence', 'in:weekly,monthly,yearly'],
            'recurrence.ends_on' => ['nullable', 'date', 'after_or_equal:occurred_at'],
            'debt_payment' => ['nullable', 'array'],
            'debt_payment.debt_id' => ['required_with:debt_payment', 'integer', Rule::exists('debts', 'id')->where('workspace_id', $this->route('workspace')->id)],
            'debt_payment.interest_amount' => ['required_with:debt_payment', 'integer', 'min:0', 'lte:amount'],
        ];
    }
}
