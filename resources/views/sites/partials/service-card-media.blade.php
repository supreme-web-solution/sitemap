<img
    class="service-photo{{ empty($service['image_url']) || str_starts_with((string) $service['image_url'], 'data:') ? ' is-placeholder' : '' }}"
    src="{{ $service['image_url'] }}"
    alt=""
    loading="lazy"
    decoding="async"
    {!! $e->image('service', $loop->index, $service['title']) !!}
>
