<?php

namespace MyListerHub\Media\Http\Requests;

use MyListerHub\API\Http\Request;

class ImageRequest extends Request
{
    /**
     * Get the validation rules that apply to the request.
     */
    public function commonRules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string'],
            'width' => ['sometimes', 'nullable', 'numeric'],
            'height' => ['sometimes', 'nullable', 'numeric'],
        ];
    }

    public function storeRules(): array
    {
        return [
            'source' => ['required', 'string'],
        ];
    }

    public function updateRules(): array
    {
        if ($this->isMethod('PUT')) {
            $maxSize = config('media.storage.images.max_size');

            return [
                'file' => [
                    'required',
                    'image',
                    sprintf('mimes:%s', implode(',', config('media.storage.images.allowed_mimes'))),
                    ...$maxSize ? ["max:{$maxSize}"] : [],
                ],
            ];
        }

        return [
            'source' => ['sometimes', 'nullable', 'string']
        ];
    }
}
