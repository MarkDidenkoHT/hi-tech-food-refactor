<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'telegram_group_chat_id', 'woocommerce_domain', 'is_active', 'last_photo_question_id'])]
class Restaurant extends Model
{
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'telegram_group_chat_id' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Users belonging to this restaurant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    /**
     * Checklist questions for this restaurant.
     */
    public function checklistQuestions(): HasMany
    {
        return $this->hasMany(ChecklistQuestion::class);
    }

    /**
     * Photo questions for this restaurant.
     */
    public function photoQuestions(): HasMany
    {
        return $this->hasMany(PhotoQuestion::class);
    }

    /**
     * Invites scoped to this restaurant.
     */
    public function invites(): HasMany
    {
        return $this->hasMany(Invite::class);
    }

    /**
     * Hardcoded menu items for this restaurant (used when not synced from WooCommerce).
     */
    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    /**
     * Stoplist entries for this restaurant.
     */
    public function stoplistEntries(): HasMany
    {
        return $this->hasMany(StoplistEntry::class);
    }

    /**
     * Calendar events for this restaurant.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
