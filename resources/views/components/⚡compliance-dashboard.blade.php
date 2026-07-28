<?php

use App\Models\AuditLog;
use App\Models\Lab;
use App\Models\Obligation;
use App\Models\SignatureRequest;
use App\Models\SignatureRequestSigner;
use App\Services\ComplianceStatusService;
use App\Services\SignatureService;
use App\Support\CurrentLab;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $labId;
    public string $labName = '';
    public string $tab = 'overview';

    /** Editable values keyed by obligation id: last_completed, document_link, signature_status, notes. */
    public array $form = [];

    public bool $canEdit = false;
    public bool $canManageUsers = false;

    /** Table controls (shared across the obligation tabs). */
    public string $frequencyFilter = '';   // '' = all; otherwise a freqKey ('1','6','12',… or 'event')
    public string $sortBy = 'next_due';     // 'next_due' | 'code'

    public function mount(Lab $lab): void
    {
        $this->labId = $lab->id;
        $this->applyLabContext();

        foreach (Obligation::orderBy('code')->get() as $o) {
            $this->form[$o->id] = [
                'last_completed' => optional($o->last_completed)->toDateString(),
                'document_link' => $o->document_link,
                'signature_status' => $o->signature_status,
                'notes' => $o->notes,
            ];
        }
    }

    /** Re-establish lab scope on every subsequent Livewire request (AJAX bypasses route middleware). */
    public function hydrate(): void
    {
        $this->applyLabContext();
    }

    private function applyLabContext(): void
    {
        $lab = Lab::find($this->labId);
        app(CurrentLab::class)->set($lab);
        $this->labName = $lab?->name ?? '';
        $this->canEdit = $lab && auth()->user()?->canEditLab($lab);
        $this->canManageUsers = $lab && auth()->user()?->canManageLab($lab);
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
    }

    /** Auto-save a single edited cell and write an audit-log entry. */
    public function updated(string $name, $value): void
    {
        if (! $this->canEdit) {
            return; // read-only users cannot persist edits
        }
        if (! str_starts_with($name, 'form.')) {
            return;
        }

        [, $id, $field] = explode('.', $name, 3);
        $allowed = ['last_completed', 'document_link', 'signature_status', 'notes'];
        if (! in_array($field, $allowed, true)) {
            return;
        }

        $o = Obligation::find($id);
        if (! $o) {
            return;
        }

        $old = $o->{$field};
        $oldStr = $old instanceof \DateTimeInterface ? $old->format('Y-m-d') : (string) $old;
        $new = ($value === '' || $value === null) ? null : $value;

        if ($oldStr === (string) $new) {
            return;
        }

        $o->{$field} = $new;

        // Re-cache next_due whenever the baseline date changes.
        if ($field === 'last_completed') {
            $nextDue = app(ComplianceStatusService::class)
                ->nextDue($new ? CarbonImmutable::parse($new) : null, $o->interval_months);
            $o->next_due = $nextDue?->toDateString();
        }

        $o->save();

        AuditLog::create([
            'entity_type' => 'obligation',
            'entity_id' => $o->id,
            'field' => $field,
            'old_value' => $oldStr,
            'new_value' => (string) $new,
            'action' => 'update',
            'changed_by' => auth()->id(),
            'changed_at' => now(),
        ]);
    }

    /** All obligations with derived status, computed live through the single source of truth. */
    #[Computed]
    public function rows()
    {
        $svc = app(ComplianceStatusService::class);

        return Obligation::with('requiredSigners')->orderBy('code')->get()->map(function ($o) use ($svc) {
            $d = $svc->for($o);

            return [
                'o' => $o,
                'next_due' => $d['next_due'],
                'days' => $d['days_until_due'],
                'status' => $d['status'],
                'stale' => $d['stale'],
                'days_since_verified' => $d['days_since_verified'],
            ];
        });
    }

    #[Computed]
    public function counts(): array
    {
        $c = ['overdue' => 0, 'due_30' => 0, 'due_60' => 0, 'on_track' => 0, 'set_dates' => 0];
        foreach ($this->rows as $r) {
            $c[$r['status']->value]++;
        }

        return $c;
    }

    /** Map an interval (months) to a stable filter key. Null interval = event-driven. */
    public function freqKey(?int $interval): string
    {
        return $interval === null ? 'event' : (string) $interval;
    }

    /** Human label for an interval (months). */
    public function freqLabel(?int $interval): string
    {
        if ($interval === null) {
            return 'Event-driven';
        }

        return [1 => 'Monthly', 3 => 'Quarterly', 4 => 'Every 4 months', 6 => 'Semiannual', 12 => 'Annual', 24 => 'Biennial'][$interval]
            ?? "Every {$interval} mo";
    }

    /** Distinct frequencies present in the register, as [key => label], soonest cadence first. */
    #[Computed]
    public function frequencyOptions(): array
    {
        return $this->rows
            ->map(fn ($r) => $r['o']->interval_months)
            ->unique()
            ->sortBy(fn ($i) => $i ?? PHP_INT_MAX)   // event-driven (null) sorts last
            ->mapWithKeys(fn ($i) => [$this->freqKey($i) => $this->freqLabel($i)])
            ->all();
    }

    /** Apply the shared frequency filter + sort to an obligation-row collection. */
    private function applyFilterSort($rows)
    {
        if ($this->frequencyFilter !== '') {
            $rows = $rows->filter(fn ($r) => $this->freqKey($r['o']->interval_months) === $this->frequencyFilter);
        }

        $rows = $this->sortBy === 'code'
            ? $rows->sortBy(fn ($r) => $r['o']->code)
            : $rows->sortBy(fn ($r) => $r['next_due']?->getTimestamp() ?? PHP_INT_MAX); // next due asc, undated last

        return $rows->values();
    }

    /** Overdue first, then due within 30 days, soonest first (honors the filter/sort controls). */
    #[Computed]
    public function dueSoon()
    {
        return $this->applyFilterSort(
            $this->rows->filter(fn ($r) => in_array($r['status']->value, ['overdue', 'due_30'], true))
        );
    }

    #[Computed]
    public function overdue()
    {
        return $this->applyFilterSort(
            $this->rows->filter(fn ($r) => $r['status']->value === 'overdue')
        );
    }

    /** Full register, filtered + sorted by the shared controls (default: next due). */
    #[Computed]
    public function registerRows()
    {
        return $this->applyFilterSort($this->rows);
    }

    /** Completeness rows with the same frequency filter + sort applied. */
    #[Computed]
    public function completenessRowsFiltered()
    {
        $rows = $this->completenessRows;

        if ($this->frequencyFilter !== '') {
            $rows = $rows->filter(fn ($r) => $this->freqKey($r['interval_months'] ?? null) === $this->frequencyFilter);
        }

        $rows = $this->sortBy === 'code'
            ? $rows->sortBy('code')
            : $rows->sortBy(fn ($r) => $r['next_due'] ? strtotime($r['next_due']) : PHP_INT_MAX);

        return $rows->values();
    }

    #[Computed]
    public function completenessRows()
    {
        return app(\App\Services\CompletenessReportService::class)->rows();
    }

    #[Computed]
    public function completenessSummary(): array
    {
        return app(\App\Services\CompletenessReportService::class)->summary();
    }

    /** Recent audit-log entries with the obligation code resolved for display. */
    #[Computed]
    public function activity()
    {
        $codes = \App\Models\Obligation::pluck('code', 'id');

        return \App\Models\AuditLog::with('changer')->latest('id')->limit(100)->get()->map(fn ($a) => [
            'when' => $a->changed_at,
            'entity' => $a->entity_type === 'obligation'
                ? ($codes[$a->entity_id] ?? '#' . $a->entity_id)
                : $a->entity_type . '#' . $a->entity_id,
            'field' => $a->field,
            'old' => $a->old_value,
            'new' => $a->new_value,
            'action' => $a->action,
            'by' => $a->changer?->name,
        ]);
    }

    /** Open signature requests, oldest-pending first (for escalation prioritization). */
    #[Computed]
    public function awaitingRequests()
    {
        return SignatureRequest::with(['obligation', 'signers'])
            ->whereIn('status', ['out_for_signature', 'partially_signed'])
            ->get()
            ->map(function ($req) {
                $signed = $req->signers->where('status', 'signed')->count();
                $total = $req->signers->count();
                $days = $req->sent_date
                    ? (int) CarbonImmutable::parse($req->sent_date)->startOfDay()->diffInDays(CarbonImmutable::now()->startOfDay())
                    : null;

                return [
                    'req' => $req,
                    'days_pending' => $days,
                    'signed' => $signed,
                    'total' => $total,
                    'all_signed' => $total > 0 && $signed === $total,
                    'pending' => $req->signers->whereIn('status', ['pending', 'not_sent'])->pluck('signer_name')->implode(', '),
                ];
            })
            ->sortByDesc('days_pending')
            ->values();
    }

    // --- Signature workflow actions ---

    public function sendForSignature(int $obligationId): void
    {
        if (! $this->canEdit) {
            return;
        }
        if ($o = Obligation::find($obligationId)) {
            app(SignatureService::class)->sendForSignature($o);
            $this->tab = 'awaiting';
        }
    }

    public function markSigned(int $signerId): void
    {
        if (! $this->canEdit) {
            return;
        }
        if ($s = SignatureRequestSigner::find($signerId)) {
            app(SignatureService::class)->markSigned($s);
        }
    }

    public function markRejected(int $signerId): void
    {
        if (! $this->canEdit) {
            return;
        }
        if ($s = SignatureRequestSigner::find($signerId)) {
            app(SignatureService::class)->markRejected($s);
        }
    }

    public function markComplete(int $requestId): void
    {
        if (! $this->canEdit) {
            return;
        }
        if ($r = SignatureRequest::find($requestId)) {
            app(SignatureService::class)->complete($r);
            // Reflect the new last_completed in the editable register form state.
            $o = $r->obligation->fresh();
            $this->form[$o->id]['last_completed'] = optional($o->last_completed)->toDateString();
            $this->form[$o->id]['signature_status'] = $o->signature_status;
        }
    }

    public function badge(string $color): string
    {
        return match ($color) {
            'red' => 'bg-red-100 text-red-800 ring-red-600/20',
            'amber' => 'bg-amber-100 text-amber-800 ring-amber-600/20',
            'yellow' => 'bg-yellow-100 text-yellow-800 ring-yellow-600/20',
            'green' => 'bg-green-100 text-green-800 ring-green-600/20',
            default => 'bg-gray-100 text-gray-700 ring-gray-500/20',
        };
    }

    public function sigLabel(string $status): string
    {
        return match ($status) {
            'out_for_signature' => 'Out for Signature',
            'partially_signed' => 'Partially Signed',
            'complete' => 'Complete',
            default => 'Not started',
        };
    }
}; ?>

