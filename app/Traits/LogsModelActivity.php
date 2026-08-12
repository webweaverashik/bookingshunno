<?php
namespace App\Traits;

use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;

/**
 * Shared Spatie activity-log configuration for application models.
 *
 * Gives every model a human-readable, per-event description (so the log stops
 * showing the bare event name like "updated") while keeping each model's own
 * log name and attribute set.
 *
 * Drop the model's hand-written getActivitylogOptions() and instead:
 *
 *   use App\Traits\LogsModelActivity;
 *
 *   class Employee extends BaseModel
 *   {
 *       use LogsModelActivity;
 *
 *       // optional overrides ──────────────────────────────────────────
 *       protected ?string $auditLogName    = 'employee_crud';            // default: snake(class)
 *       protected ?string $auditLabel       = 'Employee';                // default: headline(class)
 *       protected array   $auditAttributes  = ['name', 'designation'];   // default: all fillable
 *   }
 *
 * Note: descriptions are written at log time, so only NEW activity records pick
 * up the new wording — existing rows keep whatever was stored originally.
 */
trait LogsModelActivity
{

    public function getActivitylogOptions(): LogOptions
    {
        $attributes = $this->auditAttributes ?? [];

        $options = LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->auditLogName ?? Str::snake(class_basename(static::class)))
            ->setDescriptionForEvent(fn(string $event) => $this->buildAuditDescription($event));

        // A model may restrict logging to a subset; otherwise log all fillable.
        return ! empty($attributes)
            ? $options->logOnly($attributes)
            : $options->logFillable();
    }

    /**
     * "Employee was updated", "Salary History was created", etc.
     */
    protected function buildAuditDescription(string $event): string
    {
        $label = $this->auditLabel ?? Str::headline(class_basename(static::class));

        return match ($event) {
            'created' => "{$label} was created",
            'updated' => "{$label} was updated",
            'deleted' => "{$label} was deleted",
            'restored' => "{$label} was restored",
            default => "{$label} was {$event}",
        };
    }
}
