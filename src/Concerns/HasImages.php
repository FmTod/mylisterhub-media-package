<?php

namespace MyListerHub\Media\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use MyListerHub\Media\Actions\UpsertImages;
use MyListerHub\Media\Models\Image;

trait HasImages
{
    /**
     * Validation rules for models with custom attributes.
     *
     * @return string[]
     */
    public static function imageValidationRules(): array
    {
        return [
            'images' => 'sometimes|nullable|array',
            'images.*' => 'sometimes|nullable',
            'images.*.id' => 'sometimes|nullable|integer|exists:images,id',
        ];
    }

    /**
     * Get product images.
     */
    public function images(): MorphToMany
    {
        return $this->morphToMany($this->getImageRelatedModel(), 'imageable')->withPivot(['order'])->orderByRaw('-`imageables`.`order` DESC');
    }

    /**
     * Get the first image to be used as a thumbnail.
     */
    public function thumbnail(): MorphOne
    {
        return $this->morphOne($this->getImageRelatedModel(), 'imageable')->oldestOfMany();
    }

    /**
     * Sync images based on the provided data.
     */
    public function createImages(Collection|array $images, bool $detaching = true, bool $copyImageFromUrl = false): static
    {
        if (! $images instanceof Collection) {
            $images = collect($images);
        }

        $imageClass = $this->getImageRelatedModel();

        $uploadedImages = $images
            ->mapWithKeys(function (mixed $imageData, int $index) use ($imageClass, $copyImageFromUrl) {
                if ($imageData instanceof Image) {
                    return [$imageData->id => ['order' => $imageData->pivot?->order ?? $index]];
                }

                if ($imageData instanceof UploadedFile) {
                    $image = $imageClass::createFromFile($imageData);

                    return [$image->id => ['order' => $index]];
                }

                if (is_string($imageData)) {
                    $image = $imageClass::createFromUrl($imageData, $copyImageFromUrl);

                    return [$image->id => ['order' => $index]];
                }

                if (is_array($imageData)) {
                    $image = match (true) {
                        isset($imageData['id']) => $imageClass::find($imageData['id']),
                        isset($imageData['source']) => $imageClass::updateOrCreate(['source' => $imageData['source']], $imageData),
                        isset($imageData['url']) => $imageClass::createFromUrl($imageData['url'], $copyImageFromUrl),
                        default => null,
                    };

                    if (! $image) {
                        return null;
                    }

                    return [$image->id => ['order' => $imageData['order'] ?? $index]];
                }

                return null;
            })
            ->filter();

        $this->images()->sync($uploadedImages, $detaching);

        return $this;
    }

    /**
     * Sync given images with the current imageable model.
     *
     * @param  \Illuminate\Support\Collection|Image[]  $images
     */
    public function syncImages(Collection|array $images, bool $detaching = true): static
    {
        if (! $images instanceof Collection) {
            $images = collect($images);
        }

        $imageIds = UpsertImages::run($images);

        $this->images()->sync($imageIds, $detaching);

        return $this;
    }

    /**
     * Get the related model for images.
     *
     * @return class-string<\MyListerHub\Media\Models\Image>
     */
    protected function getImageRelatedModel(): string
    {
        return config('media.models.image', Image::class);
    }
}
