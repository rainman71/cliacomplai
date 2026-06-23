<?php

use App\Models\AuditLog;
use App\Models\Lab;
use App\Support\CurrentLab;
use Livewire\Component;

/**
 * Per-lab profile editor (managers only). The values here pre-fill the in-app forms via the
 * "profile:<key>" default tokens in the form catalog.
 */
new class extends Component
{
    public int $labId;
    public string $labName = '';
    public string $cliaNumber = '';
    public string $address = '';
    public array $profile = []; // Lab::PROFILE_FIELDS key => value

    public function mount(Lab $lab): void
    {
        abort_unless(auth()->user()?->canManageLab($lab), 403);
        $this->labId = $lab->id;
        $this->labName = $lab->name;
        $this->cliaNumber = (string) ($lab->clia_number ?? '');
        $this->address = (string) ($lab->address ?? '');
        foreach (array_keys(Lab::PROFILE_FIELDS) as $key) {
            $this->profile[$key] = $lab->profileValue($key);
        }
        app(CurrentLab::class)->set($lab);
    }

    public function hydrate(): void
    {
        $lab = Lab::find($this->labId);
        abort_unless($lab && auth()->user()?->canManageLab($lab), 403);
        app(CurrentLab::class)->set($lab);
    }

    public function save(): void
    {
        $lab = Lab::find($this->labId);
        abort_unless($lab && auth()->user()?->canManageLab($lab), 403);

        $lab->update([
            'clia_number' => trim($this->cliaNumber) ?: null,
            'address' => trim($this->address) ?: null,
            'profile' => array_map(fn ($v) => trim((string) $v), $this->profile),
        ]);

        AuditLog::create([
            'entity_type' => 'lab', 'entity_id' => $lab->id,
            'field' => 'profile', 'old_value' => null, 'new_value' => 'updated',
            'action' => 'lab_profile_update', 'changed_by' => auth()->id(), 'changed_at' => now(),
        ]);

        session()->flash('savedProfile', 'Lab profile saved — forms will pre-fill from these values.');
    }
}; ?>

<div class="min-h-screen bg-gray-50 text-gray-900">
    <div class="mx-auto max-w-3xl px-4 py-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Lab Profile — {{ $labName }}</h1>
                <p class="text-sm text-gray-500">These details auto-fill the forms (director, supervisors, CLIA info, and more).</p>
            </div>
            <a href="{{ route('dashboard', $labId) }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to dashboard</a>
        </div>

        @if (session('savedProfile'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">{{ session('savedProfile') }}</div>
        @endif

        <form wire:submit="save" class="space-y-6">
            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-3 text-sm font-semibold text-gray-700">Laboratory</div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <label class="block text-sm"><span class="font-medium text-gray-700">CLIA #</span>
                        <input type="text" wire:model="cliaNumber" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                    <label class="block text-sm sm:col-span-2"><span class="font-medium text-gray-700">Address</span>
                        <input type="text" wire:model="address" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                </div>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-3 text-sm font-semibold text-gray-700">Profile (used to pre-fill forms)</div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach (App\Models\Lab::PROFILE_FIELDS as $key => $label)
                        <label class="block text-sm {{ $key === 'specialties' ? 'sm:col-span-2' : '' }}">
                            <span class="font-medium text-gray-700">{{ $label }}</span>
                            @if ($key === 'specialties')
                                <textarea wire:model="profile.{{ $key }}" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                            @else
                                <input type="text" wire:model="profile.{{ $key }}" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @endif
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span class="text-xs text-gray-400">Saved values pre-fill new forms; you can still edit any field while filling a form.</span>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Save profile</button>
            </div>
        </form>
    </div>
</div>
