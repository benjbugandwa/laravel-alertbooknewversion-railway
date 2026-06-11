<?php

namespace App\Livewire\Pages\Victimes;

use Livewire\Component;
use App\Models\Victime;
use App\Models\Incident;
use App\Models\Violence;
use App\Livewire\Forms\VictimeForm;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;

#[Title('Gestion des Victimes')]
class Index extends Component
{
    public ?Incident $incident = null;
    public VictimeForm $form;
    
    public ?string $selectedIncidentId = null;
    
    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;

    // References for selector
    public array $all_incidents = [];
    public array $violences_options = [];

    public function mount(?string $incident = null)
    {
        $user = Auth::user();

        // Load incidents list for selection dropdown
        $incidentsQuery = Incident::orderByDesc('created_at');
        if ($user->user_role !== 'superadmin' && $user->code_province) {
            $incidentsQuery->where('code_province', $user->code_province);
        }
        $this->all_incidents = $incidentsQuery->get(['id', 'code_incident', 'localite', 'date_incident'])->toArray();

        // Resolve active incident
        if ($incident) {
            $this->incident = Incident::findOrFail($incident);
        } else {
            // Default to most recent incident
            $first = Incident::orderByDesc('created_at');
            if ($user->user_role !== 'superadmin' && $user->code_province) {
                $first->where('code_province', $user->code_province);
            }
            $this->incident = $first->first();
        }

        if ($this->incident) {
            $this->selectedIncidentId = $this->incident->id;
            $this->loadViolencesOptions();
        }

        // Handle auto-open of modal from incident show page
        $addForViolence = request()->query('add_for_violence');
        if ($addForViolence && $this->incident) {
            $this->openCreate();
            $this->form->violence_id = (int)$addForViolence;
        }
    }

    public function updatedSelectedIncidentId($value)
    {
        if ($value) {
            return redirect()->route('victimes.index', ['incident' => $value]);
        }
        return redirect()->route('victimes.index');
    }

    public function canWrite()
    {
        return in_array(auth()->user()->user_role, ['superadmin', 'admin', 'superviseur'], true);
    }

    public function loadViolencesOptions()
    {
        if ($this->incident) {
            // We only show violences that are present in the violence_incidents pivot table
            $this->violences_options = $this->incident->violences()
                ->get(['violences.id', 'violence_name'])
                ->toArray();
        } else {
            $this->violences_options = [];
        }
    }

    public function openCreate()
    {
        if (!$this->canWrite()) {
            $this->dispatch('toast', message: 'Action non autorisée pour votre rôle.', type: 'error');
            return;
        }

        $this->resetValidation();
        $this->form->reset();
        
        $this->form->incident_id = $this->incident->id;
        $this->editing = false;
        $this->editingId = null;
        $this->showModal = true;
    }

    public function openEdit($id)
    {
        if (!$this->canWrite()) {
            $this->dispatch('toast', message: 'Action non autorisée pour votre rôle.', type: 'error');
            return;
        }

        $victime = Victime::findOrFail($id);

        $this->resetValidation();
        $this->form->setVictime($victime);
        $this->editing = true;
        $this->editingId = $id;
        $this->showModal = true;
    }

    public function save()
    {
        if (!$this->canWrite()) {
            $this->dispatch('toast', message: 'Action non autorisée.', type: 'error');
            return;
        }

        $this->form->incident_id = $this->incident->id;
        $this->form->validate();

        $data = $this->form->all();
        unset($data['victime']);

        // Explicitly format age group null values to 0 if not provided
        $ageGroups = [
            'nbre_femme_0a4ans', 'nbre_femme_5a11ans', 'nbre_femme_12a17ans', 'nbre_femme_18a59ans', 'nbre_femme_6Oansouplus',
            'nbre_homme_0a4ans', 'nbre_homme_5a11ans', 'nbre_homme_12a17ans', 'nbre_homme_18a59ans', 'nbre_homme_6Oansouplus'
        ];
        foreach ($ageGroups as $group) {
            $data[$group] = !empty($data[$group]) ? (int)$data[$group] : 0;
        }

        if ($this->editing) {
            $victime = Victime::findOrFail($this->editingId);
            $victime->update($data);
            $this->dispatch('toast', message: 'Enregistrement des victimes mis à jour.', type: 'success');
        } else {
            Victime::create($data);
            $this->dispatch('toast', message: 'Victimes enregistrées avec succès.', type: 'success');
        }

        $this->showModal = false;
    }

    public function delete($id)
    {
        if (!$this->canWrite()) {
            $this->dispatch('toast', message: 'Action non autorisée.', type: 'error');
            return;
        }

        Victime::destroy($id);
        $this->dispatch('toast', message: 'Enregistrement supprimé avec succès.', type: 'success');
    }

    public function render()
    {
        $victimes = collect();

        if ($this->incident) {
            $victimes = Victime::where('incident_id', $this->incident->id)
                ->with(['violence', 'creator'])
                ->orderBy('create_at', 'desc')
                ->get();
        }

        return view('livewire.pages.victimes.index', [
            'victimes' => $victimes,
            'profiles' => ["Résidants", "Réfugiés", "Déplacés", "Retournés", "Autres"],
        ]);
    }
}
