<?php

use App\Models\AuditLog;
use App\Models\Lab;
use App\Models\LabUser;
use App\Models\User;
use App\Support\CurrentLab;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public int $labId;
    public string $labName = '';
    public string $inviteEmail = '';
    public string $inviteName = '';

    public function mount(Lab $lab): void
    {
        abort_unless(auth()->user()?->canManageLab($lab), 403);
        $this->labId = $lab->id;
        $this->labName = $lab->name;
        app(CurrentLab::class)->set($lab);
    }

    public function hydrate(): void
    {
        $lab = Lab::find($this->labId);
        abort_unless($lab && auth()->user()?->canManageLab($lab), 403);
        app(CurrentLab::class)->set($lab);
    }

    #[Computed]
    public function members()
    {
        return LabUser::with(['user', 'roles'])->where('lab_id', $this->labId)->get()
            ->sortBy(fn ($m) => ($m->active ? '0' : '1') . $m->user->name)->values();
    }

    #[Computed]
    public function addableUsers()
    {
        $memberIds = LabUser::where('lab_id', $this->labId)->pluck('user_id');

        return User::whereNotIn('id', $memberIds)->orderBy('name')->get();
    }

    public function addMember(int $userId): void
    {
        $this->authorizeManage();
        if (LabUser::where('lab_id', $this->labId)->where('user_id', $userId)->exists()) {
            return;
        }
        if (! User::find($userId)) {
            return;
        }
        $m = LabUser::create(['lab_id' => $this->labId, 'user_id' => $userId, 'active' => true]);
        $m->syncRoles(['tech_staff']);
        $this->audit($userId, 'membership', null, 'added', 'lab_member_add');
    }

    /** Invite someone who hasn't signed in yet: pre-create their user + lab access; the Google
     *  callback links them by email on first sign-in. */
    public function inviteByEmail(): void
    {
        $this->authorizeManage();

        $email = strtolower(trim($this->inviteEmail));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session()->flash('userError', 'Enter a valid email address to invite.');

            return;
        }

        $user = User::where('email', $email)->first();
        if (! $user) {
            $user = new User([
                'name' => trim($this->inviteName) ?: Str::before($email, '@'),
                'email' => $email,
                'active' => true,
            ]);
            $user->password = Str::random(40); // SSO via Google; this is never used
            $user->save();
        }

        if (LabUser::where('lab_id', $this->labId)->where('user_id', $user->id)->exists()) {
            session()->flash('userError', "{$email} is already on this lab.");
            $this->reset('inviteEmail', 'inviteName');

            return;
        }

        $m = LabUser::create(['lab_id' => $this->labId, 'user_id' => $user->id, 'active' => true]);
        $m->syncRoles(['tech_staff']);
        $this->audit($user->id, 'membership', null, 'invited', 'lab_member_invite');

        $this->reset('inviteEmail', 'inviteName');
        session()->flash('userMessage', "Invited {$email}. They'll have access the moment they first sign in with Google.");
    }

    public function toggleRole(int $membershipId, string $role): void
    {
        $this->authorizeManage();
        $m = LabUser::with('roles')->find($membershipId);
        if (! $m || $m->lab_id !== $this->labId || ! array_key_exists($role, User::ROLES)) {
            return;
        }

        $current = $m->roleNames();
        $has = in_array($role, $current, true);
        $next = $has ? array_values(array_diff($current, [$role])) : array_merge($current, [$role]);

        // Self-lockout guard: don't let a non-super-admin strip their own last manager role.
        if ($m->user_id === auth()->id() && ! auth()->user()->isSuperAdmin()
            && count(array_intersect($next, User::MANAGER_ROLES)) === 0) {
            session()->flash('userError', "You can't remove your own management access to this lab.");

            return;
        }

        $m->syncRoles($next);
        $this->audit($m->user_id, 'role', $has ? $role : null, $has ? null : $role, 'lab_role_change');
    }

    public function toggleActive(int $membershipId): void
    {
        $this->authorizeManage();
        $m = LabUser::find($membershipId);
        if (! $m || $m->lab_id !== $this->labId) {
            return;
        }
        if ($m->user_id === auth()->id() && ! auth()->user()->isSuperAdmin()) {
            session()->flash('userError', "You can't change your own access to this lab.");

            return;
        }
        $m->update(['active' => ! $m->active]);
        $this->audit($m->user_id, 'active', null, $m->active ? '1' : '0', $m->active ? 'lab_member_activate' : 'lab_member_deactivate');
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()?->canManageLab(Lab::find($this->labId)), 403);
    }

    private function audit(int $userId, string $field, ?string $old, ?string $new, string $action): void
    {
        AuditLog::create([
            'entity_type' => 'lab_user', 'entity_id' => $userId,
            'field' => $field, 'old_value' => (string) $old, 'new_value' => (string) $new,
            'action' => $action, 'changed_by' => auth()->id(), 'changed_at' => now(),
        ]);
    }
}; ?>

