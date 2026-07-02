<?php

namespace App\Livewire\Components;

use App\Exceptions\BusinessRuleException;
use App\Livewire\Forms\IncidentForm;
use App\Models\Incident;
use App\Services\IncidentService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithFileUploads;

class IncidentEditModal extends Component
{
    use WithFileUploads;

    protected IncidentService $incidentService;

    public bool $open = false;

    public ?string $incidentId = null;

    public IncidentForm $form;

    public $photo;

    protected $listeners = ['openIncidentEdit' => 'open'];

    public function boot(IncidentService $incidentService): void
    {
        $this->incidentService = $incidentService;
    }

    public function open(string $incidentId): void
    {
        $incident = Incident::findOrFail($incidentId);

        if (! $this->canEditIncident($incident)) {
            $this->dispatch('toast', message: 'Cette alerte ne peut pas être modifiée.', type: 'warning', duration: 6000);

            return;
        }

        $this->resetValidation();
        $this->photo = null;
        $this->incidentId = $incidentId;
        $this->form->setIncident($incident);
        $this->open = true;
    }

    public function save(): void
    {
        if (! $this->incidentId) {
            return;
        }

        $incident = Incident::findOrFail($this->incidentId);

        if (! $this->canEditIncident($incident)) {
            $this->open = false;
            $this->dispatch('toast', message: 'Cette alerte ne peut pas être modifiée.', type: 'warning', duration: 6000);

            return;
        }

        if (! Auth::user()->hasRole('superadmin')) {
            $this->form->code_province = Auth::user()->code_province;
        }

        $this->form->validate();
        $this->validate([
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
        ]);

        try {
            $this->incidentService->update(
                incident: $incident,
                payload: $this->form->all(),
                photo: $this->photo,
                actor: Auth::user(),
                ipAddress: request()->ip()
            );

            $this->open = false;
            $this->dispatch('incident-updated', incidentId: $incident->id);
            $this->dispatch('toast', message: 'Alerte mise à jour.', type: 'success', duration: 5000);
        } catch (BusinessRuleException $exception) {
            $this->dispatch('toast', message: $exception->getMessage(), type: 'warning', duration: 6500);
        } catch (\Throwable $exception) {
            report($exception);
            $this->dispatch('toast', message: 'Erreur interne. Veuillez réessayer.', type: 'error', duration: 6500);
        }
    }

    #[Computed]
    public function provinces(): array
    {
        return \App\Models\Province::query()
            ->select('code_province', 'nom_province')
            ->orderBy('nom_province')
            ->get()
            ->map(fn ($province) => ['code' => $province->code_province, 'name' => $province->nom_province])
            ->all();
    }

    #[Computed]
    public function territoires(): array
    {
        if (! $this->form->code_province) {
            return [];
        }

        return DB::table('territoires')
            ->select('code_territoire', 'nom_territoire')
            ->where('code_province', $this->form->code_province)
            ->orderBy('nom_territoire')
            ->get()
            ->map(fn ($territoire) => ['code' => $territoire->code_territoire, 'name' => $territoire->nom_territoire])
            ->all();
    }

    #[Computed]
    public function zones(): array
    {
        if (! $this->form->code_territoire) {
            return [];
        }

        return DB::table('zonesantes')
            ->select('code_zonesante', 'nom_zonesante')
            ->where('code_territoire', $this->form->code_territoire)
            ->orderBy('nom_zonesante')
            ->get()
            ->map(fn ($zone) => ['code' => $zone->code_zonesante, 'name' => $zone->nom_zonesante])
            ->all();
    }

    #[Computed]
    public function chefferies(): array
    {
        if (! $this->form->code_territoire) {
            return [];
        }

        return DB::table('chefferies')
            ->select('code_chefferie', 'nom_chefferie')
            ->where('code_territoire', $this->form->code_territoire)
            ->orderBy('nom_chefferie')
            ->get()
            ->map(fn ($chefferie) => ['code' => $chefferie->code_chefferie, 'name' => $chefferie->nom_chefferie])
            ->all();
    }

    #[Computed]
    public function groupements(): array
    {
        if (! $this->form->code_chefferie) {
            return [];
        }

        return DB::table('groupements')
            ->select('code_groupement', 'nom_groupement')
            ->where('code_chefferie', $this->form->code_chefferie)
            ->orderBy('nom_groupement')
            ->get()
            ->map(fn ($groupement) => ['code' => $groupement->code_groupement, 'name' => $groupement->nom_groupement])
            ->all();
    }

    #[Computed]
    public function airesantes(): array
    {
        if (! $this->form->code_zonesante) {
            return [];
        }

        return DB::table('airesantes')
            ->select('code_airesante', 'nom_airesante')
            ->where('code_zonesante', $this->form->code_zonesante)
            ->orderBy('nom_airesante')
            ->get()
            ->map(fn ($aire) => ['code' => $aire->code_airesante, 'name' => $aire->nom_airesante])
            ->all();
    }

    #[Computed]
    public function evenements(): array
    {
        return DB::table('evenements')
            ->select('code_evenement', 'nom_evenement')
            ->orderBy('nom_evenement')
            ->get()
            ->map(fn ($evenement) => ['code' => $evenement->code_evenement, 'name' => $evenement->nom_evenement])
            ->all();
    }

    #[Computed]
    public function listAuteurs()
    {
        return \App\Models\Auteur::orderBy('denomination_auteur')->get();
    }

    public function updatedFormCodeProvince(): void
    {
        $this->form->code_territoire = '';
        $this->form->code_zonesante = '';
        $this->form->code_chefferie = '';
        $this->form->code_groupement = '';
        $this->form->code_airesante = '';
        unset($this->territoires, $this->zones, $this->chefferies, $this->groupements, $this->airesantes);
    }

    public function updatedFormCodeTerritoire(): void
    {
        $this->form->code_zonesante = '';
        $this->form->code_chefferie = '';
        $this->form->code_groupement = '';
        $this->form->code_airesante = '';
        unset($this->zones, $this->chefferies, $this->groupements, $this->airesantes);
    }

    public function updatedFormCodeChefferie(): void
    {
        $this->form->code_groupement = '';
        unset($this->groupements);
    }

    public function updatedFormCodeZonesante(): void
    {
        $this->form->code_airesante = '';
        unset($this->airesantes);
    }

    private function canEditIncident(Incident $incident): bool
    {
        $user = Auth::user();

        if (! $user?->hasAnyRole(['superadmin', 'admin', 'superviseur'])) {
            return false;
        }

        if ($incident->statut_incident !== 'En attente') {
            return false;
        }

        return $user->hasRole('superadmin') || $incident->code_province === $user->code_province;
    }

    public function render()
    {
        return view('livewire.components.incident-edit-modal', [
            'severityOptions' => ['Faible', 'Moyenne', 'Élevée', 'Critique'],
            'sourceInfoOptions' => ['Population locale', 'Humanitaires', 'Autorités administratives', 'Société civile', 'Autres'],
            'confidentialityOptions' => ['Standard', 'Protegé', 'Confidentielle'],
        ]);
    }
}
