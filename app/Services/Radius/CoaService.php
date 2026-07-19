<?php

namespace App\Services\Radius;

use App\Models\CoaRequest;
use App\Models\MikrotikDevice;
use App\Models\RadiusAccounting;
use App\Services\Security\RadiusCredentialVault;
use Throwable;

class CoaService
{
    public function __construct(private readonly RadClientService $client, private readonly RadiusCredentialVault $vault)
    {
    }

    public function disconnect(RadiusAccounting $session, string $reason = 'Administrative-Reset'): CoaRequest
    {
        $device = $session->mikrotik ?: MikrotikDevice::query()->where('radius_source_ip', $session->nas_ip_address)->firstOrFail();
        $attributes = array_filter([
            'User-Name' => $session->username,
            'Acct-Session-Id' => $session->acct_session_id,
            'NAS-IP-Address' => $session->nas_ip_address,
            'Framed-IP-Address' => $session->framed_ip_address,
            'Acct-Terminate-Cause' => $reason,
        ]);

        $request = CoaRequest::query()->create([
            'tenant_id' => $device->tenant_id,
            'company_id' => $device->company_id,
            'mikrotik_device_id' => $device->id,
            'radius_accounting_id' => $session->id,
            'type' => 'disconnect',
            'status' => 'pending',
            'attributes' => $attributes,
            'requested_by' => auth()->id(),
            'requested_at' => now(),
        ]);

        try {
            $response = $this->client->send($device->management_host, (int)$device->coa_port, 'disconnect', $this->vault->decrypt($device->radius_secret_ciphertext), $attributes);
            $request->update(['status' => 'acknowledged', 'response' => $response, 'completed_at' => now()]);
        } catch (Throwable $e) {
            $request->update(['status' => 'failed', 'response' => ['error' => $e->getMessage()], 'completed_at' => now()]);
            throw $e;
        }

        return $request;
    }

    public function changeRateLimit(RadiusAccounting $session, string $rateLimit): CoaRequest
    {
        $device = $session->mikrotik ?: MikrotikDevice::query()->where('radius_source_ip', $session->nas_ip_address)->firstOrFail();
        $attributes = array_filter(['User-Name'=>$session->username,'Acct-Session-Id'=>$session->acct_session_id,'NAS-IP-Address'=>$session->nas_ip_address,'Mikrotik-Rate-Limit'=>$rateLimit]);
        $request = CoaRequest::query()->create(['tenant_id'=>$device->tenant_id,'company_id'=>$device->company_id,'mikrotik_device_id'=>$device->id,'radius_accounting_id'=>$session->id,'type'=>'coa','status'=>'pending','attributes'=>$attributes,'requested_by'=>auth()->id(),'requested_at'=>now()]);
        try { $response=$this->client->send($device->management_host,(int)$device->coa_port,'coa',$this->vault->decrypt($device->radius_secret_ciphertext),$attributes); $request->update(['status'=>'acknowledged','response'=>$response,'completed_at'=>now()]); }
        catch(Throwable $e){ $request->update(['status'=>'failed','response'=>['error'=>$e->getMessage()],'completed_at'=>now()]); throw $e; }
        return $request;
    }
}
