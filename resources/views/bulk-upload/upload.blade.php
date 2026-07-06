<x-layouts.user-shell title="Upload Data">
    <div class="max-w-3xl mx-auto">
        <div class="mb-xl text-center">
            <h1 class="font-headline-xl text-headline-xl text-on-background mb-xs">Bulk Issue Certificates</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">
                Using template: <span class="font-semibold text-on-surface">{{ $template->name }}</span>
            </p>
        </div>

        <x-bulk-upload-stepper current="Upload Data" />

        <form method="POST" action="{{ route('bulk-upload.store') }}" enctype="multipart/form-data" class="card-surface p-lg bento-shadow-sm mb-xl">
            @csrf
            <input type="hidden" name="template_id" value="{{ $template->id }}">

            <div class="flex justify-between items-center mb-md">
                <h2 class="font-headline-md text-headline-md text-on-surface">Upload CSV Data</h2>
                <a href="{{ asset('samples/bulk-upload-sample.csv') }}" download class="flex items-center gap-xs text-primary font-label-sm text-label-sm hover:underline">
                    <span class="material-symbols-outlined text-[16px]">download</span>
                    Download Sample CSV
                </a>
            </div>

            <label data-drop-zone class="block border-2 border-dashed border-primary-container bg-surface-container-lowest rounded-lg p-2xl flex flex-col items-center justify-center text-center cursor-pointer hover:bg-surface-container-low transition-colors group">
                <input type="file" name="file" accept=".csv,.xlsx,.xls" required class="hidden" data-file-label="#bulk-file-name">
                <div class="w-16 h-16 bg-primary-fixed text-on-primary-fixed rounded-full flex items-center justify-center mb-md group-hover:scale-110 transition-transform">
                    <span class="material-symbols-outlined text-[32px]">upload_file</span>
                </div>
                <h3 class="font-headline-md text-headline-md text-on-surface mb-xs">Drag &amp; drop CSV or <span class="text-primary underline">click to browse</span></h3>
                <p class="font-body-sm text-body-sm text-on-surface-variant max-w-sm">Supported formats: .csv, .xlsx. Maximum file size 10MB. First row must be column headers.</p>
                <p id="bulk-file-name" data-drop-filename class="font-label-sm text-label-sm text-on-surface mt-md"></p>
            </label>
            <x-input-error :messages="$errors->get('file')" />

            <div class="mt-xl flex justify-between items-center">
                <a href="{{ route('bulk-upload.select-template') }}" class="btn-secondary">Back</a>
                <x-primary-button class="btn-primary">
                    Continue to Mapping
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </x-primary-button>
            </div>
        </form>
    </div>
</x-layouts.user-shell>
