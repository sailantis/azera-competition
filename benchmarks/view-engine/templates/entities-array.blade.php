@extends('layouts.main')

@section('content')
    @include('partials.header', ['title' => $title, 'items' => $items])
<ul>
    @foreach($items as $i => $item)
    <li @if($i % 2 == 0) class="even" @endif>
        <span class="name">{{ mb_strtoupper($item['label']) }}</span>
        <span class="id" data-id="{{ $item['id'] }}">{{ $user['nickname'] ?? 'anonymous' }}</span>
    </li>
    @endforeach
</ul>
<p class="city">{{ $user['address']['city'] }}, {{ $user['address']['country'] }}</p>
<p class="roles">
    @foreach($user['roles'] as $role)
    <span class="role">{{ $role }}</span>@endforeach
</p>
@endsection
