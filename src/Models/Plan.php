<?php

namespace Abr4xas\LaravelPlans\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'duration',
    ];

    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }
}
