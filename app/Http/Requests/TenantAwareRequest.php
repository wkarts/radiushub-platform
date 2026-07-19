<?php
namespace App\Http\Requests;
use App\Services\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
abstract class TenantAwareRequest extends FormRequest { protected function tenantId(): string { return app(TenantContext::class)->requireId(); } }
