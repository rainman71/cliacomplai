<?php

use App\Forms\FormCatalog;
use App\Models\Lab;
use App\Services\FormService;
use App\Support\CurrentLab;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $labId;
    public string $labName = '';
    public string $formCode = 'CMP-172';

    public string $approver = '';
    public string $approvalDate = '';
    public array $labs = []; // [['name','clia_number','address','tests'], ...]
    public ?int $lastResponseId = null;

    public function mount(Lab $lab): void
    {
        abort_unless(auth()->user()?->canEditLab($lab), 403);
        $this->labId = $lab->id;
        $this->labName = $lab->name;
        $this->approver = $lab->profileValue('director_name') ?: (auth()->user()?->name ?? '');
        $this->approvalDate = now()->toDateString();
        app(CurrentLab::class)->set($lab);

        $this->labs = [$this->blankLab()];
    }

    public function hydrate(): void
    {
        $lab = Lab::find($this->labId);
        abort_unless($lab && auth()->user()?->canEditLab($lab), 403);
        app(CurrentLab::class)->set($lab);
    }

    private function blankLab(): array
    {
        return ['name' => '', 'clia_number' => '', 'address' => '', 'tests' => ''];
    }

    public function addLab(): void
    {
        $this->labs[] = $this->blankLab();
    }

    public function removeLab(int $i): void
    {
        unset($this->labs[$i]);
        $this->labs = array_values($this->labs);
        if (empty($this->labs)) {
            $this->labs = [$this->blankLab()];
        }
    }

    #[Computed]
    public function def(): array
    {
        return FormCatalog::get($this->formCode);
    }

    public function submit(FormService $forms): void
    {
        abort_unless(auth()->user()?->canEditLab(Lab::find($this->labId)), 403);

        // Drop empty rows so a blank trailing line doesn't get filed.
        $labs = array_values(array_filter($this->labs, fn ($l) => trim($l['name'] ?? '') !== ''));

        $response = $forms->submit($this->formCode, [
            'reference_labs' => $labs,
            'approver' => $this->approver,
            'approval_date' => $this->approvalDate,
        ], $this->approvalDate);

        $this->lastResponseId = $response->id;
        session()->flash('formSaved', 'Reference Laboratory Approval filed — C10 marked complete and its schedule advanced.');
    }
}; ?>

<div class="min-h-screen bg-gray-50 text-gray-900">
    <div class="mx-auto max-w-3xl px-4 py-6">
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
            <div class="space-y-3">
                @foreach ($labs as $i => $l)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-semibold uppercase tracking-wide text-gray-400">Reference Lab #{{ $i + 1 }}</span>
                            <button type="button" wire:click="removeLab({{ $i }})" class="text-xs font-medium text-red-600 hover:underline">Remove</button>
                        </div>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <label class="block text-sm"><span class="font-medium text-gray-700">Reference lab name</span>
                                <input type="text" wire:model="labs.{{ $i }}.name" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                            <label class="block text-sm"><span class="font-medium text-gray-700">CLIA #</span>
                                <input type="text" wire:model="labs.{{ $i }}.clia_number" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                            <label class="block text-sm sm:col-span-2"><span class="font-medium text-gray-700">Address</span>
                                <input type="text" wire:model="labs.{{ $i }}.address" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                            <label class="block text-sm sm:col-span-2"><span class="font-medium text-gray-700">Tests referred</span>
                                <input type="text" wire:model="labs.{{ $i }}.tests" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="e.g. confirmatory LCMS panel"></label>
                        </div>
                    </div>
                @endforeach
                <button type="button" wire:click="addLab" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">+ Add another reference lab</button>
            </div>

            <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2">
                <label class="block text-sm"><span class="font-medium text-gray-700">Approved by (Laboratory Director)</span>
                    <input type="text" wire:model="approver" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
                <label class="block text-sm"><span class="font-medium text-gray-700">Approval date</span>
                    <input type="date" wire:model="approvalDate" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></label>
            </div>

            <div class="flex items-center justify-end gap-3">
                <span class="text-xs text-gray-400">Filing this marks C10 (reference lab approval) complete and advances its annual due date.</span>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Complete &amp; file</button>
            </div>
        </form>
    </div>
</div>
