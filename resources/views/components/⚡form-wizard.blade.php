<?php

use App\Forms\FormCatalog;
use App\Models\Lab;
use App\Services\FormService;
use App\Support\CurrentLab;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Generic, catalog-driven form wizard. Renders any FormCatalog entry whose 'component' is
 * 'form-wizard': a set of typed `fields` (text/date/textarea/select) and/or checklist `sections`
 * (yes/no/na + note). Submitting stores the answers and completes the linked obligation.
 */
new class extends Component
{
    public int $labId;
    public string $labName = '';
    public string $formCode = '';

    public array $values = [];   // field key => value
    public array $items = [];    // checklist item key => ['answer','note']
    public ?int $lastResponseId = null;

    public function mount(Lab $lab, string $code): void
    {
        abort_unless(auth()->user()?->canEditLab($lab), 403);
        $def = FormCatalog::get($code);
        abort_unless(($def['component'] ?? null) === 'form-wizard', 404);

        $this->labId = $lab->id;
        $this->labName = $lab->name;
        $this->formCode = $code;
        app(CurrentLab::class)->set($lab);

        foreach (array_merge($def['fields'] ?? [], $def['footer_fields'] ?? []) as $f) {
            $this->values[$f['key']] = $this->resolveDefault($lab, (string) ($f['default'] ?? ''));
        }
        foreach ($def['sections'] ?? [] as $s) {
            foreach ($s['items'] as $it) {
                $this->items[$it['key']] = ['answer' => 'yes', 'note' => ''];
            }
        }
    }

    public function hydrate(): void
    {
        $lab = Lab::find($this->labId);
        abort_unless($lab && auth()->user()?->canEditLab($lab), 403);
        app(CurrentLab::class)->set($lab);
    }

    /** Resolve a field's default token against today / the lab record / the lab profile. */
    private function resolveDefault(Lab $lab, string $token): string
    {
        if ($token === 'today') {
            return now()->toDateString();
        }
        if ($token === 'lab_name') {
            return (string) $lab->name;
        }
        if ($token === 'lab_clia') {
            return (string) ($lab->clia_number ?? '');
        }
        if ($token === 'lab_address') {
            return (string) ($lab->address ?? '');
        }
        if (str_starts_with($token, 'profile:')) {
            return $lab->profileValue(substr($token, 8));
        }

        return $token;
    }

    #[Computed]
    public function def(): array
    {
        return FormCatalog::get($this->formCode);
    }

    public function submit(FormService $forms): void
    {
        abort_unless(auth()->user()?->canEditLab(Lab::find($this->labId)), 403);

        $def = FormCatalog::get($this->formCode);
        $dateField = $def['date_field'] ?? null;
        $completedDate = $dateField ? ($this->values[$dateField] ?? null) : null;

        $response = $forms->submit($this->formCode, [
            'fields' => $this->values,
            'items' => $this->items,
        ], $completedDate ?: null);

        $this->lastResponseId = $response->id;
        session()->flash('formSaved', $def['title'] . ' filed — the linked obligation is marked complete.');
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

        @if (! empty($this->def['instructions']))
            <p class="mb-4 rounded-md bg-amber-50 p-3 text-xs text-amber-800 ring-1 ring-amber-200">{{ $this->def['instructions'] }}</p>
        @endif

        <form wire:submit="submit" class="space-y-6">
            @if (! empty($this->def['fields']))
                <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2">
                    @foreach ($this->def['fields'] as $f)
                        @php $type = $f['type'] ?? 'text'; @endphp
                        <label class="block text-sm {{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
                            <span class="font-medium text-gray-700">{{ $f['label'] }}</span>
                            @if ($type === 'textarea')
                                <textarea wire:model="values.{{ $f['key'] }}" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                            @elseif ($type === 'select')
                                <select wire:model="values.{{ $f['key'] }}" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">—</option>
                                    @foreach ($f['options'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $type === 'date' ? 'date' : 'text' }}" wire:model="values.{{ $f['key'] }}"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif

            @foreach ($this->def['sections'] ?? [] as $section)
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-100 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-700">{{ $section['heading'] }}</div>
                    <div class="divide-y divide-gray-100">
                        @foreach ($section['items'] as $item)
                            <div class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center">
                                <div class="flex-1 text-sm text-gray-800">{{ $item['label'] }}</div>
                                <div class="flex items-center gap-2">
                                    <select wire:model="items.{{ $item['key'] }}.answer" class="rounded-md border-gray-300 text-sm shadow-sm">
                                        <option value="yes">Yes</option>
                                        <option value="no">No</option>
                                        <option value="na">N/A</option>
                                    </select>
                                    <input type="text" wire:model.blur="items.{{ $item['key'] }}.note"
                                           class="w-44 rounded-md border-gray-300 text-xs shadow-sm" placeholder="Comment (optional)">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            @if (! empty($this->def['footer_fields']))
                <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm sm:grid-cols-2">
                    @foreach ($this->def['footer_fields'] as $f)
                        @php $type = $f['type'] ?? 'text'; @endphp
                        <label class="block text-sm {{ $type === 'textarea' ? 'sm:col-span-2' : '' }}">
                            <span class="font-medium text-gray-700">{{ $f['label'] }}</span>
                            @if ($type === 'textarea')
                                <textarea wire:model="values.{{ $f['key'] }}" rows="2" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm"></textarea>
                            @elseif ($type === 'select')
                                <select wire:model="values.{{ $f['key'] }}" class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                                    <option value="">—</option>
                                    @foreach ($f['options'] as $opt)
                                        <option value="{{ $opt }}">{{ $opt }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $type === 'date' ? 'date' : 'text' }}" wire:model="values.{{ $f['key'] }}"
                                       class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm">
                            @endif
                        </label>
                    @endforeach
                </div>
            @endif

            <div class="flex items-center justify-end gap-3">
                <span class="text-xs text-gray-400">Filing this records a completion against the linked obligation and advances its due date.</span>
                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Complete &amp; file</button>
            </div>
        </form>
    </div>
</div>
