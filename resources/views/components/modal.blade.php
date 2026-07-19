@props(['id','title','size'=>''])
<div class="modal-backdrop" id="{{ $id }}" aria-hidden="true">
    <div class="modal {{ $size ? 'modal-'.$size : '' }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title">
        <div class="modal-header"><div class="modal-title" id="{{ $id }}-title">{{ $title }}</div><button class="icon-button" type="button" data-modal-close aria-label="Fechar">✕</button></div>
        <div class="modal-body">{{ $slot }}</div>
    </div>
</div>
