<?php

/**
 * Fixture objects for the `entities` page.
 *
 * PLAIN classes with PUBLIC properties, deliberately:
 *
 *   - Twig's dot operator resolves `a.b` in this order: array key, then
 *     PROPERTY, then method (b()/getB()/isB()). A plain public property is the
 *     only shape every engine reaches identically, so the fixture does not
 *     favour one access mechanism over another.
 *   - Blade and Stempler are raw PHP here (`$item->label`), Plates and native
 *     are raw PHP too. If the fixture exposed getters, an engine would have to
 *     guess which accessor convention to use, and the gate would be measuring
 *     accessor naming rather than property access.
 *   - No ArrayAccess and no __get: both would let an engine silently take a
 *     different path to the same value, which hides a difference in the thing
 *     being measured.
 *
 * They are also NOT declared readonly or final, because Blade compiles property
 * reads into bare PHP on the same object and there is no reason to constrain it.
 */

class BenchmarkItem
{
    public function __construct(
        public int $id,
        public string $label,
    )
    {
    }
}

class BenchmarkAddress
{
    public function __construct(
        public string $city,
        public string $country,
    )
    {
    }
}

class BenchmarkUser
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public string $name,
        public ?string $nickname,
        public BenchmarkAddress $address,
        public array $roles,
    )
    {
    }
}

/**
 * Build the page's item list and user.
 *
 * @return array{items:list<BenchmarkItem>,user:BenchmarkUser}
 */
function benchmarkEntityVars(int $items): array
{
    $list = [];
    for ($i = 1; $i <= $items; $i++) {
        $list[] = new BenchmarkItem($i, 'Item ' . $i);
    }

    return [
        'items' => $list,
        'user'  => new BenchmarkUser(
            'Benchmark User',
            null,
            new BenchmarkAddress('Berlin', 'Germany'),
            ['admin', 'editor', 'viewer'],
        ),
    ];
}

/**
 * The SAME page data as plain nested arrays, key for key and value for value.
 *
 * Why a second builder rather than `castToArray(benchmarkEntityVars($n))`: that
 * call exists (and Clarity runs it on every render), but using it here would
 * make the array page depend on the very conversion it is meant to be measured
 * against — and it would silently follow any future change to that method. The
 * literals below are written out so the two pages are provably the same data by
 * inspection, which is what makes their difference attributable to ACCESS SHAPE
 * rather than to content.
 *
 * The `nickname => null` is deliberate: the templates render it behind a default
 * (`?? 'anonymous'` / `| default('anonymous')`), so a null that is preserved must
 * be preserved by BOTH shapes for the pages to render the same markup.
 *
 * @return array{items:list<array{id:int,label:string}>,user:array{name:string,nickname:?string,address:array{city:string,country:string},roles:list<string>}}
 */
function benchmarkEntityArrayVars(int $items): array
{
    $list = [];
    for ($i = 1; $i <= $items; $i++) {
        $list[] = ['id' => $i, 'label' => 'Item ' . $i];
    }

    return [
        'items' => $list,
        'user'  => [
            'name'     => 'Benchmark User',
            'nickname' => null,
            'address'  => ['city' => 'Berlin', 'country' => 'Germany'],
            'roles'    => ['admin', 'editor', 'viewer'],
        ],
    ];
}

/**
 * The `mixed` page's data: 20 flat values plus $items rows of six fields each.
 *
 * TWO properties are load-bearing and neither is decoration:
 *
 *   1. EVERY value is HTML-hostile. This page absorbed the retired `escaping`
 *      page's job, and that job only exists if the data needs escaping: on
 *      `items = [1, 2, 3]` an engine that escapes and one that prints raw produce
 *      byte-identical output, so the escape path would be untested. One value per
 *      row is also placed in an ATTRIBUTE context by the templates (`data-sku`),
 *      where ENT_QUOTES is what makes it safe and the surrounding syntax is the
 *      engine's own.
 *
 *   2. EVERY value is DISTINCT, so a template that reads the wrong variable
 *      renders a DIFFERENT page and the parity gate fails. Twenty values that all
 *      read "Benchmark" would let a copy-paste error in one of six templates pass
 *      every check this repository has.
 *
 * The shape is deliberately the shape the two Clarity modes are measured
 * against. Every one of the 20 variables is read TWICE by every template — once
 * as text and once as a `data-value` attribute — which is 40 flat variable
 * accesses per render, on top of 7 field reads per row (1440 accesses in total at
 * the default 200 items). That width is the whole reason the page exists: the
 * modes differ in the cost of a SINGLE access (sandboxed `$__c_va['name']` hash
 * reads vs open mode's seeded PHP locals), so the difference only becomes
 * readable once there are enough accesses that it is not swamped by the rest of
 * the render.
 *
 * The second access is an attribute rather than a repeated echo on purpose: it
 * is different work (a different escape context) and it leaves a structural
 * trace, so a template in which the second access went missing is caught by the
 * gate's DOM comparison rather than merely rendering a shorter string.
 *
 * `rows` is a list of ARRAYS, not objects, because that is the shape the two
 * Clarity modes differ on and the shape the `entities`/`entities-array` pair
 * already establishes a convention for; the templates access it by KEY.
 *
 * `rowCount` is the same number the header partial derives from `rows |> length`,
 * so the page states its own size twice from two different sources and the gate
 * would catch them disagreeing.
 *
 * @return array<string,mixed>
 */
function benchmarkMixedVars(int $items): array
{
    // `&` and `"` together are what makes an escaper and a non-escaper
    // distinguishable; the tag makes a RAW value visibly change the structure,
    // which the gate's DOM comparison can see even when the text collapses.
    $hostile = static fn(string $name): string => '<b>&"' . $name . '"</b>';

    $rows = [];
    for ($i = 1; $i <= $items; $i++) {
        $rows[] = [
            'id'       => $i,
            'label'    => $hostile('Item ' . $i),
            'sku'      => '<b>&"SKU-' . $i . '"</b>',
            'city'     => $hostile('Berlin ' . $i),
            'category' => $hostile('Category ' . $i),
            'status'   => $hostile('Status ' . $i),
        ];
    }

    return [
        // Named `items`, not `rows`: the shared header partial renders
        // `Items: {{ items |> length }}` and every other page hands it `items`.
        // A second name for the same thing would need every engine's header
        // partial to know about both — six more files to keep equivalent for a
        // synonym. The row COUNT is stated twice on purpose (`items |> length`
        // in the header, `rowCount` in the body) so the gate can catch the two
        // disagreeing.
        'items'     => $rows,
        'rowCount'  => $items,
        'total'     => $hostile('1234.56'),
        'theme'     => $hostile('theme'),
        'locale'    => $hostile('en-GB'),
        'region'    => $hostile('eu-central-1'),
        'currency'  => $hostile('EUR'),
        'plan'      => $hostile('enterprise'),
        'channel'   => $hostile('web'),
        'tier'      => $hostile('gold'),
        'status'    => $hostile('active'),
        'build'     => $hostile('2026.09'),
        'stage'     => $hostile('production'),
        'cluster'   => $hostile('azera-1'),
        'node'      => $hostile('node-7'),
        'release'   => $hostile('v3.4.1'),
        'subtitle'  => $hostile('Subtitle'),
        'tagline'   => $hostile('Tagline'),
        'generated' => $hostile('2026-09-26'),
        'owner'     => $hostile('operations'),
        'checksum'  => $hostile('deadbeef'),
    ];
}