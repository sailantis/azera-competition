@extends('layouts.app')

@section('title', 'Item ' . $item->id)

@section('content')
    @if(!empty($flash))
<div class="flash">{{ $flash }}</div>
@endif
<h1>Item {{ $item->id }}</h1>

<table>
<tbody>
    <tr>
        <th>ID</th>
        <td>{{ $item->id }}</td>
    </tr>
    <tr>
        <th>Title</th>
        <td>{{ $item->title }}</td>
    </tr>
    <tr>
        <th>Created</th>
        <td>{{ $item->created_at }}</td>
    </tr>
    <tr>
        <th>Status</th>
        <td>
            @if($item->id % 100 == 0)
            <span class="badge">Pinned</span>
            @elseif($item->id % 10 == 0)
            <span class="badge">Featured</span>
            @else
            <span class="badge">Standard</span>
            @endif
        </td>
    </tr>
</tbody>
</table>

<footer>
    <p class="meta">ID #{{ $item->id }} — created {{ $item->created_at }}</p>
</footer>
@endsection