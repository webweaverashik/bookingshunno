<?php
namespace App\Traits;

use App\Models\Auth\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasCreatedBy
{
    /**
     * User who created the record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Automatically populate created_by.
     */
    protected static function bootHasCreatedBy(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }
}

/*
 * This trait can be used in any model that has a 'created_by' field to establish a relationship with the User model.
 * It provides a convenient way to access the creator of a record.
 * You currently have the same creator() relationship repeated across:
    CpfOpeningBalance
    CpfContributionBatch
    CpfLedger
    CpfAdvance
    CpfAdvanceRecovery
    BankInterestBatch
    Attachment
    EmployeeSalaryHistory
 */
