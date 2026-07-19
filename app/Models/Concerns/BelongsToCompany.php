<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Services\Tenancy\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = app(CompanyContext::class)->id();
            if ($companyId !== null) {
                $builder->where($builder->getModel()->qualifyColumn('company_id'), $companyId);
            }
        });

        static::creating(function ($model): void {
            if (! $model->company_id) {
                $model->company_id = app(CompanyContext::class)->id();
            }

            if (! $model->company_id) {
                throw new LogicException('Não é permitido criar um registro multiempresa sem empresa ativa.');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
