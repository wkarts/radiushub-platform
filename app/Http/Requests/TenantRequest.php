<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
class TenantRequest extends FormRequest { public function authorize(): bool { return (bool) $this->user()?->is_super_admin; } public function rules(): array { $tenant=$this->route('tenant'); return ['name'=>['required','string','max:150'],'slug'=>['required','alpha_dash','max:80',Rule::unique('tenants','slug')->ignore($tenant?->id)],'document'=>['nullable','string','max:20'],'email'=>['nullable','email','max:150'],'phone'=>['nullable','string','max:30'],'timezone'=>['required','timezone'],'active'=>['nullable','boolean']]; } }
