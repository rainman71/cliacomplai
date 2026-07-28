<?php

use App\Models\AuditLog;
use App\Models\Lab;
use App\Services\Drive\DriveClient;
use App\Services\LabProvisioner;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** Add-a-lab wizard state. */
    public int $step = 1;

    public string $newName = '';
    public string $newClia = '';
    public string $newTimezone = 'America/New_York';
    public string $newDrive = '';

    /** Live "Test connection" result: idle | ok | warn | fail. */
    public string $driveCheckState = 'idle';
    public string $driveCheckMsg = '';

    /** Inline-editable fields keyed by lab id. */
    public array $edits = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        foreach (Lab::orderBy('name')->get() as $lab) {
            $this->edits[$lab->id] = ['clia' => $lab->clia_number, 'drive' => $lab->drive_root_folder_id];
        }
    }

    #[Computed]
    public function labs()
    {
        return Lab::withCount(['obligations', 'memberships'])->orderBy('name')->get();
    }

    /**
     * The service-account address the customer must share their Shared Drive with. Read straight
     * from the key file so it's always correct for whichever key this server is running.
     */
    #[Computed]
    public function serviceAccountEmail(): ?string
    {
        $path = config('services.google.drive_credentials');
        if (! $path || ! is_file($path)) {
            return null;
        }
        $json = json_decode((string) file_get_contents($path), true);

        return is_array($json) ? ($json['client_email'] ?? null) : null;
    }

    public function nextStep(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'newName' => 'required|string|max:160',
                'newClia' => 'nullable|string|max:20',
                'newTimezone' => 'required|string|max:64',
            ]);
        }
        $this->step = min(3, $this->step + 1);
    }

    public function prevStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /** Reset the connection check whenever the pasted id changes. */
    public function updatedNewDrive(): void
    {
        $this->driveCheckState = 'idle';
        $this->driveCheckMsg = '';
    }

    /** Probe the pasted id with the service account and report exactly what it can (or can't) see. */
    public function verifyDrive(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $this->validate(['newDrive' => 'required|string|max:120']);
        $id = trim($this->newDrive);
        $sa = $this->serviceAccountEmail ?? 'the service account';

        try {
            $client = app(DriveClient::class);
        } catch (\Throwable $e) {
            $this->driveCheckState = 'warn';
            $this->driveCheckMsg = 'Drive isn’t configured on this server, so the connection can’t be tested here. '
                .'You can still save the ID and verify later with `php artisan compliance:drive-check`.';

            return;
        }

        $meta = $client->describeFolder($id);

        if (! $meta) {
            $this->driveCheckState = 'fail';
            $this->driveCheckMsg = "No access to that ID. Check the ID is the Shared Drive's, and that the drive is shared with {$sa} as Content manager.";

            return;
        }

        if (empty($meta['driveId'])) {
            $this->driveCheckState = 'warn';
            $this->driveCheckMsg = "Connected to “{$meta['name']}”, but this is a regular My Drive folder, not a Shared Drive. "
                .'Files the app creates here are owned by the service account and can hit storage limits. Create a Shared Drive instead.';

            return;
        }

        $this->driveCheckState = 'ok';
        $this->driveCheckMsg = "Connected to Shared Drive “{$meta['name']}”. The service account can read and write here.";
    }

    public function createLab(): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $data = $this->validate([
            'newName' => 'required|string|max:160',
            'newClia' => 'nullable|string|max:20',
            'newTimezone' => 'required|string|max:64',
            'newDrive' => 'nullable|string|max:120',
        ]);

        $lab = app(LabProvisioner::class)->create([
            'name' => $data['newName'],
            'clia_number' => $data['newClia'] ?: null,
            'timezone' => $data['newTimezone'],
            'drive_root_folder_id' => $data['newDrive'] ?: null,
        ]);

        $this->edits[$lab->id] = ['clia' => $lab->clia_number, 'drive' => $lab->drive_root_folder_id];
        $this->audit($lab->id, 'lab', '', 'created', 'lab_create');
        $count = $lab->obligations()->count();
        $this->reset('newName', 'newClia', 'newDrive', 'driveCheckState', 'driveCheckMsg');
        $this->newTimezone = 'America/New_York';
        $this->step = 1;
        session()->flash('labMsg', "Created \"{$lab->name}\" with {$count} obligations.");
    }

    public function updated(string $name, $value): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        if (! str_starts_with($name, 'edits.')) {
            return;
        }
        [, $id, $field] = explode('.', $name, 3);
        $col = ['clia' => 'clia_number', 'drive' => 'drive_root_folder_id'][$field] ?? null;
        $lab = Lab::find($id);
        if (! $col || ! $lab) {
            return;
        }
        $new = $value === '' ? null : $value;
        if ((string) $lab->{$col} === (string) $new) {
            return;
        }
        $old = $lab->{$col};
        $lab->update([$col => $new]);
        $this->audit($lab->id, $col, (string) $old, (string) $new, 'lab_update');
    }

    public function toggleActive(int $labId): void
    {
        abort_unless(auth()->user()?->isSuperAdmin(), 403);
        $lab = Lab::find($labId);
        if (! $lab) {
            return;
        }
        $lab->update(['active' => ! $lab->active]);
        $this->audit($lab->id, 'active', null, $lab->active ? '1' : '0', $lab->active ? 'lab_activate' : 'lab_deactivate');
    }

    private function audit(int $labId, string $field, ?string $old, ?string $new, string $action): void
    {
        AuditLog::create([
            'lab_id' => $labId, 'entity_type' => 'lab', 'entity_id' => $labId,
            'field' => $field, 'old_value' => (string) $old, 'new_value' => (string) $new,
            'action' => $action, 'changed_by' => auth()->id(), 'changed_at' => now(),
        ]);
    }
}; ?>

