<h1>Items</h1>
<table>
    <thead>
        <tr><th>ID</th><th>Title</th><th>Created</th></tr>
    </thead>
    <tbody>
        @foreach($items as $item)
        <tr>
            <td>{{ $item->id }}</td>
            <td>{{ $item->title }}</td>
            <td>{{ $item->created_at }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="pagination">
    <span>Page {{ $pagination['currentPage'] }} of {{ $pagination['lastPage'] }}</span>
    @if($pagination['hasPrevious'])
    <a href="{{ $baseUrl }}?page={{ $pagination['previousPage'] }}">&laquo; Prev</a>
    @endif
    @if($pagination['hasNext'])
    <a href="{{ $baseUrl }}?page={{ $pagination['nextPage'] }}">Next &raquo;</a>
    @endif
    <span>{{ $pagination['totalItems'] }} items</span>
</div>