<div class="min-h-screen bg-gray-50 text-gray-900">
    <div class="mx-auto max-w-5xl px-4 py-6">
        <div class="mb-6 flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight">Users &amp; Access — {{ $labName }}</h1>
                <p class="text-sm text-gray-500">Grant access, assign one or more roles, revoke access.</p>
            </div>
            <a href="{{ route('dashboard', $labId) }}" class="text-sm font-medium text-indigo-600 hover:underline">← Back to dashboard</a>
        </div>

        @if (session('userError'))
            <div class="mb-4 rounded-md bg-red-50 p-3 text-sm text-red-700 ring-1 ring-red-200">{{ session('userError') }}</div>
        @endif
        @if (session('userMessage'))
            <div class="mb-4 rounded-md bg-green-50 p-3 text-sm text-green-800 ring-1 ring-green-200">{{ session('userMessage') }}</div>
        @endif

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Roles (click to toggle)</th>
                        <th class="px-4 py-3">Access</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($this->members as $m)
                        @php $roles = $m->roleNames(); @endphp
                        <tr class="{{ $m->active ? '' : 'bg-amber-50/50' }} align-top">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ $m->user->name }}@if ($m->user_id === auth()->id())<span class="ml-1 text-xs text-gray-400">(you)</span>@endif</div>
                                <div class="text-xs text-gray-500">{{ $m->user->email }}</div>
                                @unless ($m->user->google_sub)
                                    <div class="mt-0.5 text-[10px] font-medium text-amber-600">Invited · pending first sign-in</div>
                                @endunless
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                    @foreach (App\Models\User::ROLES as $value => $label)
                                        @php $on = in_array($value, $roles, true); @endphp
                                        <button wire:click="toggleRole({{ $m->id }}, '{{ $value }}')"
                                            class="rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset
                                                {{ $on ? 'bg-indigo-100 text-indigo-800 ring-indigo-600/20' : 'bg-white text-gray-400 ring-gray-300 hover:bg-gray-50' }}">
                                            {{ $label }}
                                        </button>
                                    @endforeach
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if ($m->active)
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-0.5 text-xs font-semibold text-green-800">Active</span>
                                @else
                                    <span class="inline-flex rounded-full bg-gray-200 px-2 py-0.5 text-xs font-semibold text-gray-600">Inactive</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @unless ($m->user_id === auth()->id())
                                    <button wire:click="toggleActive({{ $m->id }})"
                                        class="text-xs font-medium {{ $m->active ? 'text-red-600' : 'text-green-700' }} hover:underline">
                                        {{ $m->active ? 'Revoke access' : 'Grant access' }}
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No members yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->addableUsers->isNotEmpty())
            <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                <div class="mb-2 text-sm font-medium text-gray-700">Add an existing user to this lab</div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->addableUsers as $u)
                        <button wire:click="addMember({{ $u->id }})"
                            class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50">
                            + {{ $u->name }} <span class="text-gray-400">({{ $u->email }})</span>
                        </button>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-gray-400">New members start as Technical Staff (read-only) and Active — adjust roles above.</p>
            </div>
        @endif

        <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
            <div class="mb-1 text-sm font-medium text-gray-700">Invite someone by email</div>
            <p class="mb-3 text-xs text-gray-500">For people who haven’t signed in yet. They get access (Technical Staff to start) the moment they first sign in with Google.</p>
            <form wire:submit="inviteByEmail" class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="text-xs font-medium text-gray-600">Email</span>
                    <input type="email" wire:model="inviteEmail" placeholder="name@lab.com" class="mt-1 w-64 rounded-md border-gray-300 text-sm shadow-sm">
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-gray-600">Name (optional)</span>
                    <input type="text" wire:model="inviteName" placeholder="Full name" class="mt-1 w-48 rounded-md border-gray-300 text-sm shadow-sm">
                </label>
                <button type="submit" class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500">Invite</button>
            </form>
        </div>

        <p class="mt-3 text-xs text-gray-400">
            Managers (Admin, Lab Director, Compliance Specialist) can manage users and edit the register.
            Editors (Lab Director Designee, Technical &amp; General Supervisors) can edit the register and
            drive signatures but not manage users. A person may hold several roles here. All changes are logged.
        </p>
    </div>
</div>
