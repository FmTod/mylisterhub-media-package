<?php

namespace MyListerHub\Media\Http\Controllers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MyListerHub\API\Http\Controller;
use MyListerHub\Media\Http\Requests\ImageRequest;
use MyListerHub\Media\Http\Requests\ImageUploadRequest;
use MyListerHub\Media\Http\Resources\ImageResource;
use MyListerHub\Media\Models\Image;
use RahulHaque\Filepond\Facades\Filepond;
use RahulHaque\Filepond\Models\Filepond as FilepondModel;

class ImageController extends Controller
{
    protected string $request = ImageRequest::class;

    protected ?string $resource = ImageResource::class;

    public function upload(ImageUploadRequest $request): JsonResource|ResourceCollection
    {
        $files = $request->type() === 'filepond'
            ? $request->input('files')
            : $request->file('files');

        $path = config('media.storage.images.path');
        $disk = config('media.storage.images.disk');

        $images = collect($files)->map(function (UploadedFile|string $file) use ($path, $disk) {
            if (is_string($file)) {
                $filepond = Filepond::field($file);
                /** @var FilepondModel $model */
                $model = $filepond->getModel();

                $content = Storage::disk($model->disk)->get($model->filepath);
                $name = sprintf('%s_%s', now()->getTimestamp(), $model->filename);

                $tempPath = tempnam(sys_get_temp_dir(), 'media_');
                file_put_contents($tempPath, $content);

                if ($model->disk === Filepond::getTempDisk()) {
                    $filepond->copyTo("{$path}/{$name}", $disk);
                }
            } else {
                $name = sprintf('%s_%s', now()->getTimestamp(), $file->getClientOriginalName());
                $tempPath = $file->getRealPath();

                $file->storeAs($path, $name, $disk);
            }

            $image = \Spatie\Image\Image::load($tempPath);

            return Image::create([
                'name' => $name,
                'source' => $name,
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
            ]);
        });

        return $this->response($images);
    }

    protected function getModel(): string
    {
        return config('media.models.image', Image::class);
    }
}
