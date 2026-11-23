{% if flash is not empty %}
<div class="flash">Item {{ flash|e }} (id {{ item.id|e }})</div>
{% endif %}

<h1>Item {{ item.id|e }}</h1>

<table>
    <tbody>
        <tr>
            <th>#</th>
            <td>{{ item.id|e }}</td>
        </tr>
        <tr>
            <th>Title</th>
            <td>{{ item.title|e }}</td>
        </tr>
        <tr>
            <th>Created</th>
            <td>{{ item.created_at|e }}</td>
        </tr>
    </tbody>
</table>

<p><a href="/items">← Back to items</a></p>

{% include 'partials/item_footer.volt' %}