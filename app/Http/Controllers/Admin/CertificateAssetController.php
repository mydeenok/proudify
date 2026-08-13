<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CertificateAssetController extends Controller
{
    private const DISK = 'public';

    private const FOLDER = 'certificate-assets';

    /**
     * Shared decorative gallery for the template builder. Seeds a few
     * CC0-style SVG seals/borders on first empty list so the panel is
     * never a blank square after install.
     */
    public function index(): JsonResponse
    {
        $this->ensureSeedAssets();

        $files = collect(Storage::disk(self::DISK)->files(self::FOLDER))
            ->filter(fn (string $path) => preg_match('/\.(png|jpe?g|svg|webp)$/i', $path))
            ->values()
            ->map(fn (string $path) => [
                'path' => $path,
                'url' => '/storage/'.$path,
                'name' => basename($path),
            ]);

        return response()->json($files);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:5120'],
        ]);

        $extension = strtolower($validated['file']->getClientOriginalExtension() ?: 'png');
        $basename = Str::slug(pathinfo($validated['file']->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'asset';
        $filename = $basename.'-'.Str::random(6).'.'.$extension;

        // Laravel's built-in 'image' rule deliberately excludes svg (it
        // isn't a raster image and can carry a <script>), which is why
        // this endpoint uses 'mimes' instead - but that means an uploaded
        // SVG's XML is never sanitized before being served back straight
        // from public storage. Anyone who opens that file's URL directly
        // (not as an <img> tag - as a top-level navigation) gets it
        // rendered as an SVG document, and any <script>/event-handler
        // inside runs in this app's origin: stored XSS. Sanitizing here
        // strips exactly that before the file ever touches disk.
        if ($extension === 'svg') {
            try {
                $safeSvg = $this->sanitizeSvg($validated['file']->getContent());
            } catch (\Throwable) {
                return response()->json(['message' => 'That file could not be read as valid SVG.'], 422);
            }

            $path = self::FOLDER.'/'.$filename;
            Storage::disk(self::DISK)->put($path, $safeSvg);
        } else {
            $path = $validated['file']->storeAs(self::FOLDER, $filename, self::DISK);
        }

        return response()->json([
            'path' => $path,
            'url' => '/storage/'.$path,
            'name' => basename($path),
        ], 201);
    }

    /**
     * Strips <script> elements and on*="" event-handler / javascript:
     * href attributes from an SVG's XML tree. Deliberately does NOT pass
     * LIBXML_NOENT or enable substituteEntities - leaving external entity
     * expansion off is what prevents this from being an XXE vector on top
     * of everything else.
     */
    private function sanitizeSvg(string $svg): string
    {
        libxml_use_internal_errors(true);

        $dom = new \DOMDocument;
        $loaded = $dom->loadXML($svg, LIBXML_NONET);

        libxml_clear_errors();

        if (! $loaded || ! $dom->documentElement) {
            throw new \RuntimeException('Uploaded file is not valid SVG/XML.');
        }

        $xpath = new \DOMXPath($dom);

        foreach (iterator_to_array($xpath->query('//*[local-name()="script"]')) as $scriptNode) {
            $scriptNode->parentNode?->removeChild($scriptNode);
        }

        foreach (iterator_to_array($xpath->query('//@*')) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = trim($attribute->nodeValue ?? '');

            $isEventHandler = str_starts_with($name, 'on');
            $isScriptUri = in_array($name, ['href', 'xlink:href'], true) && stripos($value, 'javascript:') === 0;

            if ($isEventHandler || $isScriptUri) {
                $attribute->ownerElement?->removeAttributeNode($attribute);
            }
        }

        return $dom->saveXML($dom->documentElement) ?: '';
    }

    private function ensureSeedAssets(): void
    {
        if (! Storage::disk(self::DISK)->exists(self::FOLDER)) {
            Storage::disk(self::DISK)->makeDirectory(self::FOLDER);
        }

        // The docblock above index() says this only seeds "on first empty
        // list," but the foreach below used to run unconditionally on
        // every call - i.e. every time the builder's asset panel opened -
        // silently overwriting seal-star.svg/border-ornament.svg/ribbon.svg
        // even after an admin had replaced or removed them. This actually
        // gates on the folder being empty, matching the documented intent.
        if (Storage::disk(self::DISK)->files(self::FOLDER) !== []) {
            return;
        }

        $seeds = [
            'seal-star.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 120 120" fill="none">
  <circle cx="60" cy="60" r="54" stroke="#03757f" stroke-width="4"/>
  <circle cx="60" cy="60" r="42" stroke="#fdcf48" stroke-width="3"/>
  <path d="M60 22 L68 48 L96 48 L74 64 L82 90 L60 74 L38 90 L46 64 L24 48 L52 48 Z" fill="#03757f"/>
</svg>
SVG,
            'border-ornament.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="200" height="40" viewBox="0 0 200 40" fill="none">
  <path d="M4 20 H70" stroke="#03757f" stroke-width="2"/>
  <circle cx="100" cy="20" r="10" stroke="#fdcf48" stroke-width="3"/>
  <path d="M130 20 H196" stroke="#03757f" stroke-width="2"/>
</svg>
SVG,
            'ribbon.svg' => <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="80" height="100" viewBox="0 0 80 100" fill="none">
  <path d="M40 8 L72 28 L60 36 L40 24 L20 36 L8 28 Z" fill="#b40012"/>
  <path d="M24 34 H56 V78 L40 68 L24 78 Z" fill="#03757f"/>
</svg>
SVG,
        ];

        foreach ($seeds as $name => $svg) {
            Storage::disk(self::DISK)->put(self::FOLDER.'/'.$name, $svg);
        }
    }
}
