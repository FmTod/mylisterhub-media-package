<?php

namespace MyListerHub\Media\Http\Controllers;

use Exception;
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
use Spatie\Image\Image as SpatieImage;

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

        $result = Media::storeImage(source: $file->getRealPath(), name: $name);

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

        $images = collect($files)->map(fn (UploadedFile|string $file) => $this->processUploadedFile($file));

        return $this->response($images);
    }

    protected function processUploadedFile(UploadedFile|string $file): Image
    {
        if ($file instanceof UploadedFile) {
            return Media::createImageFromFile($file);
        }

        $path = config('media.storage.images.path');
        $disk = config('media.storage.images.disk');

        $filepond = Filepond::field($file);
        /** @var FilepondModel $filepondModel */
        $filepondModel = $filepond->getModel();

        $name = sprintf('%s_%s', now()->getTimestamp(), $filepondModel->filename);

        try {
            $content = Storage::disk($filepondModel->disk)->readStream($filepondModel->filepath);
            $tempPath = tempnam(sys_get_temp_dir(), 'media_');
            $destStream = fopen($tempPath, 'wb');

            if ($content && $destStream) {
                stream_copy_to_stream($content, $destStream);
                fclose($content);
                fclose($destStream);
            }

            $image = SpatieImage::load($tempPath);
        } catch (Exception $e) {
            report($e);
            $image = null;
        }

        if ($filepondModel->disk === Filepond::getTempDisk()) {
            $filepond->copyTo("{$path}/{$name}", $disk, 'public');
        }

        return ($this->getModel())::create([
            'name' => $name,
            'source' => $name,
            'width' => $image?->getWidth(),
            'height' => $image?->getHeight(),
        ]);
    }

    protected function getModel(): string
    {
        return config('media.models.image', Image::class);
    }
}
