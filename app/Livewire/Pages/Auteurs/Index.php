<?php

namespace App\Livewire\Pages\Auteurs;

use App\Models\Auteur;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $q = '';
    public int $perPage = 10;

    public bool $showModal = false;
    public bool $editing = false;
    public ?string $editingId = null;

    public array $form = [
        'code_auteur' => '',
        'denomination_auteur' => '',
        'observation' => '',
    ];

    public function updatingQ(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    private function isAllowed(): bool
    {
        return Auth::check() && Auth::user()->user_role === 'superadmin';
    }

    public function mount(): void
    {
        abort_if(!$this->isAllowed(), 403, 'Accès non autorisé.');
    }

    private function rules(): array
    {
        return [
            'form.code_auteur' => [
                'required',
                'string',
                'max:100',
                $this->editing
                    ? Rule::unique('auteurs', 'code_auteur')->ignore($this->editingId, 'code_auteur')
                    : 'unique:auteurs,code_auteur',
            ],
            'form.denomination_auteur' => [
                'required',
                'string',
                'max:255',
                $this->editing
                    ? Rule::unique('auteurs', 'denomination_auteur')->ignore($this->editingId, 'code_auteur')
                    : 'unique:auteurs,denomination_auteur',
            ],
            'form.observation' => ['nullable', 'string'],
        ];
    }

    public function openCreate(): void
    {
        if (!$this->isAllowed()) {
            $this->dispatch('toast', message: "Accès refusé.", type: 'error', duration: 5000);
            return;
        }

        $this->resetValidation();
        $this->editing = false;
        $this->editingId = null;

        $this->form = [
            'code_auteur' => '',
            'denomination_auteur' => '',
            'observation' => '',
        ];

        $this->showModal = true;
    }

    public function openEdit(string $codeAuteur): void
    {
        if (!$this->isAllowed()) {
            $this->dispatch('toast', message: "Accès refusé.", type: 'error', duration: 5000);
            return;
        }

        $this->resetValidation();

        $auteur = Auteur::findOrFail($codeAuteur);

        $this->editing = true;
        $this->editingId = $auteur->code_auteur;

        $this->form = [
            'code_auteur' => $auteur->code_auteur,
            'denomination_auteur' => $auteur->denomination_auteur,
            'observation' => $auteur->observation ?? '',
        ];

        $this->showModal = true;
    }

    public function save(): void
    {
        if (!$this->isAllowed()) {
            $this->dispatch('toast', message: "Accès refusé.", type: 'error', duration: 5000);
            return;
        }

        $this->validate($this->rules());

        $code = trim($this->form['code_auteur']);
        $denomination = trim($this->form['denomination_auteur']);
        $observation = trim($this->form['observation']);

        $payload = [
            'code_auteur' => $code,
            'denomination_auteur' => $denomination,
            'observation' => $observation !== '' ? $observation : null,
        ];

        if ($this->editing && $this->editingId) {
            $auteur = Auteur::findOrFail($this->editingId);
            
            // Si la clé primaire est modifiée (non recommandé mais géré)
            $auteur->update($payload);

            $this->dispatch('toast', message: "Auteur présumé mis à jour.", type: 'success', duration: 5000);
        } else {
            Auteur::create($payload);

            $this->dispatch('toast', message: "Auteur présumé créé avec succès.", type: 'success', duration: 5000);
        }

        $this->showModal = false;
    }

    public function render()
    {
        $query = Auteur::query()->with('creator');

        if (trim($this->q) !== '') {
            $s = '%' . trim($this->q) . '%';
            $query->where(function ($qq) use ($s) {
                $qq->where('code_auteur', 'ilike', $s)
                  ->orWhere('denomination_auteur', 'ilike', $s)
                  ->orWhere('observation', 'ilike', $s);
            });
        }

        $auteurs = $query
            ->orderBy('denomination_auteur')
            ->paginate($this->perPage);

        return view('livewire.pages.auteurs.index', [
            'auteurs' => $auteurs,
        ]);
    }
}
