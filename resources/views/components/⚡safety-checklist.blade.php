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
    public string $formCode = 'CMP-173';

    public array $items = [];            // key => ['answer' => 'yes'|'no', 'note' => '']
    public string $completedBy = '';
    public string $techSupervisor = '';
    public string $reviewDate = '';
    public ?int $lastResponseId = null;

    public function mount(Lab $lab): void
    {
        abort_unless(auth()->user()?->canEditLab($lab), 403);
        $this->labId = $lab->id;
        $this->labName = $lab->name;
        app(CurrentLab::class)->set($lab);

        $this->reviewDate = now()->toDateString();
        $this->techSupervisor = $lab->profileValue('tech_supervisor');
        foreach (FormCatalog::itemKeys($this->formCode) as $key) {
            $this->items[$key] = ['answer' => 'yes', 'note' => ''];
        }
    }

    public function hydrate(): void
    {
        $lab = Lab::find($this->labId);
        abort_unless($lab && auth()->user()?->canEditLab($lab), 403);
        app(CurrentLab::class)->set($lab);
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
            'items' => $this->items,
            'completed_by' => $this->completedBy,
            'tech_supervisor' => $this->techSupervisor,
            'review_date' => $this->reviewDate,
        ], $this->reviewDate);

        $this->lastResponseId = $response->id;
        session()->flash('formSaved', $this->def['title'] . ' filed — the obligation is now marked complete and the schedule advanced.');
    }
}; ?>

<div class="min-h-screen bg-gray-50 text-gray-900">
    <div class="mx-auto max-w-3xl px-4 py-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">{{ $this->def['title'] }} <span class="text-gray-400">({{ $formCode }})</span></h1>
                <p class="text-sm text-gray-500">{{ $labName }}</p>
            </div>
            <a href="{{ route('dashboard', $labId) }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to dashboard</a>
        </div>

        @if (session('formSaved'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">
                {{ session('formSaved') }}
                @if ($lastResponseId)
                    <a href="{{ route('forms.pdf', ['lab' => $labId, 'response' => $lastResponseId]) }}"
                       class="ml-2 font-medium underline">Download the completed PDF</a>
                @endif
            </div>
        @endif

        <p class="mb-4 rounded-md bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">{{ $this->def['instructions'] }}</p>

        <form wire:submit="submit" class="space-y-6">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="font-medium text-gray-700">Review date</span>
                    <input type="date" wire:model="reviewDate" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-gray-700">Completed by</span>
                    <input type="text" wire:model="completedBy" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="Name">
                </label>
                <label class="block text-sm">
                    <span class="font-medium text-gray-700">Technical Supervisor</span>
                    <input type="text" wire:model="techSupervisor" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm" placeholder="Name">
                </label>
            </div>

            @foreach ($this->def['sections'] as $section)
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">{{ $section['heading'] }}</div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($section['items'] as $item)
                            @php $key = $item['key']; @endphp
                            <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                <div class="flex-1 text-sm text-gray-800">{{ $item['label'] }}</div>
                                <div class="flex items-center gap-2">
                                    <select wire:model="items.{{ $key }}.answer" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                        <option value="na">N/A</option>
                                    </select>
                                    <input type="text" wire:model.blur="items.{{ $key }}.note"
                                           class="w-48 rounded-md border-gray-300 text-xs shadow-sm" placeholder="Action taken (optional)">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex items-center justify-end gap-3">
                <span class="text-xs text-gray-400">Submitting files this checklist as the obligation's evidence and advances its due date.</span>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                    Complete &amp; file
                </button>
            </div>
        </form>
    </div>
</div>
