<?php

namespace App\Livewire\Pages\Exports;

use App\Models\Province;
use App\Models\Territoire;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Index extends Component
{
    public string $from = '';

    public string $to = '';

    public ?string $province = null;

    public ?string $territoire = null;

    public bool $include_violences = true;

    public bool $include_victimes = true;

    public bool $include_reponses = true;

    public function mount(): void
    {
        $this->to = now()->toDateString();
        $this->from = now()->subDays(30)->toDateString();

        if (! $this->isSuperAdmin()) {
            $this->province = Auth::user()?->code_province;
        }
    }

    #[Computed]
    public function provinces(): array
    {
        if (! $this->isSuperAdmin()) {
            return [];
        }

        return Province::query()
            ->select(['code_province', 'nom_province'])
            ->orderBy('nom_province')
            ->get()
            ->map(fn (Province $province): array => [
                'code' => $province->code_province,
                'name' => $province->nom_province,
            ])
            ->all();
    }

    #[Computed]
    public function territoires(): array
    {
        if (! $this->province) {
            return [];
        }

        return Territoire::query()
            ->select(['code_territoire', 'nom_territoire'])
            ->where('code_province', $this->province)
            ->orderBy('nom_territoire')
            ->get()
            ->map(fn (Territoire $territoire): array => [
                'code' => $territoire->code_territoire,
                'name' => $territoire->nom_territoire,
            ])
            ->all();
    }

    public function updatedProvince(): void
    {
        $this->territoire = null;
        unset($this->territoires);
    }

    public function export()
    {
        $data = $this->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after:from'],
            'province' => ['nullable', 'string', 'exists:provinces,code_province'],
            'territoire' => ['nullable', 'string', 'exists:territoires,code_territoire'],
            'include_violences' => ['boolean'],
            'include_victimes' => ['boolean'],
            'include_reponses' => ['boolean'],
        ]);

        if (! $this->isSuperAdmin()) {
            $data['province'] = Auth::user()?->code_province;
        }

        $this->ensureTerritoireBelongsToProvince($data['territoire'] ?? null, $data['province'] ?? null);

        $query = [
            'from' => $data['from'],
            'to' => $data['to'],
            'include_violences' => (int) ($data['include_violences'] ?? false),
            'include_victimes' => (int) ($data['include_victimes'] ?? false),
            'include_reponses' => (int) ($data['include_reponses'] ?? false),
        ];

        if (! empty($data['province'])) {
            $query['province'] = $data['province'];
        }

        if (! empty($data['territoire'])) {
            $query['territoire'] = $data['territoire'];
        }

        return $this->redirectRoute('exports.incidents', $query, navigate: false);
    }

    public function render()
    {
        return view('livewire.pages.exports.index');
    }

    private function isSuperAdmin(): bool
    {
        return (bool) Auth::user()?->hasRole('superadmin');
    }

    private function ensureTerritoireBelongsToProvince(?string $territoire, ?string $province): void
    {
        if (! $territoire || ! $province) {
            return;
        }

        $exists = DB::table('territoires')
            ->where('code_territoire', $territoire)
            ->where('code_province', $province)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'territoire' => 'Le territoire selectionne ne correspond pas a la province choisie.',
            ]);
        }
    }
}
