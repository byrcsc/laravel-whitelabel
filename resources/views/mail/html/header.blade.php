{{-- Laravel's own mail::html.header, with the brand's logo in front of it.
     Anything the brand does not supply falls through to Laravel's markup,
     including its "Laravel" logo branch. --}}
@props(['url'])
@php($brandLogo = Byrcsc\Whitelabel\Mail\BrandedMarkdown::logoUrl())
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;{{ Byrcsc\Whitelabel\Mail\BrandedMarkdown::headingStyle() }}">
@if ($brandLogo !== null)
<img src="{{ $brandLogo }}" class="logo" alt="{{ Byrcsc\Whitelabel\Mail\BrandedMarkdown::name() }}">
@elseif (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo" alt="Laravel Logo">
@else
{!! $slot !!}
@endif
</a>
</td>
</tr>
