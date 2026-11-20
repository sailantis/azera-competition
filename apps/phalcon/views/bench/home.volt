<h1>Welcome to Azera Competition</h1>

<p>
    This is a benchmark suite comparing <strong>Azera</strong> against other
    popular PHP frameworks (Laravel, Symfony, Spiral, CodeIgniter 4, CakePHP 5, Phalcon 6)
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

<h2>Try it</h2>
<p>Trigger the write + render POST endpoints directly from the browser:</p>
<div class="actions">
    <form method="post" action="/items">
        <button type="submit">POST /items (ORM + flash)</button>
    </form>
    <form method="post" action="/items-qb">
        <button type="submit">POST /items-qb (query builder)</button>
    </form>
</div>
