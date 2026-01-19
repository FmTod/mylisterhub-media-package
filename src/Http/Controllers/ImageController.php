<?php

namespace MyListerHub\Media\Http\Controllers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MyListerHub\API\Http\Controller;
use MyListerHub\API\Http\Request;
use MyListerHub\Media\Facades\Media;
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

    public function update(Request $request, $id)
    {
        if ($request->isMethod('PATCH')) {
            return parent::update($request, $id);
        }

        $model = $this->getModel();
        $image = $model::findOrFail($id);

        $file = $request->file('file');
        $isUrl = Str::matches('/^https?:\/\//', $image->source);
        $name = $isUrl ? sprintf('%s_%s', now()->getTimestamp(), $file->getClientOriginalName()) : $image->name;

        $result = Media::processAndStoreImage(
            sourcePath: $file->getRealPath(),
            destinationName: $name,
        );

        $image->update([
            'name' => $result->name,
            'source' => $isUrl ? $result->name : $image->source,
            'width' => $result->width,
            'height' => $result->height,
        ]);

        return $this->response($image);
    }

    public function upload(ImageUploadRequest $request): JsonResource|ResourceCollection
    {
        $files = $request->type() === 'filepond'
            ? $request->input('files')
            : $request->file('files');

        $images = collect($files)->map(function (UploadedFile|string $file) {
            if (is_string($file)) {
                // Handle Filepond upload
                $filepond = Filepond::field($file);
                /** @var FilepondModel $model */
                $model = $filepond->getModel();

                $name = sprintf('%s_%s', now()->getTimestamp(), $model->filename);
                $tempPath = tempnam(sys_get_temp_dir(), 'media_');

                // Use stream to copy a file instead of loading into memory
                $sourceStream = Storage::disk($model->disk)->readStream($model->filepath);
                $destStream = fopen($tempPath, 'wb');

                if ($sourceStream && $destStream) {
                    stream_copy_to_stream($sourceStream, $destStream);
                    fclose($sourceStream);
                    fclose($destStream);
                }
            } else {
                // Handle regular file upload
                $name = sprintf('%s_%s', now()->getTimestamp(), $file->getClientOriginalName());
                $tempPath = $file->getRealPath();
            }

            @unlink($tempPath);

            $result = Media::processAndStoreImage($tempPath, $name);
            $model = $this->getModel();

            return $model::create([
                'name' => $result->name,
                'source' => $result->name,
                'width' => $result->width,
                'height' => $result->height,
            ]);
        });

        return $this->response($images);
    }

    protected function getModel(): string
    {
        return config('media.models.image', Image::class);
    }
}
