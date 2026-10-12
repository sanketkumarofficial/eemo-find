@props(['title' => null, 'description' => null])
<section {{ $attributes->class(['card']) }}>
@if($title)<div class="card-heading"><h2>{{ $title }}</h2>@if($description)<p>{{ $description }}</p>@endif</div>@endif
{{ $slot }}
</section>
