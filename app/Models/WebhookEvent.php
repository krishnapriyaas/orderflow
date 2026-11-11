<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $event_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property WebhookOutcome|null $outcome
 * @property Carbon|null $processed_at
 */
#[Fillable([
    'event_id',
    'type',
    'payload',
    'outcome',
    'processed_at',
])]
class WebhookEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'outcome' => WebhookOutcome::class,
            'processed_at' => 'datetime',
        ];
    }
}
