<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Reservation extends Model
{
    protected $fillable = [
        'business_date',
        'starts_at',
        'ends_at',
        'people_count',
        'location_id',
    ];

    protected $casts = [
        'business_date' => 'date',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * @return BelongsToMany<Table, $this>
     */
    public function tables(): BelongsToMany
    {
        return $this->belongsToMany(Table::class);
    }
}
