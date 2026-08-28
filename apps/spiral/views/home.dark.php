<extends:layouts.app/>

<block:title>Welcome</block:title>

<block:content>
    <h1>Welcome to Azera Competition</h1>

    <p>
        This is a benchmark suite comparing <strong>Azera</strong> against other
        popular PHP frameworks (Laravel, Symfony, Spiral, CodeIgniter 4, CakePHP 5)
        across a realistic request lifecycle: routing, middleware, model queries,
        and template rendering.
    </p>

    <h2>Endpoints</h2>
    <ul>
        <li><a href="/items">/items</a> — paginated item list (ORM hydration)</li>
        <li><a href="/items-qb">/items-qb</a> — paginated item list (query builder, no hydration)</li>
        <li><a href="/items/1">/items/1</a> — single item detail (ORM)</li>
        <li><a href="/items-qb/1">/items-qb/1</a> — single item detail (query builder)</li>
        <li><a href="/features">/features</a> — framework feature demos (AOP, cache, log, events, …)</li>
        <li><a href="/api/items">/api/items</a> — REST API (JSON, ORM hydration)</li>
    </ul>

    <h2>What is measured?</h2>
    <table>
    <tbody>
        <tr>
            <th>GET /</th>
            <td>Routing + middleware + template render (no DB)</td>
        </tr>
        <tr>
            <th>GET /items</th>
            <td>Routing + middleware + model query (COUNT + LIMIT) + pagination + template</td>
        </tr>
        <tr>
            <th>GET /items/{id}</th>
            <td>Routing + middleware + model find + template</td>
        </tr>
        <tr>
            <th>POST /items</th>
            <td>Routing + middleware + model upsert (ORM write path)</td>
        </tr>
        <tr>
            <th>GET /items-qb</th>
            <td>Same as /items but without model hydration</td>
        </tr>
        <tr>
            <th>GET /items-qb/{id}</th>
            <td>Same as /items/{id} but without model hydration</td>
        </tr>
        <tr>
            <th>POST /items-qb</th>
            <td>Same as POST /items but via query builder (no ORM write path)</td>
        </tr>
    </tbody>
    </table>

    <h2>Stack</h2>
    <ul>
        <li>Framework: <strong>Spiral Framework</strong></li>
        <li>Template engine: <strong>Stempler</strong></li>
        <li>Database: <strong>SQLite</strong> (WAL mode)</li>
        <li>ORM: <strong>Cycle ORM</strong> (annotated entities)</li>
        <li>Routes: 4 benchmark endpoints + 100 filler routes</li>
    </ul>
</block:content>