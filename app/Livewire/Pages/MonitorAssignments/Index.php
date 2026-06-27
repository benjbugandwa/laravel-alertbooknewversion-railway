<?php

namespace App\Livewire\Pages\MonitorAssignments;

use App\Models\MonitorSupervisorAssignment;
use App\Models\Province;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';
    public string $province = '';
    public ?string $supervisorId = null;
    public array $monitorIds = [];
    public int $perPage = 10;

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasRole('superadmin'), 403);
        $this->province = Auth::user()->code_province ?? '';
    }

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatedProvince(): void
    {
        $this->province = Auth::user()->code_province ?? '';
        $this->supervisorId = null;
        $this->monitorIds = [];
        $this->resetPage();
    }

    #[Computed]
    public function provinces()
    {
        return Province::query()
            ->select('code_province', 'nom_province')
            ->where('code_province', Auth::user()->code_province)
            ->orderBy('nom_province')
            ->get();
    }

    #[Computed]
    public function supervisors()
    {
        if (!$this->province) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('code_province', $this->province)
            ->whereHas('roles', fn($roleQuery) => $roleQuery->where('slug', 'superviseur'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }

    #[Computed]
    public function monitors()
    {
        if (!$this->province) {
            return collect();
        }

        return User::query()
            ->where('is_active', true)
            ->where('code_province', $this->province)
            ->whereHas('roles', fn($roleQuery) => $roleQuery->where('slug', 'moniteur'))
            ->with('monitorAssignments.supervisor')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'code_province']);
    }

    public function assign(): void
    {
        abort_unless(Auth::user()?->hasRole('superadmin'), 403);

        $this->validate([
            'province' => ['required', Rule::in([Auth::user()->code_province])],
            'supervisorId' => [
                'required',
                Rule::exists('users', 'id')->where('code_province', $this->province)->where('is_active', true),
            ],
            'monitorIds' => ['required', 'array', 'min:1'],
            'monitorIds.*' => [
                'required',
                Rule::exists('users', 'id')->where('code_province', $this->province)->where('is_active', true),
            ],
        ]);

        $supervisor = $this->supervisors->firstWhere('id', (int) $this->supervisorId);
        if (!$supervisor) {
            $this->addError('supervisorId', 'Superviseur invalide pour cette province.');
            return;
        }

        $allowedMonitorIds = $this->monitors->pluck('id')->map(fn($id) => (string) $id)->all();
        foreach ($this->monitorIds as $monitorId) {
            if (!in_array((string) $monitorId, $allowedMonitorIds, true)) {
                $this->addError('monitorIds', 'Un moniteur selectionne est invalide pour cette province.');
                return;
            }
        }

        foreach ($this->monitorIds as $monitorId) {
            $assignment = MonitorSupervisorAssignment::firstOrNew(['monitor_id' => $monitorId]);
            if (!$assignment->exists) {
                $assignment->created_by = Auth::id();
            }

            $assignment->fill([
                'supervisor_id' => $this->supervisorId,
                'code_province' => $this->province,
                'updated_by' => Auth::id(),
            ])->save();
        }

        $count = count($this->monitorIds);
        $this->monitorIds = [];
        $this->dispatch('toast', message: "{$count} moniteur(s) assigne(s).", type: 'success', duration: 5000);
    }

    public function removeAssignment(int $assignmentId): void
    {
        abort_unless(Auth::user()?->hasRole('superadmin'), 403);

        $assignment = MonitorSupervisorAssignment::query()
            ->where('code_province', Auth::user()->code_province)
            ->findOrFail($assignmentId);
        $assignment->delete();

        $this->dispatch('toast', message: 'Affectation retiree.', type: 'success', duration: 5000);
    }

    public function render()
    {
        $assignments = MonitorSupervisorAssignment::query()
            ->with(['monitor', 'supervisor', 'province'])
            ->where('code_province', Auth::user()->code_province)
            ->when(trim($this->q) !== '', function ($query): void {
                $search = '%' . trim($this->q) . '%';
                $query->where(function ($query) use ($search): void {
                    $query->whereHas('monitor', fn($userQuery) => $userQuery->where('name', 'ilike', $search)->orWhere('email', 'ilike', $search))
                        ->orWhereHas('supervisor', fn($userQuery) => $userQuery->where('name', 'ilike', $search)->orWhere('email', 'ilike', $search));
                });
            })
            ->latest()
            ->paginate($this->perPage);

        return view('livewire.pages.monitor-assignments.index', [
            'assignments' => $assignments,
        ]);
    }
}
