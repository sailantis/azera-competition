<h1>Enterprise Feature Demos</h1>

<p>
    These endpoints exercise Phalcon 6's native subsystems (cache, events,
    validation, config, the db adapter's events manager, logger) on a real
    Phalcon app — the same set every other entrant serves, so the feature rows
    are comparable.
</p>

<table>
    <thead>
        <tr>
            <th>Feature</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        {% for feature in features %}
        <tr>
            <td>
                <a href="{{ feature['url']|e }}">{{ feature['title']|e }}</a>
            </td>
            <td>{{ feature['desc']|e }}</td>
        </tr>
        {% endfor %}
    </tbody>
</table>

<p class="meta">
    All endpoints return JSON for easy inspection. Click a link to try one.
</p>