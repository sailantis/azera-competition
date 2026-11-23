{% include 'partials/header.volt' %}

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Title</th>
            <th>Created</th>
            <th>Badge</th>
        </tr>
    </thead>
    <tbody>
        {% for item in items %}
        <tr{% if loop.index0 % 2 == 0 %} class="even"{% endif %}>
            <td><a href="{{ baseUrl|e }}/{{ item.id|e }}">{{ item.id|e }}</a></td>
            <td>{{ item.title|e }}</td>
            <td>{{ item.created_at|e }}</td>
            <td>
                {% if item.id % 100 == 0 %}
                <span class="badge">Pinned</span>
                {% elseif item.id % 10 == 0 %}
                <span class="badge">Featured</span>
                {% else %}
                <span class="badge">Standard</span>
                {% endif %}
            </td>
        </tr>
        {% endfor %}
    </tbody>
</table>

<div class="pagination">
    <span class="page-info">
        Showing {{ pagination['firstItem']|e }}–{{ pagination['lastItem']|e }}
        of {{ pagination['totalItems']|e }} items
    </span>
    <span class="page-nav">
        {% if pagination['hasPrevious'] %}
        <a href="{{ baseUrl|e }}?page={{ pagination['previousPage']|e }}">← Previous</a>
        {% else %}
        <span class="disabled">← Previous</span>
        {% endif %}
        <span class="page-current">Page {{ pagination['currentPage']|e }} / {{ pagination['lastPage']|e }}</span>
        {% if pagination['hasNext'] %}
        <a href="{{ baseUrl|e }}?page={{ pagination['nextPage']|e }}">Next →</a>
        {% else %}
        <span class="disabled">Next →</span>
        {% endif %}
    </span>
</div>