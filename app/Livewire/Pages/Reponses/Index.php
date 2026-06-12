<?php

namespace App\Livewire\Pages\Reponses;

use Livewire\Component;
use Livewire\WithPagination;
use Livewire\WithFileUploads;
use App\Models\Reponse;
use App\Models\Incident;
use App\Models\Organisation;
use App\Livewire\Forms\ReponseForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\ReponsesExport;

#[Title('Gestion des Réponses aux Incidents')]
class Index extends Component
{
    use WithPagination, WithFileUploads;

    public ?Incident $incident = null;
    public ReponseForm $form;

    // Selection properties
    public ?string $selectedIncidentId = null;
    public array $all_incidents = [];
    public array $organisations = [];

    // Filter properties
    public ?string $f_date_reponse = '';
    public string $f_fournie_par = '';
    public string $f_type_reponse = '';

    // Modal properties
    public bool $showModal = false;
    public bool $editing = false;
    public ?int $editingId = null;
    public $rapportFile = null; // for file upload

    // Export Modal properties
    public bool $showExportModal = false;
    public string $exp_start_date = '';
    public string $exp_end_date = '';

    public function mount(?string $incident = null)
    {
        $user = Auth::user();

        // Load incidents list for selection dropdown
        $incidentsQuery = Incident::orderByDesc('created_at')
            ->where('statut_incident', '!=', 'En attente');
        if ($user->user_role !== 'superadmin' && $user->code_province) {
            $incidentsQuery->where('code_province', $user->code_province);
        }
        $this->all_incidents = $incidentsQuery->get(['id', 'code_incident', 'localite', 'date_incident'])->toArray();

        // Resolve active incident
        if ($incident) {
            $this->incident = Incident::find($incident);
        }
        
        if (!$this->incident) {
            // Default to most recent validated / not-pending incident
            $first = Incident::orderByDesc('created_at')
                ->where('statut_incident', '!=', 'En attente');
            if ($user->user_role !== 'superadmin' && $user->code_province) {
                $first->where('code_province', $user->code_province);
            }
            $this->incident = $first->first();
        }

        if ($this->incident) {
            $this->selectedIncidentId = $this->incident->id;
        }

        // Load organisations list for autocomplete datalist
        $this->organisations = Organisation::orderBy('org_name')->get(['org_name', 'org_sigle'])->toArray();
    }

    public function updatedSelectedIncidentId($value)
    {
        if ($value) {
            return redirect()->route('reponses.index', ['incident' => $value]);
        }
        return redirect()->route('reponses.index');
    }

    public function canWrite()
    {
        return in_array(auth()->user()->user_role, ['superadmin', 'admin', 'superviseur'], true);
    }

    public function updating($field)
    {
        if (in_array($field, ['f_date_reponse', 'f_fournie_par', 'f_type_reponse'])) {
            $this->resetPage();
        }
    }

    public function openCreate()
    {
        if (!$this->canWrite()) {
            $this->dispatch('toast', message: 'Action non autorisée pour votre rôle.', type: 'error');
            return;
        }

        if (!$this->incident) {
            $this->dispatch('toast', message: "Aucun incident disponible.", type: 'error');
            return;
        }

        // Check if the incident is validated and not archived
        if ($this->incident->statut_incident !== 'Validé' && $this->incident->statut_incident !== 'Cloturée') {
            $this->dispatch('toast', message: "Impossible d'ajouter une réponse à un incident non validé ou archivé.", type: 'error');
            return;
        }

        $this->resetValidation();
        $this->form->reset();
        $this->rapportFile = null;

        $this->form->alerte_id = $this->incident->id;
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

        $reponse = Reponse::findOrFail($id);

        // Check if the incident is validated and not archived
        if ($this->incident->statut_incident !== 'Validé' && $this->incident->statut_incident !== 'Cloturée') {
            $this->dispatch('toast', message: "Impossible de modifier une réponse d'un incident non validé ou archivé.", type: 'error');
            return;
        }

        $this->resetValidation();
        $this->form->setReponse($reponse);
        $this->rapportFile = null;
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

        // Backend double check
        if ($this->incident->statut_incident !== 'Validé' && $this->incident->statut_incident !== 'Cloturée') {
            $this->dispatch('toast', message: "L'incident doit être validé ou clôturé, et non archivé.", type: 'error');
            return;
        }

        $this->form->alerte_id = $this->incident->id;
        $this->form->validate();

        if ($this->rapportFile) {
            $this->validate([
                'rapportFile' => 'file|mimes:doc,docx,pdf,jpg,jpeg,png|max:20480',
            ], [
                'rapportFile.mimes' => 'Le rapport doit être un fichier de type : word, pdf, image.',
                'rapportFile.max' => 'La taille du rapport ne peut pas dépasser 20 Mo.',
            ]);
        }

        $data = $this->form->all();
        unset($data['reponse'], $data['rapport']);

        if ($this->rapportFile) {
            $path = $this->rapportFile->store('rapports', 'public');
            $data['rapport'] = $path;
        }

        if ($this->editing) {
            $reponse = Reponse::findOrFail($this->editingId);
            
            if ($this->rapportFile && $reponse->rapport) {
                Storage::disk('public')->delete($reponse->rapport);
            } elseif (!$this->rapportFile) {
                $data['rapport'] = $reponse->rapport;
            }

            $reponse->update($data);
            $this->dispatch('toast', message: 'Réponse mise à jour avec succès.', type: 'success');
        } else {
            $data['created_by'] = auth()->id();
            Reponse::create($data);
            $this->dispatch('toast', message: 'Réponse enregistrée avec succès.', type: 'success');
        }

        $this->showModal = false;
    }

