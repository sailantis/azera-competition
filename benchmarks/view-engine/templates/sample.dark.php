<use:element path="partials/header"/>
<extends path="layouts/main"/>

<block:content>
    <header/>
    <ul>
        @foreach($items as $i => $item)
        <li @if($i % 2 == 0) class="even" @endif>
            {{ mb_strtoupper((string) $item) }}
            <div class="extras">
                @for($j = 0; $j
                < 10; $j++)
                    <span>
                    {{ $item . '-' . $j }}
                </span>
                @endfor
            </div>
        </li>
        @endforeach
    </ul>
</block:content>