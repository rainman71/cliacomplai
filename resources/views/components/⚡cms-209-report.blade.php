<?php

use App\Forms\FormCatalog;
use App\Models\Lab;
use App\Models\LabUser;
use App\Models\User;
use App\Services\FormService;
use App\Support\CurrentLab;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $labId;
    public string $labName = '';
    public string $formCode = 'CMS-209';

    public string $cliaNumber = '';
    public string $address = '';
    public string $reportDate = '';
    public string $preparedBy = '';
    public array $people = []; // [['user_id','name','email','positions','qualifications'], ...]
    public ?int $lastResponseId = null;

    public function mount(Lab $lab): void
    {
        abort_unless(auth()->user()?->canEditLab($lab), 403);
        $this->labId = $lab->id;
        $this->labName = $lab->name;
        $this->cliaNumber = (string) ($lab->clia_number ?? '');
        $this->address = (string) ($lab->address ?? '');
        $this->reportDate = now()->toDateString();
        $this->preparedBy = auth()->user()?->name ?? '';
        app(CurrentLab::class)->set($lab);

        $this->loadPeople();
    }

    public function hydrate(): void
    {
        $lab = Lab::find($this->labId);
        abort_unless($lab && auth()->user()?->canEditLab($lab), 403);
        app(CurrentLab::class)->set($lab);
    }

    /** Pre-fill the roster from the lab's active memberships + per-lab roles. */
    private function loadPeople(): void
    {
        $this->people = LabUser::with(['user', 'roles'])
            ->where('lab_id', $this->labId)->where('active', true)->get()
            ->map(fn ($m) => [
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'roles' => $m->roleNames(), // role keys — drive the CMS-209 position checkboxes on overlay
                'positions' => collect($m->roleNames())->map(fn ($r) => User::ROLES[$r] ?? $r)->implode(', '),
                'qualifications' => '',
            ])->values()->all();
    }

    #[Computed]
    public function def(): array
    {
        return FormCatalog::get($this->formCode);
    }

    public function submit(FormService $forms): void
    {
        abort_unless(auth()->user()?->canEditLab(Lab::find($this->labId)), 403);

        $response = $forms->submit($this->formCode, [
            'clia_number' => $this->cliaNumber,
            'address' => $this->address,
            'report_date' => $this->reportDate,
            'prepared_by' => $this->preparedBy,
            'people' => $this->people,
        ], $this->reportDate);

        $this->lastResponseId = $response->id;
        session()->flash('formSaved', 'CMS-209 Personnel Report filed — C05 (personnel) marked complete and its schedule advanced.');
    }
}; ?>

<div class="min-h-screen bg-gray-50 text-gray-900">
    <div class="mx-auto max-w-4xl px-4 py-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $this->def['title'] }}</h1>
                <p class="text-sm text-gray-500">{{ $labName }}</p>
            </div>
            <a href="{{ route('dashboard', $labId) }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to dashboard</a>
        </div>

        @if (session('formSaved'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                {{ session('formSaved') }}
                @if ($lastResponseId)
                    <a href="{{ route('forms.pdf', ['lab' => $labId, 'response' => $lastResponseId]) }}" class="ml-2 font-medium underline">Download the completed PDF</a>
                @endif
            </div>
        @endif

        <p class="mb-4 rounded-md bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">{{ $this->def['instructions'] }}</p>

        <form wire:submit="submit" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2">
                <label class="block text-sm"><span class="font-medium text-gray-700">CLIA #</span>
                    <input type="text" wire:model="cliaNumber" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm"><span class="font-medium text-gray-700">Report date</span>
                    <input type="date" wire:model="reportDate" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm sm:col-span-2"><span class="font-medium text-gray-700">Laboratory address</span>
                    <input type="text" wire:model="address" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm"><span class="font-medium text-gray-700">Prepared by</span>
                    <input type="text" wire:model="preparedBy" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">
                    Personnel — pre-filled from this lab's assigned roles
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Position(s) held</th>
                            <th class="px-4 py-2">Qualifications</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($people as $i => $p)
                            <tr class="align-top">
                                <td class="px-4 py-2">
                                    <div class="font-medium">{{ $p['name'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $p['email'] }}</div>
                                </td>
                                <td class="px-4 py-2 text-gray-700">{{ $p['positions'] ?: '—' }}</td>
                                <td class="px-4 py-2">
                                    <input type="text" wire:model="people.{{ $i }}.qualifications"
                                        class="w-full rounded-md border-gray-300 text-xs shadow-sm" placeholder="Degree, license, board cert…">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-gray-400">No active members — add people in Users &amp; Access first.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span class="text-xs text-gray-400">Positions come from each person's roles (manage them under Users &amp; Access).</span>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Complete &amp; file</button>
            </div>
        </form>
    </div>
</div>
