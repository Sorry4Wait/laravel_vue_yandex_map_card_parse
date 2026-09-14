<?php

namespace App\Http\Requests;

use App\Services\YandexMaps\OrgUrlResolver;
use Illuminate\Foundation\Http\FormRequest;

class SaveOrganizationLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'yandex_url' => [
                'required',
                'string',
                'max:2048',
                'url',
                function ($attribute, $value, $fail) {
                    if (! app(OrgUrlResolver::class)->looksLikeOrgUrl($value)) {
                        $fail('This does not look like a link to an organization card on Yandex Maps.');
                    }
                },
            ],
        ];
    }
}
