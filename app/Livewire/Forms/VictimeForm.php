<?php

namespace App\Livewire\Forms;

use Livewire\Form;
use App\Models\Victime;
use Illuminate\Validation\Rule;

class VictimeForm extends Form
{
    public ?Victime $victime = null;

    public ?string $incident_id = null;
    public ?int $violence_id = null;
    public string $profile_victimes = '';
    
    public $nbre_femme_0a4ans = 0;
    public $nbre_femme_5a11ans = 0;
    public $nbre_femme_12a17ans = 0;
    public $nbre_femme_18a59ans = 0;
    public $nbre_femme_6Oansouplus = 0; // note: letter O!

    public $nbre_homme_0a4ans = 0;
    public $nbre_homme_5a11ans = 0;
    public $nbre_homme_12a17ans = 0;
    public $nbre_homme_18a59ans = 0;
    public $nbre_homme_6Oansouplus = 0; // note: letter O!

    public string $description_faits = '';

    public function rules()
    {
        return [
            'incident_id' => ['required', 'exists:incidents,id'],
            'violence_id' => [
                'required',
                'integer',
                // Custom rule to check that (incident_id, violence_id) exists in violence_incidents
                function ($attribute, $value, $fail) {
                    $exists = \DB::table('violence_incidents')
                        ->where('id_incident', $this->incident_id)
                        ->where('id_violence', $value)
                        ->exists();
                    if (!$exists) {
                        $fail("La violence sélectionnée n'est pas rapportée pour cet incident.");
                    }
                }
            ],
            'profile_victimes' => ['required', 'string', Rule::in(['Résidants', 'Réfugiés', 'Déplacés', 'Retournés', 'Autres'])],
            
            'nbre_femme_0a4ans' => ['nullable', 'integer', 'min:0'],
            'nbre_femme_5a11ans' => ['nullable', 'integer', 'min:0'],
            'nbre_femme_12a17ans' => ['nullable', 'integer', 'min:0'],
            'nbre_femme_18a59ans' => ['nullable', 'integer', 'min:0'],
            'nbre_femme_6Oansouplus' => ['nullable', 'integer', 'min:0'],

            'nbre_homme_0a4ans' => ['nullable', 'integer', 'min:0'],
            'nbre_homme_5a11ans' => ['nullable', 'integer', 'min:0'],
            'nbre_homme_12a17ans' => ['nullable', 'integer', 'min:0'],
            'nbre_homme_18a59ans' => ['nullable', 'integer', 'min:0'],
            'nbre_homme_6Oansouplus' => ['nullable', 'integer', 'min:0'],

            'description_faits' => ['required', 'string'],
        ];
    }

    public function setVictime(Victime $victime)
    {
        $this->victime = $victime;
        $this->incident_id = $victime->incident_id;
        $this->violence_id = $victime->violence_id;
        $this->profile_victimes = $victime->profile_victimes;

        $this->nbre_femme_0a4ans = $victime->nbre_femme_0a4ans;
        $this->nbre_femme_5a11ans = $victime->nbre_femme_5a11ans;
        $this->nbre_femme_12a17ans = $victime->nbre_femme_12a17ans;
        $this->nbre_femme_18a59ans = $victime->nbre_femme_18a59ans;
        $this->nbre_femme_6Oansouplus = $victime->nbre_femme_6Oansouplus;

        $this->nbre_homme_0a4ans = $victime->nbre_homme_0a4ans;
        $this->nbre_homme_5a11ans = $victime->nbre_homme_5a11ans;
        $this->nbre_homme_12a17ans = $victime->nbre_homme_12a17ans;
        $this->nbre_homme_18a59ans = $victime->nbre_homme_18a59ans;
        $this->nbre_homme_6Oansouplus = $victime->nbre_homme_6Oansouplus;

        $this->description_faits = $victime->description_faits;
    }
}
