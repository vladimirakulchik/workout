<?php

declare(strict_types=1);

namespace App\Actions\Trainer;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class UpdateBranding
{
    public const DISK = 'public';

    public const MAX_SIZE = 200;

    /**
     * Save the trainer's brand colour and, when given, a new logo (raster images are scaled down to 200x200).
     */
    public function handle(User $trainer, ?string $color, ?UploadedFile $logo = null): void
    {
        $profile = $trainer->trainerProfile()->firstOrFail();
        $previous = $profile->logo_path;
        $attributes = ['brand_color' => $color === null ? null : strtoupper($color)];

        if ($logo !== null) {
            $attributes['logo_path'] = $this->storeLogo($trainer, $logo);
        }

        $profile->forceFill($attributes)->save();

        if ($logo !== null && $previous !== null) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }

    /**
     * Remove the logo and colour so the default branding applies again.
     */
    public function reset(User $trainer): void
    {
        $profile = $trainer->trainerProfile()->firstOrFail();
        $previous = $profile->logo_path;

        $profile->forceFill(['logo_path' => null, 'brand_color' => null])->save();

        if ($previous !== null) {
            Storage::disk(self::DISK)->delete($previous);
        }
    }

    private function storeLogo(User $trainer, UploadedFile $logo): string
    {
        $disk = Storage::disk(self::DISK);
        $extension = strtolower($logo->getClientOriginalExtension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $path = 'logos/'.$trainer->id.'/'.Str::uuid()->toString().'.'.$extension;

        if ($extension === 'svg') {
            $disk->put($path, (string) file_get_contents($logo->getRealPath()));

            return $path;
        }

        $image = Image::decodePath($logo->getRealPath())->scaleDown(self::MAX_SIZE, self::MAX_SIZE);
        $disk->put($path, (string) $image->encodeUsingFileExtension($extension));

        return $path;
    }
}
