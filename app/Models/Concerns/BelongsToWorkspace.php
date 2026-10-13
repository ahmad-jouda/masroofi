<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

trait BelongsToWorkspace
{
    /**
     * Boot the workspace scoping and automatic assignment.
     */
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            if (auth()->check() && auth()->user()->workspace_id) {
                $table = $builder->getModel()->getTable();
                $builder->where("{$table}.workspace_id", auth()->user()->workspace_id);
            }
        });

        static::creating(function ($model) {
            if (auth()->check() && auth()->user()->workspace_id) {
                if (empty($model->workspace_id)) {
                    $model->workspace_id = auth()->user()->workspace_id;
                }
            }

            // Automatically attribute author if model has user_id
            if (auth()->check() && Schema::hasColumn($model->getTable(), 'user_id')) {
                if (empty($model->user_id)) {
                    $model->user_id = auth()->id();
                }
            }
        });
    }

    /**
     * Relationship to the Workspace.
     */
    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
