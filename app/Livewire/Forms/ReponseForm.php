<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\Reponse;
use Illuminate\Validation\Rule;

class ReponseForm extends Form
{
    public ?Reponse $reponse = null;

    public ?string $alerte_id = null;
    public ?string $date_reponse = null;
    public string $fournie_par = '';
    public string $type_reponse = '';
    public array $secteurs_couverts = [];
    public ?int $nbre_menages_couverts = null;
    public ?int $nbre_individus_couverts = null;
    public ?string $impact_reponse = '';
    public ?string $observation_gap = '';
    public $rapport = null;

    public function rules()
    {
        return [
            'alerte_id' => ['required', 'exists:incidents,id'],
            'date_reponse' => ['required', 'date', 'before_or_equal:today'],
            'fournie_par' => ['required', 'string', 'max:255'],
            'type_reponse' => ['required', 'string', Rule::in(['Humanitaire', 'Militaire', 'Mixte', 'Autre'])],
            'secteurs_couverts' => ['required', 'array', 'min:1'],
            'secteurs_couverts.*' => ['string', Rule::in(['Sécurité', 'Protection', 'WASH', 'santé', 'Education', 'Sécurité alimentaire'])],
            'nbre_menages_couverts' => ['nullable', 'integer', 'min:1'],
            'nbre_individus_couverts' => ['nullable', 'integer', 'min:1'],
            'impact_reponse' => ['nullable', 'string'],
            'observation_gap' => ['nullable', 'string'],
        ];
    }

    public function setReponse(Reponse $reponse)
    {
        $this->reponse = $reponse;
        $this->alerte_id = $reponse->alerte_id;
        $this->date_reponse = $reponse->date_reponse ? $reponse->date_reponse->toDateString() : null;
        $this->fournie_par = $reponse->fournie_par;
        $this->type_reponse = $reponse->type_reponse;
        $this->secteurs_couverts = is_array($reponse->secteurs_couverts) ? $reponse->secteurs_couverts : [];
        $this->nbre_menages_couverts = $reponse->nbre_menages_couverts;
        $this->nbre_individus_couverts = $reponse->nbre_individus_couverts;
        $this->impact_reponse = $reponse->impact_reponse;
        $this->observation_gap = $reponse->observation_gap;
        $this->rapport = $reponse->rapport;
    }
}
