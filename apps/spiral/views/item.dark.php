<h1>Item {{ $item->id }}</h1>
<dl>
    <dt>ID</dt><dd>{{ $item->id }}</dd>
    <dt>Title</dt><dd>{{ $item->title }}</dd>
    <dt>Created</dt><dd>{{ $item->created_at }}</dd>
</dl>
<a href="/items">&laquo; Back to list</a>