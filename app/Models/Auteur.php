<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Auteur extends Model
{
    use HasFactory;

    protected $table = 'auteurs';
    protected $primaryKey = 'code_auteur';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'code_auteur',
        'denomination_auteur',
        'observation',
        'create_at',
        'created_by',
    ];

    /**
     * Boot the model.
     */
    protected static function booted()
    {
        static::creating(function ($auteur) {
            if (empty($auteur->create_at)) {
                $auteur->create_at = now()->toDateString();
            }
            if (empty($auteur->created_by) && auth()->check()) {
                $auteur->created_by = auth()->id();
            }
        });
    }

    /**
     * Get the user who created this alleged perpetrator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
