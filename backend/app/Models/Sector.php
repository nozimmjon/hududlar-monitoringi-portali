<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = ['code', 'name_short', 'org_full', 'signer_text', 'sort_order'];

    public function tasks(): HasMany
    {
        return $this->hasMany(SectorTask::class);
    }

    /** Public-relative path of the bundled logo (svg preferred), or null when none shipped. */
    public function logoPath(): ?string
    {
        foreach (['svg', 'png'] as $ext) {
            $rel = "img/sectors/{$this->code}.{$ext}";
            if (is_file(public_path($rel))) {
                return $rel;
            }
        }

        return null;
    }
}
