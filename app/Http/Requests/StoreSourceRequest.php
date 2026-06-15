<?php

namespace App\Http\Requests;

use App\Services\Yandex\YandexUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSourceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                if (YandexUrl::tryParse((string) $this->input('url')) === null) {
                    $validator->errors()->add('url', 'Ссылка не похожа на карточку организации Яндекс.Карт.');
                }
            },
        ];
    }
}
