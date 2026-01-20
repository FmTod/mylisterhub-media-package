<?php

namespace MyListerHub\Media\Services;

use Illuminate\Support\Facades\Storage;
use Override;
use RahulHaque\Filepond\Filepond;
use RahulHaque\Filepond\Models\Filepond as FilepondModel;

/**
 * Streamed Filepond Service Decorator
 *
 * This service extends the base Filepond class to use streaming for file operations
 * instead of loading entire files into memory. It acts as a decorator that provides
 * memory-efficient file transfers between storage disks.
 *
 * Features:
 * - Stream-based file transfers for better memory efficiency
 * - Handles large file uploads without memory exhaustion
 * - Supports both single and multiple file uploads
 * - Maintains all original Filepond functionality
 * - Custom visibility settings for stored files
 *
 * Use Cases:
 * - Large file uploads (videos, high-resolution images, archives)
 * - Memory-constrained environments
 * - Transferring files between different storage disks
 *
 * Methods:
 * - moveTo(): Stream files from temp storage to a permanent location and delete originals
 * - copyTo(): Stream files from temp storage to a permanent location keeping originals
 * - putFile(): Internal method that performs the actual streaming operation
 *
 * @see \RahulHaque\Filepond\Filepond
 */
class StreamedFilepond extends Filepond
{
    #[Override]
    public function moveTo(string $path, string $disk = '', string $visibility = ''): ?array
    {
        if (! $this->getFieldValue()) {
            return null;
        }

        if ($this->getIsMultipleUpload()) {
            $response = [];
            $fileponds = $this->getFieldModel();
            foreach ($fileponds as $index => $filepond) {
                $to = $path . '-' . ($index + 1);
                $response[] = $this->putFile($filepond, $to, $disk, $visibility);
            }
            $this->delete();

            return $response;
        }

        $filepond = $this->getFieldModel();
        $response = $this->putFile($filepond, $path, $disk, $visibility);
        $this->delete();

        return $response;
    }

    #[Override]
    public function copyTo(string $path, string $disk = '', string $visibility = ''): ?array
    {
        if (! $this->getFieldValue()) {
            return null;
        }

        if ($this->getIsMultipleUpload()) {
            $response = [];
            $fileponds = $this->getFieldModel();
            foreach ($fileponds as $index => $filepond) {
                $to = $path . '-' . ($index + 1);
                $response[] = $this->putFile($filepond, $to, $disk, $visibility);
            }

            return $response;
        }

        $filepond = $this->getFieldModel();

        return $this->putFile($filepond, $path, $disk, $visibility);
    }

    protected function putFile(FilepondModel $filepond, string $path, string $disk, string $visibility): array
    {
        $permanentDisk = $disk === '' ? $filepond->disk : $disk;

        $pathInfo = pathinfo($path);

        Storage::disk($permanentDisk)->writeStream(
            $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.' . $filepond->extension,
            Storage::disk($this->getTempDisk())->readStream($filepond->filepath),
            ['visibility' => $visibility],
        );

        return [
            'id' => $filepond->id,
            'dirname' => dirname($path . '.' . $filepond->extension),
            'basename' => basename($path . '.' . $filepond->extension),
            'extension' => $filepond->extension,
            'filename' => basename($path . '.' . $filepond->extension, '.' . $filepond->extension),
            'location' => $path . '.' . $filepond->extension,
            'url' => Storage::disk($permanentDisk)->url($path . '.' . $filepond->extension),
        ];
    }
}