<div class="min-h-screen bg-gray-50 text-gray-900">
    <div class="mx-auto max-w-5xl px-4 py-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Manage Labs</h1>
                <p class="text-sm text-gray-500">Create labs, set CLIA #, Drive root, and activation. Super admin only.</p>
            </div>
            <a href="{{ route('portfolio') }}" class="text-sm font-medium text-indigo-600 hover:underline">← My Labs</a>
        </div>

        @if (session('labMsg'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">{{ session('labMsg') }}</div>
        @endif

        <div class="mb-6 rounded-xl border border-gray-200 bg-white shadow-sm">
            {{-- Stepper header --}}
            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3">
                @foreach (['Lab details', 'Create the Shared Drive', 'Connect & verify'] as $i => $label)
                    @php $n = $i + 1; @endphp
                    <div class="flex items-center gap-2">
                        <span @class([
                            'flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold',
                            'bg-indigo-600 text-white' => $step === $n,
                            'bg-green-100 text-green-700' => $step > $n,
                            'bg-gray-100 text-gray-500' => $step < $n,
                        ])>{{ $step > $n ? '✓' : $n }}</span>
                        <span @class(['text-xs font-medium', 'text-gray-900' => $step === $n, 'text-gray-500' => $step !== $n])>{{ $label }}</span>
                    </div>
                    @if (! $loop->last) <div class="h-px w-6 bg-gray-200"></div> @endif
                @endforeach
            </div>

            <div class="p-4">
                {{-- Step 1: lab details --}}
                @if ($step === 1)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Lab name *</label>
                            <input wire:model="newName" placeholder="e.g. Acme Toxicology" class="w-full rounded border-gray-300 text-sm shadow-sm" />
                            @error('newName') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">CLIA #</label>
                            <input wire:model="newClia" placeholder="34D1234567" class="w-full rounded border-gray-300 text-sm shadow-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-gray-600">Timezone</label>
                            <input wire:model="newTimezone" class="w-full rounded border-gray-300 text-sm shadow-sm" />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button wire:click="nextStep" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">Next: Drive setup →</button>
                    </div>
                @endif

                {{-- Step 2: create the Shared Drive & share it with the SA --}}
                @if ($step === 2)
                    <div class="rounded-lg bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">
                        <strong>It must be a Shared Drive — not a folder in “My Drive.”</strong>
                        A Shared Drive is owned by the lab’s Google&nbsp;Workspace, so the files the app creates count against
                        <em>their</em> storage and survive staff turnover. A personal My-Drive folder will hit storage limits and break.
                        (Shared Drives require Google Workspace, not a personal @gmail.com account.)
                    </div>

                    <ol class="mt-4 space-y-3 text-sm text-gray-700">
                        <li class="flex gap-2">
                            <span class="font-semibold text-indigo-600">1.</span>
                            <span>In Google Drive, click <strong>Shared drives</strong> in the left sidebar → <strong>+ New</strong>. Name it something like <em>“{{ $newName ?: 'Acme' }} — CLIA Compliance.”</em></span>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-indigo-600">2.</span>
                            <span>Open that Shared Drive → <strong>Manage members</strong>. Add the address below as a <strong>Content manager</strong> (uncheck “Notify people”), then Send.</span>
                        </li>
                        <li>
                            <div class="ml-5 rounded-lg border border-gray-200 bg-gray-50 p-3" x-data="{ copied: false }">
                                <div class="text-xs font-medium text-gray-500">Share with this service account:</div>
                                @if ($this->serviceAccountEmail)
                                    <div class="mt-1 flex items-center gap-2">
                                        <code class="flex-1 truncate rounded bg-white px-2 py-1 text-xs text-gray-800 ring-1 ring-gray-200">{{ $this->serviceAccountEmail }}</code>
                                        <button type="button"
                                            x-on:click="navigator.clipboard.writeText('{{ $this->serviceAccountEmail }}'); copied = true; setTimeout(() => copied = false, 1500)"
                                            class="rounded-md bg-indigo-600 px-2.5 py-1 text-xs font-medium text-white hover:bg-indigo-500">
                                            <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                                        </button>
                                    </div>
                                @else
                                    <div class="mt-1 text-xs text-red-600">No service-account key is configured on this server. Set <code>GOOGLE_DRIVE_CREDENTIALS</code> and run <code>php artisan config:clear</code>, or share with the SA email from your key file.</div>
                                @endif
                            </div>
                        </li>
                        <li class="flex gap-2">
                            <span class="font-semibold text-indigo-600">3.</span>
                            <span>Open the Shared Drive and copy its ID from the browser URL — the part after <code>/drive/folders/</code>, e.g. <code>drive.google.com/drive/folders/<strong>0AH1a…XYZ</strong></code>.</span>
                        </li>
                    </ol>

                    <div class="mt-4 flex justify-between">
                        <button wire:click="prevStep" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900">← Back</button>
                        <button wire:click="nextStep" class="rounded-md bg-indigo-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">Next: paste the ID →</button>
                    </div>
                @endif

                {{-- Step 3: paste the id, verify, finish --}}
                @if ($step === 3)
                    <label class="mb-1 block text-xs font-medium text-gray-600">Shared Drive ID</label>
                    <div class="flex items-center gap-2">
                        <input wire:model.live.debounce.400ms="newDrive" placeholder="0AH1a…XYZ" class="flex-1 rounded border-gray-300 text-sm shadow-sm" />
                        <button wire:click="verifyDrive" wire:loading.attr="disabled" wire:target="verifyDrive"
                            class="rounded-md bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50">
                            <span wire:loading.remove wire:target="verifyDrive">Test connection</span>
                            <span wire:loading wire:target="verifyDrive">Testing…</span>
                        </button>
                    </div>
                    @error('newDrive') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror

                    @if ($driveCheckState !== 'idle')
                        <div @class([
                            'mt-3 rounded-md p-3 text-sm ring-1',
                            'bg-green-50 text-green-800 ring-green-200' => $driveCheckState === 'ok',
                            'bg-amber-50 text-amber-900 ring-amber-200' => $driveCheckState === 'warn',
                            'bg-red-50 text-red-700 ring-red-200' => $driveCheckState === 'fail',
                        ])>
                            {{ $driveCheckState === 'ok' ? '✓ ' : ($driveCheckState === 'fail' ? '✗ ' : '⚠ ') }}{{ $driveCheckMsg }}
                        </div>
                    @endif

                    <p class="mt-3 text-xs text-gray-400">You can create the lab now and set the Drive ID later — but auto-filing of signed PDFs stays off until a verified Shared Drive is connected.</p>

                    <div class="mt-4 flex justify-between">
                        <button wire:click="prevStep" class="rounded-md px-3 py-1.5 text-sm font-medium text-gray-600 hover:text-gray-900">← Back</button>
                        <button wire:click="createLab" class="rounded-md bg-green-600 px-4 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-green-500">
                            Create lab (clones the CLIA obligations)
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Lab</th>
                        <th class="px-4 py-3">CLIA #</th>
                        <th class="px-4 py-3">Drive root folder id</th>
                        <th class="px-4 py-3 text-center">Obligations</th>
                        <th class="px-4 py-3 text-center">Members</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($this->labs as $lab)
                        <tr class="{{ $lab->active ? '' : 'bg-gray-50' }}">
                            <td class="px-4 py-2 font-medium">{{ $lab->name }}</td>
                            <td class="px-4 py-2">
                                <input wire:model.blur="edits.{{ $lab->id }}.clia" class="w-28 rounded border-gray-300 text-xs shadow-sm" />
                            </td>
                            <td class="px-4 py-2">
                                <input wire:model.blur="edits.{{ $lab->id }}.drive" placeholder="—" class="w-56 rounded border-gray-300 text-xs shadow-sm" />
                            </td>
                            <td class="px-4 py-2 text-center text-gray-600">{{ $lab->obligations_count }}</td>
                            <td class="px-4 py-2 text-center text-gray-600">{{ $lab->memberships_count }}</td>
                            <td class="px-4 py-2">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $lab->active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                                    {{ $lab->active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right">
                                <a href="{{ route('dashboard', $lab) }}" class="text-xs font-medium text-indigo-600 hover:underline">Open</a>
                                <button wire:click="toggleActive({{ $lab->id }})" class="ml-3 text-xs font-medium {{ $lab->active ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                    {{ $lab->active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-400">The Drive root folder id is the Drive folder shared with the service account; signed PDFs file under it per lab.</p>
    </div>
</div>