<div class="min-h-screen bg-gray-50 text-gray-900">
    <div class="mx-auto max-w-7xl px-4 py-6">
        {{-- Header --}}
        <div class="mb-6 flex items-start justify-between gap-4">
            <div class="flex items-center gap-3">
                <img src="{{ asset('img/cliacomplai-icon.png') }}" alt="CLIAComplai" class="h-11 w-11 shrink-0">
                <div>
                    <h1 class="text-2xl font-bold tracking-tight">{{ $labName }}</h1>
                    <p class="text-sm text-gray-500">
                        CLIA Compliance · <a href="{{ route('portfolio') }}" class="text-indigo-600 hover:underline">← My Labs</a>
                    </p>
                </div>
            </div>
            @auth
                <div class="flex items-center gap-3 text-sm">
                    @if ($canManageUsers)
                        <a href="{{ route('lab.profile', $labId) }}" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 shadow-sm hover:bg-gray-50">Lab Profile</a>
                        <a href="{{ route('users', $labId) }}" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 font-medium text-gray-700 shadow-sm hover:bg-gray-50">Users</a>
                    @endif
                    <div class="text-right">
                        <div class="font-medium text-gray-800">{{ auth()->user()->name }}</div>
                        <div class="text-xs text-gray-400">
                            {{ auth()->user()->roleListForLab($labId) }}@unless ($canEdit) <span class="text-gray-400">· read-only</span>@endunless
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-600 shadow-sm hover:bg-gray-50">Sign out</button>
                    </form>
                </div>
            @endauth
        </div>

        {{-- Tabs --}}
        @php
            $tabs = [
                'overview' => 'Overview',
                'due_soon' => 'Due Soon',
                'overdue' => 'Overdue',
                'register' => 'Full Register',
                'awaiting' => 'Awaiting Signature',
                'completeness' => 'Completeness',
                'activity' => 'Activity',
            ];
        @endphp
        <div class="mb-6 flex flex-wrap gap-1 border-b border-gray-200">
            @foreach ($tabs as $key => $label)
                <button wire:click="setTab('{{ $key }}')"
                    class="relative -mb-px rounded-t-md px-4 py-2 text-sm font-medium transition
                        {{ $tab === $key ? 'border-b-2 border-indigo-600 text-indigo-700' : 'text-gray-500 hover:text-gray-800' }}">
                    {{ $label }}
                    @if ($key === 'overdue' && $this->counts['overdue'] > 0)
                        <span class="ml-1 rounded-full bg-red-600 px-1.5 py-0.5 text-xs font-semibold text-white">{{ $this->counts['overdue'] }}</span>
                    @endif
                    @if ($key === 'awaiting' && $this->awaitingRequests->count() > 0)
                        <span class="ml-1 rounded-full bg-indigo-600 px-1.5 py-0.5 text-xs font-semibold text-white">{{ $this->awaitingRequests->count() }}</span>
                    @endif
                </button>
            @endforeach
        </div>

        {{-- Filter / sort toolbar — shown on the obligation tables --}}
        @if (in_array($tab, ['due_soon', 'overdue', 'register', 'completeness'], true))
            @php
                $shownCount = match ($tab) {
                    'due_soon' => $this->dueSoon->count(),
                    'overdue' => $this->overdue->count(),
                    'register' => $this->registerRows->count(),
                    'completeness' => $this->completenessRowsFiltered->count(),
                    default => 0,
                };
            @endphp
            <div class="mb-3 flex flex-wrap items-center gap-3">
                <label class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-gray-500">Frequency</span>
                    <select wire:model.live="frequencyFilter" class="rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">All frequencies</option>
                        @foreach ($this->frequencyOptions as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="flex items-center gap-1.5">
                    <span class="text-xs font-medium text-gray-500">Sort by</span>
                    <select wire:model.live="sortBy" class="rounded-md border-gray-300 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="next_due">Next due</option>
                        <option value="code">Code</option>
                    </select>
                </label>
                @if ($frequencyFilter !== '')
                    <button wire:click="$set('frequencyFilter', '')" class="text-xs font-medium text-indigo-600 hover:underline">Clear filter</button>
                @endif
                <span class="text-xs text-gray-400">Showing {{ $shownCount }} of {{ $this->rows->count() }}</span>
            </div>
        @endif

        {{-- OVERVIEW --}}
        @if ($tab === 'overview')
            <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @php
                    $cards = [
                        ['Overdue', $this->counts['overdue'], 'bg-red-50 border-red-200 text-red-700'],
                        ['Due ≤30 days', $this->counts['due_30'], 'bg-amber-50 border-amber-200 text-amber-700'],
                        ['Due ≤60 days', $this->counts['due_60'], 'bg-yellow-50 border-yellow-200 text-yellow-700'],
                        ['On track', $this->counts['on_track'], 'bg-green-50 border-green-200 text-green-700'],
                        ['Set dates', $this->counts['set_dates'], 'bg-gray-50 border-gray-200 text-gray-600'],
                    ];
                @endphp
                @foreach ($cards as [$label, $count, $classes])
                    <div class="rounded-xl border p-5 shadow-sm {{ $classes }}">
                        <div class="text-3xl font-bold">{{ $count }}</div>
                        <div class="mt-1 text-sm font-medium">{{ $label }}</div>
                    </div>
                @endforeach
            </div>
            <p class="mt-4 text-sm text-gray-500">
                {{ $this->rows->count() }} obligations tracked.
                @if ($this->counts['set_dates'] > 0)
                    {{ $this->counts['set_dates'] }} still need a baseline “Last Completed” date — set them in <button wire:click="setTab('register')" class="font-medium text-indigo-600 underline">Full Register</button>.
                @endif
            </p>
        @endif

        {{-- DUE SOON / OVERDUE --}}
        @if ($tab === 'due_soon' || $tab === 'overdue')
            @php $list = $tab === 'due_soon' ? $this->dueSoon : $this->overdue; @endphp
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Obligation</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Owner</th>
                            <th class="px-4 py-3 text-right">Days Until Due</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Document</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($list as $r)
                            <tr class="{{ $r['status']->value === 'overdue' ? 'bg-red-50' : 'bg-amber-50' }}">
                                <td class="px-4 py-3 font-medium">{{ $r['o']->code }} — {{ $r['o']->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $r['o']->category }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $r['o']->owner_role }}</td>
                                <td class="px-4 py-3 text-right font-semibold {{ $r['days'] < 0 ? 'text-red-700' : 'text-amber-700' }}">{{ $r['days'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $this->badge($r['status']->color()) }}">{{ $r['status']->label() }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($r['o']->document_link)
                                        <a href="{{ $r['o']->document_link }}" target="_blank" class="text-indigo-600 underline">Open</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-400">Nothing here — {{ $tab === 'overdue' ? 'no overdue items.' : 'nothing due in the next 30 days.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        {{-- FULL REGISTER (editable) --}}
        @if ($tab === 'register')
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-3">ID</th>
                            <th class="px-3 py-3">Obligation</th>
                            <th class="px-3 py-3">Owner</th>
                            <th class="px-3 py-3">Freq</th>
                            <th class="px-3 py-3">Last Completed</th>
                            <th class="px-3 py-3">Next Due</th>
                            <th class="px-3 py-3 text-right">Days</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Signature</th>
                            <th class="px-3 py-3">Document Link</th>
                            <th class="px-3 py-3">Notes</th>
                            <th class="sticky right-0 z-20 border-l border-gray-200 bg-gray-50 px-3 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->registerRows as $r)
                            @php $o = $r['o']; @endphp
                            <tr class="align-top hover:bg-gray-50/60">
                                <td class="px-3 py-2 font-semibold text-gray-700">{{ $o->code }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $o->name }}</div>
                                    <div class="text-xs text-gray-400">{{ $o->category }}</div>
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $o->owner_role }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $o->frequency_label }}</td>
                                <td class="px-3 py-2">
                                    <input type="date" wire:model.blur="form.{{ $o->id }}.last_completed" @disabled(! $canEdit)
                                        class="w-32 rounded border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500" />
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $r['next_due']?->toFormattedDateString() ?? '—' }}</td>
                                <td class="px-3 py-2 text-right {{ $r['days'] !== null && $r['days'] < 0 ? 'font-semibold text-red-700' : 'text-gray-600' }}">{{ $r['days'] ?? '—' }}</td>
                                <td class="px-3 py-2">
                                    <span class="inline-flex whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset {{ $this->badge($r['status']->color()) }}">{{ $r['status']->label() }}</span>
                                    @if ($r['stale'])
                                        <span class="mt-1 block text-[10px] font-medium text-amber-600" title="No update in {{ $r['days_since_verified'] }} days — verify this is still current">⚠ not verified · {{ $r['days_since_verified'] }}d</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    <select wire:model.blur="form.{{ $o->id }}.signature_status" @disabled(! $canEdit)
                                        class="rounded border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500">
                                        <option value="not_started">Not started</option>
                                        <option value="out_for_signature">Out for Signature</option>
                                        <option value="partially_signed">Partially Signed</option>
                                        <option value="complete">Complete</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex flex-col gap-1">
                                        <input type="url" placeholder="Drive URL" wire:model.blur="form.{{ $o->id }}.document_link" @disabled(! $canEdit)
                                            class="w-32 rounded border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500" />
                                        @if (filled($form[$o->id]['document_link'] ?? null))
                                            <a href="{{ $form[$o->id]['document_link'] }}" target="_blank" rel="noopener noreferrer"
                                                class="inline-flex items-center gap-0.5 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline">
                                                Open ↗
                                            </a>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <input type="text" placeholder="…" wire:model.blur="form.{{ $o->id }}.notes" @disabled(! $canEdit)
                                        class="w-28 rounded border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500" />
                                </td>
                                <td class="sticky right-0 z-10 border-l border-gray-200 bg-white px-3 py-2 whitespace-nowrap">
                                    @if (in_array($o->signature_status, ['out_for_signature', 'partially_signed']))
                                        <button wire:click="setTab('awaiting')" class="text-xs font-medium text-indigo-600 underline">Awaiting…</button>
                                    @elseif ($canEdit)
                                        <button wire:click="sendForSignature({{ $o->id }})"
                                            class="rounded-md bg-indigo-600 px-2 py-1 text-xs font-medium text-white shadow-sm hover:bg-indigo-500">Send for signature</button>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                    @php
                                        $formDef = \App\Forms\FormCatalog::forObligation($o->code);
                                        $formParams = $formDef
                                            ? ($formDef['route'] === 'forms.show' ? ['lab' => $labId, 'code' => $formDef['code']] : ['lab' => $labId])
                                            : [];
                                    @endphp
                                    @if ($formDef && $canEdit)
                                        <a href="{{ route($formDef['route'], $formParams) }}"
                                            class="ml-2 inline-block whitespace-nowrap text-xs font-medium text-emerald-700 underline">Fill {{ $formDef['sop_code'] ?? $formDef['code'] }} →</a>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="12" class="px-3 py-10 text-center text-gray-400">No obligations match this frequency filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-400">Edits auto-save on blur and are written to the audit log.</p>
        @endif

        {{-- AWAITING SIGNATURE --}}
        @if ($tab === 'awaiting')
            <div class="space-y-4">
                @forelse ($this->awaitingRequests as $a)
                    @php $req = $a['req']; $o = $req->obligation; @endphp
                    <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <div class="font-semibold text-gray-900">{{ $o->code }} — {{ $o->name }}</div>
                                <div class="text-xs text-gray-500">{{ $o->category }} · Owner: {{ $o->owner_role }}</div>
                            </div>
                            <div class="text-right text-sm">
                                <div class="font-semibold {{ $a['all_signed'] ? 'text-green-700' : 'text-gray-700' }}">{{ $a['signed'] }} of {{ $a['total'] }} signed</div>
                                <div class="text-xs {{ ($a['days_pending'] ?? 0) >= 10 ? 'font-semibold text-red-600' : 'text-gray-500' }}">
                                    @if ($a['days_pending'] !== null)
                                        {{ $a['days_pending'] }} day{{ $a['days_pending'] === 1 ? '' : 's' }} pending
                                        @if ($a['days_pending'] >= 10) · escalate @elseif ($a['days_pending'] >= 5) · reminder due @endif
                                    @endif
                                </div>
                            </div>
                        </div>

                        <table class="mt-3 w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($req->signers as $signer)
                                    <tr>
                                        <td class="py-1.5 text-gray-700">{{ $signer->signer_name }}</td>
                                        <td class="py-1.5">
                                            @php
                                                $sc = match ($signer->status) {
                                                    'signed' => 'bg-green-100 text-green-800',
                                                    'rejected' => 'bg-red-100 text-red-800',
                                                    default => 'bg-gray-100 text-gray-600',
                                                };
                                            @endphp
                                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $sc }}">{{ ucfirst($signer->status) }}</span>
                                            @if ($signer->signed_date)
                                                <span class="ml-1 text-xs text-gray-400">{{ $signer->signed_date->format('Y-m-d') }}</span>
                                            @endif
                                        </td>
                                        <td class="py-1.5 text-right">
                                            @if ($canEdit)
                                                @if ($signer->status !== 'signed')
                                                    <button wire:click="markSigned({{ $signer->id }})" class="text-xs font-medium text-green-700 hover:underline">Mark signed</button>
                                                @endif
                                                @if ($signer->status !== 'rejected')
                                                    <button wire:click="markRejected({{ $signer->id }})" class="ml-3 text-xs font-medium text-red-600 hover:underline">Reject</button>
                                                @endif
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        <div class="mt-3 flex items-center justify-between">
                            <div class="text-xs text-gray-400">
                                Sent {{ optional($req->sent_date)->toFormattedDateString() ?? '—' }}
                                @if ($o->document_link) · <a href="{{ $o->document_link }}" target="_blank" class="text-indigo-600 underline">Open document</a> @endif
                            </div>
                            @if ($canEdit)
                                <button wire:click="markComplete({{ $req->id }})"
                                    @disabled(! $a['all_signed'])
                                    class="rounded-md px-3 py-1.5 text-sm font-medium shadow-sm
                                        {{ $a['all_signed'] ? 'bg-green-600 text-white hover:bg-green-500' : 'cursor-not-allowed bg-gray-200 text-gray-400' }}">
                                    Mark complete &amp; file
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-xl border border-gray-200 bg-white p-10 text-center text-gray-400 shadow-sm">
                        Nothing awaiting signature. Send an obligation from the Full Register to start.
                    </div>
                @endforelse
            </div>
            <p class="mt-3 text-xs text-gray-400">Oldest-pending first. Items reaching 5 days flag a reminder; 10 days flag escalation (emails wired in the reminders phase).</p>
        @endif

        {{-- COMPLETENESS REPORT --}}
        @if ($tab === 'completeness')
            @php $s = $this->completenessSummary; @endphp
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="text-sm text-gray-600">
                    <span class="font-semibold text-gray-900">{{ $s['total'] }}</span> obligations ·
                    <span class="font-semibold text-green-700">{{ $s['completed'] }}</span> completed ·
                    <span class="font-semibold text-red-700">{{ $s['missing'] }}</span> missing a date ·
                    <span class="font-semibold">{{ $s['with_links'] }}</span> with links
                </div>
                <div class="flex gap-2">
                    <a href="{{ route('reports.completeness.csv', $labId) }}"
                        class="rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-300 hover:bg-gray-50">Export CSV</a>
                    <a href="{{ route('reports.completeness.pdf', $labId) }}"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">Export PDF</a>
                </div>
            </div>
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-3 py-3">ID</th>
                            <th class="px-3 py-3">Obligation</th>
                            <th class="px-3 py-3">Owner</th>
                            <th class="px-3 py-3 text-center">On file?</th>
                            <th class="px-3 py-3">Last Completed</th>
                            <th class="px-3 py-3">Status</th>
                            <th class="px-3 py-3">Signature</th>
                            <th class="px-3 py-3">Required Signers</th>
                            <th class="px-3 py-3">Document</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->completenessRowsFiltered as $r)
                            <tr class="{{ $r['completed'] ? '' : 'bg-red-50' }}">
                                <td class="px-3 py-2 font-semibold text-gray-700">{{ $r['code'] }}</td>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $r['name'] }}</div>
                                    <div class="text-xs text-gray-400">{{ $r['category'] }}</div>
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $r['owner_role'] }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if ($r['completed'])
                                        <span class="font-semibold text-green-700">✓ Yes</span>
                                    @else
                                        <span class="font-semibold text-red-700">✗ No</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $r['last_completed'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $r['status'] }}</td>
                                <td class="px-3 py-2 text-gray-600">{{ $r['signature_status'] }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $r['required_signers'] }}</td>
                                <td class="px-3 py-2">
                                    @if ($r['document_link'])
                                        <a href="{{ $r['document_link'] }}" target="_blank" class="text-indigo-600 underline">Open</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="px-3 py-10 text-center text-gray-400">No obligations match this frequency filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-400">Inspection-ready: every row shows whether evidence is on file, with a clickable Drive link. Red rows are missing a completion date.</p>
        @endif

        {{-- ACTIVITY (audit trail) --}}
        @if ($tab === 'activity')
            <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">When</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Field</th>
                            <th class="px-4 py-3">From</th>
                            <th class="px-4 py-3">To</th>
                            <th class="px-4 py-3">By</th>
                            <th class="px-4 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($this->activity as $a)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap text-gray-500">{{ $a['when']?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-2 font-semibold text-gray-700">{{ $a['entity'] }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $a['field'] }}</td>
                                <td class="px-4 py-2 text-gray-400">{{ $a['old'] !== '' && $a['old'] !== null ? $a['old'] : '—' }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $a['new'] !== '' && $a['new'] !== null ? $a['new'] : '—' }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $a['by'] ?? '—' }}</td>
                                <td class="px-4 py-2"><span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600">{{ $a['action'] }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-400">No changes logged yet. Edit a cell in the Full Register to see it appear here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-400">Append-only audit trail (kept ≥ 7 years for HIPAA). Showing the 100 most recent changes.</p>
        @endif
    </div>
</div>
