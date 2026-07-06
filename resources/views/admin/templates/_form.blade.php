@php $template = $template ?? null; @endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
    <div class="lg:col-span-2 space-y-lg">
        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm space-y-md">
            <div>
                <x-input-label for="name" value="Template name" />
                <x-text-input id="name" name="name" type="text" required :value="old('name', $template?->name)" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="category" value="Category" />
                <x-text-input id="category" name="category" type="text" placeholder="e.g. Achievement, Recognition, Completion" :value="old('category', $template?->category)" />
                <x-input-error :messages="$errors->get('category')" />
            </div>

            <div class="grid grid-cols-2 gap-md">
                <div>
                    <x-input-label for="page_format" value="Page format" />
                    <select id="page_format" name="page_format" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md font-body-md text-body-md text-on-surface focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none">
                        @foreach (['a4' => 'A4', 'letter' => 'Letter'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('page_format', $template?->page_format ?? 'a4') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="orientation" value="Orientation" />
                    <select id="orientation" name="orientation" class="h-11 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md font-body-md text-body-md text-on-surface focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none">
                        @foreach (['landscape' => 'Landscape', 'portrait' => 'Portrait'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('orientation', $template?->orientation ?? 'landscape') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm">
            <x-input-label for="html_content" value="HTML content" />
            <p class="font-body-sm text-body-sm text-on-surface-variant mb-sm">
                Available placeholders: {title} {recipient_name} {description} {date_of_issue} {date_of_expiry} {organization_name} {qrcode} {signature} {verification_code}
            </p>
            <textarea id="html_content" name="html_content" rows="16" required
                class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-mono text-body-sm text-on-surface focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none"
            >{{ old('html_content', $template?->html_content) }}</textarea>
            <x-input-error :messages="$errors->get('html_content')" />
        </div>
    </div>

    <div class="space-y-lg">
        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm space-y-md">
            <div>
                <x-input-label for="thumbnail" value="Thumbnail image" />
                <input id="thumbnail" name="thumbnail" type="file" accept="image/*" class="block w-full text-body-sm text-on-surface-variant">
                <x-input-error :messages="$errors->get('thumbnail')" />
                @if ($template?->thumbnail_path)
                    <img src="{{ \Illuminate\Support\Facades\Storage::url($template->thumbnail_path) }}" class="mt-sm rounded-lg border border-outline-variant" alt="Current thumbnail">
                @endif
            </div>

            <label class="flex items-center gap-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $template?->is_active)) class="rounded border-outline-variant text-primary focus:ring-primary">
                <span class="font-body-sm text-body-sm text-on-surface">Live (visible to users)</span>
            </label>

            <label class="flex items-center gap-sm">
                <input type="checkbox" name="is_exclusive" value="1" @checked(old('is_exclusive', $template?->is_exclusive)) class="rounded border-outline-variant text-primary focus:ring-primary">
                <span class="font-body-sm text-body-sm text-on-surface">Exclusive (premium plans only)</span>
            </label>
        </div>

        <x-primary-button class="w-full">{{ $template ? 'Save Changes' : 'Create Template' }}</x-primary-button>
    </div>
</div>
