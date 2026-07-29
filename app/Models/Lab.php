<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lab extends Model
{
    protected $fillable = [
        'management_company_id', 'name', 'clia_number', 'address', 'profile', 'timezone',
        'drive_root_folder_id', 'active',
    ];

    /**
     * Editable profile fields (key => label) used to auto-fill forms. Referenced from form
     * definitions via the "profile:<key>" default token. Add a field here and it appears on
     * the Lab Profile page and becomes available to forms.
     */
    public const PROFILE_FIELDS = [
        'director_name' => 'Laboratory Director',
        'director_credentials' => 'Director Credentials (e.g. PhD, HCLD)',
        'tech_supervisor' => 'Technical Supervisor',
        'general_supervisor' => 'General Supervisor',
        'clinical_consultant' => 'Clinical Consultant',
        'phone' => 'Phone',
        'hours' => 'Hours of Operation',
        'certificate_type' => 'CLIA Certificate Type',
        'test_volume' => 'Estimated Annual Test Volume',
        'specialties' => 'Specialties / Subspecialties',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'profile' => 'array'];
    }

    /** A single profile value (empty string if unset). */
    public function profileValue(string $key): string
    {
        return (string) data_get($this->profile, $key, '');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(ManagementCompany::class, 'management_company_id');
    }

    public function obligations(): HasMany
    {
        return $this->hasMany(Obligation::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(LabUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'lab_user')->withPivot('active')->withTimestamps();
    }
}
