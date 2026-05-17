<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Intervention\Image\ImageManager;

class FloorplanUploadController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'floorplan' => ['required', 'file', 'image', 'max:4096'],
        ]);

        $dir = public_path('images');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $uploaded = $request->file('floorplan');
        $tempName = 'floorplan-upload.'.$uploaded->getClientOriginalExtension();
        $tempPath = $uploaded->move($dir, $tempName)->getPathname();

        $manager = ImageManager::gd();
        $image = $manager->read($tempPath);

        if ($image->width() > 1920) {
            $newHeight = (int) round(($image->height() / $image->width()) * 1920);
            $image->resize(1920, max(1, $newHeight));
        }

        $optimizedJpgPath = $dir.DIRECTORY_SEPARATOR.'floorplan.jpg';
        $image->toJpeg(80)->save($optimizedJpgPath);

        // Keep legacy path in sync so existing floor-map rendering continues to work unchanged.
        $legacyPngPath = $dir.DIRECTORY_SEPARATOR.'floorplan.png';
        $image->toPng()->save($legacyPngPath);

        if ($tempPath !== $optimizedJpgPath && is_file($tempPath)) {
            @unlink($tempPath);
        }

        Setting::set('floorplan_image', 'images/floorplan.png');

        return redirect()
            ->route('admin.tables', ['edit' => 1])
            ->with('success', 'Floor plan image saved and optimized as public/images/floorplan.jpg.');
    }
}
