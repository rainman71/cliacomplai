<?php

use App\Models\AuditLog;
use App\Models\Lab;
use App\Services\LabProvisioner;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $newName = '';
    public string $newClia = '';
    public string $newTimezone = 'America/New_York';
    public string $newDrive = '';

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
        $this->reset('newName', 'newClia', 'newDrive');
        $this->newTimezone = 'America/New_York';
        session()->flash('labMsg', "Created \"{$lab->name}\" with 13 obligations.");
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

        <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-3 text-sm font-semibold text-gray-700">New lab</div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
                <input wire:model="newName" placeholder="Lab name *" class="rounded border-gray-300 text-sm shadow-sm" />
                <input wire:model="newClia" placeholder="CLIA #" class="rounded border-gray-300 text-sm shadow-sm" />
                <input wire:model="newTimezone" placeholder="Timezone" class="rounded border-gray-300 text-sm shadow-sm" />
                <input wire:model="newDrive" placeholder="Drive root folder id" class="rounded border-gray-300 text-sm shadow-sm" />
            </div>
            @error('newName') <div class="mt-1 text-xs text-red-600">{{ $message }}</div> @enderror
            <button wire:click="createLab"
                class="mt-3 rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">
                Create lab (clones the 13 obligations)
            </button>
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
