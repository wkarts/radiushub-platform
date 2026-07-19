<?php
namespace App\Services\Mikrotik;
use App\Models\MikrotikDevice;
use Throwable;
class MikrotikService { public function test(MikrotikDevice $device): array { $client=new RouterOsApiClient(); try { $client->connect($device->management_host,(int)$device->api_port,(bool)$device->api_ssl,$device->api_username,(string)$device->api_password); $reply=$client->command('/system/resource/print'); $resource=collect($reply)->firstWhere('type','!re') ?? []; $device->forceFill(['status'=>'online','last_seen_at'=>now(),'last_error'=>null])->save(); return ['ok'=>true,'resource'=>$resource]; } catch(Throwable $e) { $device->forceFill(['status'=>'offline','last_error'=>substr($e->getMessage(),0,1000)])->save(); return ['ok'=>false,'error'=>$e->getMessage()]; } finally { $client->close(); } } }
