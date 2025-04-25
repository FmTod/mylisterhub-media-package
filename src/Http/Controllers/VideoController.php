<?php

namespace MyListerHub\Media\Http\Controllers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use MyListerHub\API\Http\Controller;
use MyListerHub\Media\Http\Requests\VideoRequest;
use MyListerHub\Media\Http\Requests\VideoUploadRequest;
use MyListerHub\Media\Http\Resources\VideoResource;
use MyListerHub\Media\Models\Video;
use RahulHaque\Filepond\Facades\Filepond;
use RahulHaque\Filepond\Models\Filepond as FilepondModel;

class VideoController extends Controller
{
    protected string $request = VideoRequest::class;

    protected ?string $resource = VideoResource::class;

    protected function getModel(): string
    {
        return config('media.models.video', Video::class);
    }

    public function upload(VideoUploadRequest $request): JsonResource|ResourceCollection
    {
        $validated = $request->validated();

        $files = $request->type() === 'filepond'
            ? Filepond::field($validated['files'])->getModel()
            : $request->file('files');

        $path = config('media.storage.videos.path');
        $disk = config('media.storage.videos.disk');

        $videos = collect($files)->map(function (UploadedFile|FilepondModel $file) use ($path, $disk) {
            if ($file instanceof FilepondModel) {
                $content = Storage::disk(Filepond::getTempDisk())->get($file->filepath);
                $name = sprintf('%s_%s', now()->getTimestamp(), $file->filename);

                Storage::disk($disk)->put("{$path}/{$name}", $content);

                Storage::disk(Filepond::getTempDisk())->delete($file->filepath);
                $file->delete();
            } else {
                $name = sprintf('%s_%s', now()->getTimestamp(), $file->getClientOriginalName());

                $file->storeAs($path, $name, $disk);
            }

            return Video::create([
                'name' => $name,
                'path' => "{$path}/{$name}",
                'disk' => $disk,
                'url' => Storage::disk($disk)->url("{$path}/{$name}"),
            ]);
        });

        return $this->response($videos);
    }
}
