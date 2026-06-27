<?php

namespace App\Livewire\Pages\Incidents;

use App\Models\Incident;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\AuditLog;
use App\Exceptions\BusinessRuleException;
use App\Livewire\Components\IncidentReferencements;
use App\Services\IncidentDuplicateService;
use App\Services\IncidentQualityService;
use App\Services\IncidentService;
use App\Services\IncidentSlaService;
use App\Services\IncidentTimelineService;

class Show extends Component
{
    public Incident $incident;

    // Confirmation modal
    public bool $showConfirmModal = false;
    public string $confirmTitle = '';
    public string $confirmMessage = '';
    public string $confirmAction = ''; // 'validate'

    public bool $showCoordinatesModal = false;
    public ?string $longitude = null;
    public ?string $latitude = null;

    public bool $showCloseModal = false;
    public string $closeComment = '';

    protected $listeners = ['violences-updated' => 'refreshIncident'];

    public function refreshIncident(): void
    {
        $this->incident->refresh()->load([
            'violences:id,violence_name,categorie_name',
            'province', 'territoire', 'zoneSante',
            'chefferie', 'groupement', 'aireSante', 'evenement',
            'victimes.violence', 'mouvements', 'referencements.provider',
            'reponses', 'caseNotes', 'creator', 'assignedTo'
        ]);
    }

    public function mount(Incident $incident): void
    {
        // Sécurité province (superadmin voit tout)
        $user = Auth::user();

        if (!$user->hasRole('superadmin') && $incident->code_province !== $user->code_province) {
            abort(403);
        }

        // Les incidents archivés ne sont pas visibles
        if ($incident->statut_incident === 'Archivé') {
            abort(404);
        }

        $this->incident = $incident->load([
            'violences:id,violence_name,categorie_name',
            'province', 'territoire', 'zoneSante',
            'chefferie', 'groupement', 'aireSante', 'evenement',
            'victimes.violence', 'mouvements', 'referencements.provider',
            'reponses', 'caseNotes', 'creator', 'assignedTo'
        ]);

        // $this->incident = $incident;
    }

    private function canValidate(): bool
    {
        if (!Auth::user()->hasAnyRole(['superadmin', 'admin', 'superviseur'])) {
            return false;
        }

        // Empêcher si verrouillé ou déjà validé
        if (in_array($this->incident->statut_incident, ['Cloturée', 'Archivé'], true)) {
            return false;
        }

        if ($this->incident->statut_incident === 'Validé') {
            return false;
        }

        return true;
    }

    public function askConfirmValidate(): void
    {
        if (!$this->canValidate()) {
            $this->dispatch('toast', message: "Vous n'êtes pas autorisé à valider cet incident.", type: 'warning', duration: 6000);
            return;
        }

        $this->confirmTitle = "Valider l’incident ?";
        $this->confirmMessage = "Cette action changera le statut de l’incident en « Validé ». Voulez-vous continuer ?";
        $this->confirmAction = 'validate';
        $this->showConfirmModal = true;
    }

    public function runConfirmAction(): void
    {
        $this->showConfirmModal = false;

        if ($this->confirmAction === 'validate') {
            $this->validateIncident();
            return;
        }

        if ($this->confirmAction === 'archive') {
            $this->archiveIncident();
            return;
        }
    }

    public function archiveIncident(): void
    {
        if (!$this->canArchive()) {
            $this->dispatch('toast', message: "Action non autorisée.", type: 'warning', duration: 6000);
            return;
        }

        $oldStatus = $this->incident->statut_incident;

        $this->incident->statut_incident = 'Archivé';
        $this->incident->last_status_changed_at = now();
        $this->incident->save();

        // Audit log
        \App\Models\AuditLog::create([
            'id' => random_int(100000000, 999999999),
            'user_id' => Auth::user()->id,
            'user_action' => 'incident_archived',
            'model_type' => 'incident',
            'model_id' => $this->incident->id, // UUID
            'ip_address' => request()->ip(),
            'action_meta' => json_encode([
                'code_incident' => $this->incident->code_incident,
                'old_status' => $oldStatus,
                'new_status' => 'Archivé',
            ], JSON_UNESCAPED_UNICODE),
        ]);

        // Comme archivé n'est plus visible, on redirige vers la liste
        session()->flash('success', "Incident archivé.");
        $this->redirect(route('incidents.index'), navigate: true);
    }

    private function canArchive(): bool
    {
        // seuls admin/superviseur/superadmin
        if (!Auth::user()->hasAnyRole(['superadmin', 'admin', 'superviseur'])) {
            return false;
        }

        // si déjà archivé/clôturé => bloqué
        if (in_array($this->incident->statut_incident, ['Cloturée', 'Archivé'], true)) {
            return false;
        }

        return true;
    }

