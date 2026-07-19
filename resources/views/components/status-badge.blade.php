@php
$value = $value instanceof \BackedEnum ? $value->value : (string) $value;
$map = [
'active'=>'success','online'=>'success','paid'=>'success','acknowledged'=>'success','processed'=>'success','accept'=>'success',
'available'=>'success','used'=>'info','expired'=>'danger','error'=>'danger','pending'=>'warning','overdue'=>'danger','refunded'=>'info','partially_refunded'=>'warning','chargeback'=>'danger','ignored'=>'muted','success'=>'success','suspended'=>'warning','offline'=>'danger','blocked'=>'danger','disabled'=>'muted','inactive'=>'muted','cancelled'=>'muted','failed'=>'danger','reject'=>'danger','unknown'=>'muted','draft'=>'info'
];
$labels=['available'=>'Disponível','used'=>'Utilizado','expired'=>'Expirado','error'=>'Erro','active'=>'Ativo','online'=>'Online','paid'=>'Pago','pending'=>'Pendente','overdue'=>'Vencido','refunded'=>'Estornado','partially_refunded'=>'Estorno parcial','chargeback'=>'Chargeback','ignored'=>'Ignorado','success'=>'Sucesso','suspended'=>'Suspenso','offline'=>'Offline','blocked'=>'Bloqueado','disabled'=>'Desativado','inactive'=>'Inativo','cancelled'=>'Cancelado','failed'=>'Falhou','unknown'=>'Desconhecido','draft'=>'Rascunho','acknowledged'=>'Confirmado','processed'=>'Processado','accept'=>'Aceito','reject'=>'Recusado'];
@endphp
<span class="badge badge-{{ $map[$value] ?? 'info' }}">{{ $labels[$value] ?? ucfirst($value) }}</span>
