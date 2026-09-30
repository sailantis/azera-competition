<use:element path="partials/header"/>
<extends path="layouts/main"/>

<block:content>
    <header/>
    <ul>
        @foreach($items as $i => $item)
        <li @if($i % 2 == 0) class="even" @endif>
            <span class="name">{{ mb_strtoupper((string) $item['label']) }}</span>
            <span class="id" data-id="{{ $item['id'] }}">{{ $user['nickname'] ?? 'anonymous' }}</span>
        </li>
        @endforeach
    </ul>
    <p class="city">{{ $user['address']['city'] }}, {{ $user['address']['country'] }}</p>
    <p class="roles">
        @foreach($user['roles'] as $role)
        <span class="role">{{ $role }}</span>@endforeach
    </p>
</block:content>
