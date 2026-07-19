<div class="page-titlebar">
    <div class="page-title">
        <h1>{{ $title }}</h1>
        @isset($description)<p>{{ $description }}</p>@endisset
    </div>
    @if(isset($actions))<div class="actions">{{ $actions }}</div>@endif
</div>
