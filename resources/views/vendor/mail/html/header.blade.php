@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ asset('images/logo-hijauan.jpeg') }}" class="logo" alt="" width="72" height="72">
<span class="brand-name">{{ config('app.name') }}</span>
</a>
</td>
</tr>
