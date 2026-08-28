<extends:layouts.app/>

<block:title>Features</block:title>

<block:content>
    <h1>Enterprise Feature Demos</h1>

    <p>
        These endpoints exercise Spiral's enterprise subsystems
        (AOP interceptors, PSR-14 events, PSR-16 cache, validation, config)
        on a real Spiral app — without touching the production sailantis-homepage.
    </p>

    <table>
    <thead>
        <tr>
            <th>Feature</th>
            <th>Description</th>
        </tr>
    </thead>
    <tbody>
        @foreach($features as $feature)
        <tr>
            <td>
                <a href="{{ $feature['url'] }}">{{ $feature['title'] }}</a>
            </td>
            <td>{{ $feature['desc'] }}</td>
        </tr>
        @endforeach
    </tbody>
    </table>

    <p class="meta">
        All endpoints return JSON for easy inspection. Click a link to try one.
    </p>
</block:content>