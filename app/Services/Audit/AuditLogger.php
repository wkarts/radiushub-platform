<?php
namespace App\Services\Audit;
use App\Models\AuditLog;
use App\Services\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
class AuditLogger { public function __construct(private readonly Request $request, private readonly TenantContext $context) {} public function record(string $action, ?Model $model=null, array $old=[], array $new=[]): void { $tenantId=$this->context->id() ?? ($model?->getAttribute('tenant_id')) ?? (($model instanceof \App\Models\Tenant)?$model->getKey():null); AuditLog::query()->create(['tenant_id'=>$tenantId,'user_id'=>$this->request->user()?->id,'action'=>$action,'auditable_type'=>$model?->getMorphClass(),'auditable_id'=>$model?->getKey(),'old_values'=>$old ?: null,'new_values'=>$new ?: null,'ip_address'=>$this->request->ip(),'user_agent'=>substr((string)$this->request->userAgent(),0,500),'created_at'=>now()]); } }