    public function delete($id)
    {
        if (!$this->canWrite()) {
            $this->dispatch('toast', message: 'Action non autorisée.', type: 'error');
            return;
        }

        if ($this->incident->statut_incident !== 'Validé' && $this->incident->statut_incident !== 'Cloturée') {
            $this->dispatch('toast', message: "L'incident n'est pas modifiable.", type: 'error');
            return;
        }

        $reponse = Reponse::findOrFail($id);
        if ($reponse->rapport) {
            Storage::disk('public')->delete($reponse->rapport);
        }
        $reponse->delete();

        $this->dispatch('toast', message: 'Réponse supprimée avec succès.', type: 'success');
    }

    public function downloadRapport($id)
    {
        $reponse = Reponse::findOrFail($id);
        if (!$reponse->rapport || !Storage::disk('public')->exists($reponse->rapport)) {
            $this->dispatch('toast', message: 'Fichier introuvable sur le serveur.', type: 'error');
            return;
        }
        return Storage::disk('public')->download($reponse->rapport);
    }

    public function openExport()
    {
        $this->resetValidation();
        $this->exp_start_date = '';
        $this->exp_end_date = '';
        $this->showExportModal = true;
    }

    public function export()
    {
        $this->validate([
            'exp_start_date' => 'required|date',
            'exp_end_date' => 'required|date|after_or_equal:exp_start_date',
        ], [
            'exp_start_date.required' => 'La date de début est requise.',
            'exp_end_date.required' => 'La date de fin est requise.',
            'exp_end_date.after_or_equal' => 'La date de fin doit être supérieure ou égale à la date de début.',
        ]);

        $filename = 'Export_Reponses_' . ($this->incident ? $this->incident->code_incident . '_' : '') . now()->format('Ymd_His') . '.xlsx';
        
        $this->showExportModal = false;

        return Excel::download(
            new ReponsesExport($this->exp_start_date, $this->exp_end_date, $this->incident->id), 
            $filename
        );
    }

    public function render()
    {
        $reponses = collect();

        if ($this->incident) {
            $query = Reponse::where('alerte_id', $this->incident->id)->with(['creator']);

            // Search and Filters
            if ($this->f_date_reponse) {
                $query->whereDate('date_reponse', $this->f_date_reponse);
            }
            if ($this->f_fournie_par) {
                $query->where('fournie_par', 'like', '%' . $this->f_fournie_par . '%');
            }
            if ($this->f_type_reponse) {
                $query->where('type_reponse', $this->f_type_reponse);
            }

            $reponses = $query->orderByDesc('date_reponse')->paginate(10);
        }

        return view('livewire.pages.reponses.index', [
            'reponses' => $reponses,
            'types_options' => ['Humanitaire', 'Militaire', 'Mixte', 'Autre'],
            'secteurs_options' => ['Sécurité', 'Protection', 'WASH', 'santé', 'Education', 'Sécurité alimentaire'],
        ]);
    }
}
