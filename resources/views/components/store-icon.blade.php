@props(['name'])
<svg {{ $attributes->merge(['class' => 'ui-icon']) }} viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="#ui-{{ $name }}"/></svg>
