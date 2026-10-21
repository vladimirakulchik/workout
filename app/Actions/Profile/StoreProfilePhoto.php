<?php

declare(strict_types=1);

namespace App\Actions\Profile;

use App\Models\PlayerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class StoreProfilePhoto
{
    public const DISK = 'public';

    public const SIZE = 400;

    public const THUMBNAIL_SIZE = 96;

    /**
     * Store a resized photo and thumbnail for the user and remove the previous ones.
     * Returns the path of the 400px image; the thumbnail sits next to it with a `_thumb` suffix.
     */
    public function handle(User|PlayerProfile $user, UploadedFile $photo): string
    {
        $disk = Storage::disk(self::DISK);
        $directory = ($user instanceof PlayerProfile ? 'photos/profiles/' : 'photos/').$user->id;
        $name = Str::uuid()->toString();
        $path = "{$directory}/{$name}.webp";

        $disk->put($path, (string) Image::decodePath($photo->getRealPath())->cover(self::SIZE, self::SIZE)->encodeUsingFileExtension('webp'));
        $disk->put("{$directory}/{$name}_thumb.webp", (string) Image::decodePath($photo->getRealPath())->cover(self::THUMBNAIL_SIZE, self::THUMBNAIL_SIZE)->encodeUsingFileExtension('webp'));

        $previous = $user->photo_path;

        $user->forceFill(['photo_path' => $path])->save();

        if ($previous !== null && $previous !== $path) {
            $this->delete($previous);
        }

        return $path;
    }

    /**
     * Delete a stored photo together with its thumbnail.
     */
    public function delete(string $path): void
    {
        Storage::disk(self::DISK)->delete([$path, Str::beforeLast($path, '.').'_thumb.'.Str::afterLast($path, '.')]);
    }
}
