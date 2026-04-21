<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * REQ-M6-002: validates the inbound PATCH payload for a workbench rename.
 * `name` is required and must be a non-empty string of at most 120 chars
 * after leading/trailing whitespace is trimmed.
 */
class RenameWorkbenchRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level authorization is handled by WorkbenchPolicy in the
        // controller (REQ-M6-001); the request class only validates shape.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        if (is_string($name)) {
            $this->merge(['name' => trim($name)]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:1', 'max:120'],
        ];
    }
}