    private function canManage(): bool
    {
        $user = Auth::user();

        if (!$user?->hasAnyRole(['superadmin', 'admin', 'superviseur'])) {
            return false;
        }

        return $user->hasRole('superadmin') || $this->incident->code_province === $user->code_province;
    }

    public function openCoordinatesModal(): void
    {
        if (!$this->canManage()) {
            $this->dispatch('toast', message: "Action non autorisée.", type: 'warning', duration: 6000);
            return;
        }

        $this->resetValidation();
        $this->longitude = $this->incident->longitude !== null ? (string) $this->incident->longitude : null;
        $this->latitude = $this->incident->latitude !== null ? (string) $this->incident->latitude : null;
        $this->showCoordinatesModal = true;
    }

    public function saveCoordinates(): void
    {
        $this->validate([
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
        ], [
            'longitude.numeric' => 'La longitude doit etre un nombre valide.',
            'longitude.between' => 'La longitude doit etre comprise entre -180 et 180.',
            'latitude.numeric' => 'La latitude doit etre un nombre valide.',
            'latitude.between' => 'La latitude doit etre comprise entre -90 et 90.',
        ]);

        try {
            $incidentService = app(IncidentService::class);

            $incidentService->updateCoordinates(
                incident: $this->incident,
                longitude: $this->longitude !== null && $this->longitude !== '' ? (float) $this->longitude : null,
                latitude: $this->latitude !== null && $this->latitude !== '' ? (float) $this->latitude : null,
                actor: Auth::user(),
                ipAddress: request()->ip()
            );

            $this->showCoordinatesModal = false;
            $this->refreshIncident();
            $this->dispatch('toast', message: "Coordonnées GPS mises à jour.", type: 'success', duration: 5000);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'warning', duration: 6500);
        }
    }

    public function openCloseModal(): void
    {
        if (!$this->canManage() || $this->incident->statut_incident !== 'Validé') {
            $this->dispatch('toast', message: "Seul un incident validé peut être clôturé.", type: 'warning', duration: 6000);
            return;
        }

        $this->resetValidation();
        $this->closeComment = '';
        $this->showCloseModal = true;
    }

    public function closeIncident(): void
    {
        $this->validate([
            'closeComment' => ['required', 'string', 'min:5', 'max:1000'],
        ], [
            'closeComment.required' => 'Le commentaire de clôture est obligatoire.',
            'closeComment.min' => 'Le commentaire de clôture doit contenir au moins 5 caractères.',
        ]);

        try {
            $incidentService = app(IncidentService::class);

            $incidentService->closeIncident(
                incident: $this->incident,
                comment: $this->closeComment,
                actor: Auth::user(),
                ipAddress: request()->ip()
            );

            $this->showCloseModal = false;
            $this->refreshIncident();
            $this->dispatch('toast', message: "Incident clôturé avec succès.", type: 'success', duration: 5000);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'warning', duration: 6500);
        }
    }

    public function askConfirmArchive(): void
    {
        if (!$this->canArchive()) {
            $this->dispatch('toast', message: "Vous n'êtes pas autorisé à archiver cet incident.", type: 'warning', duration: 6000);
            return;
        }

        $this->confirmTitle = "Archiver l’incident ?";
        $this->confirmMessage = "L’incident sera archivé et ne sera plus visible dans la liste. Voulez-vous continuer ?";
        $this->confirmAction = 'archive';
        $this->showConfirmModal = true;
    }




    public function validateIncident(): void
    {
        if (!$this->canValidate()) {
            $this->dispatch('toast', message: "Action non autorisée.", type: 'warning', duration: 6000);
            return;
        }

        try {
            app(IncidentService::class)->validateIncident(
                incident: $this->incident,
                actor: Auth::user(),
                ipAddress: request()->ip()
            );

            $this->refreshIncident();
            $this->dispatch('incidentStatusChanged')->to(IncidentReferencements::class);
            $this->dispatch('toast', message: "Incident validé avec succès.", type: 'success', duration: 5000);
        } catch (BusinessRuleException $e) {
            $this->dispatch('toast', message: $e->getMessage(), type: 'warning', duration: 6500);
        }
    }

    public function render()
    {
        $this->incident->loadCount(['reponses', 'referencements']);

        return view('livewire.pages.incidents.show', [
            'sla' => app(IncidentSlaService::class)->statusFor($this->incident),
            'quality' => app(IncidentQualityService::class)->report($this->incident),
            'timeline' => app(IncidentTimelineService::class)->forIncident($this->incident),
            'duplicates' => app(IncidentDuplicateService::class)->candidatesFor($this->incident),
        ]);
    }
}
