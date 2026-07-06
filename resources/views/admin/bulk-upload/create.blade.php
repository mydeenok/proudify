<x-layouts.admin-shell title="Bulk Upload">
    <x-page-header
        title="High-Volume Issuance"
        description="Upload and process large batches of certificates without quota restrictions."
    />

    <form method="POST" action="{{ route('admin.bulk-upload.store') }}" enctype="multipart/form-data"
        x-data="{ userName: '', templateName: '', fileName: '' }"
        class="grid grid-cols-1 lg:grid-cols-12 gap-gutter"
    >
        @csrf

        <div class="lg:col-span-8 flex flex-col gap-gutter">
            <div class="card-surface bento-shadow-sm p-lg">
                <div class="flex items-center gap-sm mb-md">
                    <div class="w-8 h-8 rounded-full bg-primary-container/20 flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[18px]">corporate_fare</span>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Target Organization</h3>
                </div>
                <x-input-label for="user_id" value="Assign to Organization/User" />
                <select id="user_id" name="user_id" required
                    x-on:change="userName = $event.target.options[$event.target.selectedIndex].text"
                    class="form-input">
                    <option value="">Search by organization or email…</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->organization_name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('user_id')" />
            </div>

            <div class="card-surface bento-shadow-sm p-lg">
                <div class="flex items-center gap-sm mb-md">
                    <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[18px]">design_services</span>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Select Template</h3>
                </div>
                <select id="template_id" name="template_id" required
                    x-on:change="templateName = $event.target.options[$event.target.selectedIndex].text"
                    class="form-input">
                    <option value="">Select a template…</option>
                    @foreach ($templates as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('template_id')" />
            </div>

            <div class="card-surface bento-shadow-sm p-lg">
                <div class="flex items-center gap-sm mb-md">
                    <div class="w-8 h-8 rounded-full bg-surface-container-high flex items-center justify-center text-on-surface-variant">
                        <span class="material-symbols-outlined text-[18px]">upload_file</span>
                    </div>
                    <h3 class="font-headline-md text-headline-md text-on-surface">Data Source</h3>
                </div>
                <label data-drop-zone class="block border-2 border-dashed border-outline-variant rounded-xl p-xl flex flex-col items-center justify-center text-center bg-surface-container-lowest hover:bg-surface-container-low transition-colors cursor-pointer group h-64 relative">
                    <input type="file" name="file" accept=".csv,.xlsx,.xls" required class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10"
                        x-on:change="fileName = $event.target.files[0]?.name ?? ''"
                        data-file-label="#admin-bulk-file-name">
                    <div class="w-16 h-16 rounded-full bg-surface-variant flex items-center justify-center mb-md group-hover:scale-105 transition-transform duration-300">
                        <span class="material-symbols-outlined text-[32px] text-on-surface-variant">csv</span>
                    </div>
                    <h4 class="font-headline-md text-headline-md text-on-surface mb-xs">Drag &amp; Drop CSV or Excel</h4>
                    <p id="admin-bulk-file-name" data-drop-filename class="font-body-sm text-body-sm text-on-surface-variant mb-md max-w-sm" x-text="fileName || 'Supports up to 2,000 rows per batch.'"></p>
                    <span class="btn-secondary h-10 pointer-events-none relative z-0">Browse Files</span>
                </label>
                <x-input-error :messages="$errors->get('file')" />
            </div>
        </div>

        <div class="lg:col-span-4 flex flex-col gap-gutter">
            <div class="card-surface bento-shadow-sm p-lg sticky top-[104px]">
                <h3 class="font-headline-md text-headline-md text-on-surface mb-lg">Batch Summary</h3>
                <div class="space-y-4 mb-xl">
                    <div class="flex justify-between items-center py-2 border-b border-outline-variant/50">
                        <span class="font-body-md text-body-md text-on-surface-variant">Target Org</span>
                        <span class="font-label-md text-label-md text-on-surface truncate max-w-[150px]" x-text="userName || '—'"></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-outline-variant/50">
                        <span class="font-body-md text-body-md text-on-surface-variant">Template</span>
                        <span class="font-label-md text-label-md text-on-surface text-right truncate max-w-[150px]" x-text="templateName || '—'"></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-outline-variant/50">
                        <span class="font-body-md text-body-md text-on-surface-variant">File Status</span>
                        <span class="inline-flex items-center gap-1 font-label-sm text-label-sm text-on-surface-variant bg-surface-variant px-2 py-1 rounded-full">
                            <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                            <span x-text="fileName ? 'Ready' : 'Waiting'"></span>
                        </span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="font-body-md text-body-md text-on-surface-variant">File</span>
                        <span class="font-label-md text-label-md text-on-surface text-right truncate max-w-[150px]" x-text="fileName || '—'"></span>
                    </div>
                </div>

                <div class="p-md bg-surface-container-low rounded-lg mb-xl border border-outline-variant/50">
                    <div class="flex gap-sm">
                        <span class="material-symbols-outlined text-on-surface-variant text-[20px]">info</span>
                        <p class="font-body-sm text-body-sm text-on-surface-variant leading-relaxed">
                            Quota limits are suspended for admin-issued batches. Column mapping and row review happen on the next screen.
                        </p>
                    </div>
                </div>

                <button type="submit" class="btn-primary w-full">
                    <span class="material-symbols-outlined">rocket_launch</span>
                    Continue to Mapping
                </button>
            </div>
        </div>
    </form>
</x-layouts.admin-shell>
